<?php

namespace App\Services\Billing;

use App\Enums\Billing\InvoiceStatus;
use App\Enums\Billing\InvoiceType;
use App\Enums\Payment\PaymentStatus;
use App\Models\BillingExemption;
use App\Models\BillingPeriod;
use App\Models\ContributionType;
use App\Models\House;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\User;
use App\Models\WaterUsageRecord;
use App\Services\Audit\AuditService;
use App\Services\CodeGeneratorService;
use App\Services\Communication\NotificationService;
use App\Services\Contribution\ContributionService;
use App\Services\Waste\WasteService;
use App\Support\Money;
use Brick\Math\BigDecimal;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Pembuatan & siklus invoice (FR-BIL-001..005, Q8, S-3, S-4).
 *
 * - Satu invoice per jenis per rumah per bulan: `water`, `waste`,
 *   `contribution:<id>` (billing_key, dikunci partial unique index).
 * - generate() idempotent: menjalankan ulang hanya membuat invoice yang belum
 *   ada. Invoice dibuat sebagai DRAFT; issue() menerbitkannya.
 * - Nominal disalin sebagai snapshot di invoice_details; konfigurasi tarif
 *   berikutnya tidak memengaruhi invoice lama (BR-006/007, EX-008).
 * - Rumah dengan pembebasan aktif (S-4 "Belum Menempati") dilewati.
 */
class BillingService
{
    public function __construct(
        private readonly WasteService $waste,
        private readonly ContributionService $contributions,
        private readonly CodeGeneratorService $codes,
        private readonly NotificationService $notifications,
        private readonly AuditService $audit,
    ) {}

    public function period(CarbonInterface $month): BillingPeriod
    {
        $start = Carbon::instance($month)->startOfMonth();

        return BillingPeriod::firstOrCreate(
            ['period_code' => $start->format('Y-m')],
            ['period_start' => $start->toDateString(), 'period_end' => $start->copy()->endOfMonth()->toDateString(), 'status' => 'open'],
        );
    }

    public function isExempt(string $houseId, InvoiceType $type, CarbonInterface $month): bool
    {
        return BillingExemption::query()
            ->where('house_id', $houseId)
            ->where(fn ($q) => $q->whereNull('billing_type')->orWhere('billing_type', $type->value))
            ->whereDate('valid_from', '<=', $month->copy()->endOfMonth()->toDateString())
            ->whereDate('valid_until', '>=', $month->copy()->startOfMonth()->toDateString())
            ->exists();
    }

    /**
     * Buat draft invoice untuk bulan tertentu.
     *
     * @param  list<string>  $types  water|waste|contribution
     * @return array{created: int, skipped_existing: int, skipped_exempt: int, by_type: array<string, int>}
     */
    public function generate(CarbonInterface $month, array $types = ['water', 'waste', 'contribution']): array
    {
        $month = Carbon::instance($month)->startOfMonth();
        $summary = ['created' => 0, 'skipped_existing' => 0, 'skipped_exempt' => 0, 'by_type' => []];

        DB::transaction(function () use ($month, $types, &$summary) {
            $period = $this->period($month);
            BillingPeriod::whereKey($period->getKey())->lockForUpdate()->first();

            if ($period->status === 'closed') {
                throw new RuntimeException("Periode {$period->label()} sudah ditutup.");
            }

            $make = function (string $houseId, InvoiceType $type, string $key, array $lines, ?string $contributionTypeId = null) use ($period, $month, &$summary): ?Invoice {
                if ($this->isExempt($houseId, $type, $month)) {
                    $summary['skipped_exempt']++;

                    return null;
                }

                $exists = Invoice::where('house_id', $houseId)
                    ->where('billing_period_id', $period->getKey())
                    ->where('billing_key', $key)
                    ->where('status', '!=', InvoiceStatus::Cancelled)
                    ->exists();
                if ($exists) {
                    $summary['skipped_existing']++;

                    return null;
                }

                $invoice = $this->createInvoice($period, $month, $houseId, $type, $key, $lines, $contributionTypeId);
                $summary['created']++;
                $summary['by_type'][$type->value] = ($summary['by_type'][$type->value] ?? 0) + 1;

                return $invoice;
            };

            if (in_array('water', $types, true)) {
                $usages = WaterUsageRecord::query()
                    ->whereHas('house', fn ($q) => $q->where('is_active', true))
                    ->whereDate('usage_period', $month->toDateString())
                    ->whereNull('billed_at')
                    ->lockForUpdate()
                    ->get();

                foreach ($usages as $usage) {
                    $invoice = $make($usage->house_id, InvoiceType::Water, 'water', $this->waterLines($usage));
                    if ($invoice) {
                        $usage->update(['billed_at' => now()]);
                    }
                }
            }

            if (in_array('waste', $types, true)) {
                $fee = $this->waste->feeFor($month);
                if ($fee && Money::of($fee->monthly_amount)->isPositive()) {
                    foreach ($this->waste->subscriptionsFor($month) as $subscription) {
                        $make($subscription->house_id, InvoiceType::Waste, 'waste', [[
                            'component_type' => 'waste_fee',
                            'source_type' => 'waste_fee_period',
                            'source_id' => $fee->getKey(),
                            'description' => 'Iuran sampah '.$month->translatedFormat('F Y'),
                            'quantity' => '1',
                            'unit_price' => Money::of($fee->monthly_amount),
                            'snapshot' => ['monthly_amount' => Money::toDb($fee->monthly_amount), 'fee_period_from' => $fee->effective_from->toDateString()],
                        ]]);
                    }
                }
            }

            if (in_array('contribution', $types, true)) {
                foreach (ContributionType::where('status', 'active')->get() as $contribution) {
                    foreach ($this->contributions->chargesFor($contribution, $month) as $houseId => $amount) {
                        if (! $amount->isPositive()) {
                            continue;
                        }
                        $make($houseId, InvoiceType::Contribution, 'contribution:'.$contribution->getKey(), [[
                            'component_type' => 'contribution',
                            'source_type' => 'contribution_type',
                            'source_id' => $contribution->getKey(),
                            'description' => $contribution->name.' '.$month->translatedFormat('F Y'),
                            'quantity' => '1',
                            'unit_price' => $amount,
                            'snapshot' => ['contribution' => $contribution->name, 'amount' => (string) $amount],
                        ]], $contribution->getKey());
                    }
                }
            }

            $this->audit->record('invoices.generated', $period, ['types' => $types, ...$summary]);
        });

        return $summary;
    }

    /**
     * Terbitkan draft (izin invoices.issue, dipisahkan dari Admin).
     *
     * @param  Collection<int, Invoice>|null  $invoices  null = semua draft periode ini
     */
    public function issue(BillingPeriod $period, User $user, ?Collection $invoices = null): int
    {
        $issued = collect();

        DB::transaction(function () use ($period, $user, $invoices, &$issued) {
            $query = Invoice::where('billing_period_id', $period->getKey())->where('status', InvoiceStatus::Draft)->lockForUpdate();
            if ($invoices) {
                $query->whereIn('id', $invoices->pluck('id'));
            }

            foreach ($query->get() as $invoice) {
                $invoice->status->assertCanTransitionTo(InvoiceStatus::Issued);
                $invoice->fill(['status' => InvoiceStatus::Issued, 'issued_at' => now(), 'issued_by' => $user->getKey()])->save();
                $issued->push($invoice);
            }

            $this->audit->record('invoices.issued', $period, ['count' => $issued->count()]);
        });

        // Notifikasi per rumah, setelah transaksi sukses (FR-NOT-001).
        foreach ($issued->groupBy('house_id') as $houseId => $houseInvoices) {
            $house = House::find($houseId);
            if (! $house) {
                continue;
            }
            $total = Money::sum($houseInvoices->pluck('total_amount'));
            $this->notifications->notifyHouse(
                $house,
                'Tagihan baru '.$period->label(),
                $houseInvoices->count().' tagihan untuk '.$house->house_code.' sebesar '.Money::format($total).'.',
                route('my-bills.index'),
                'fas fa-file-invoice',
                'invoice_issued',
            );
        }

        return $issued->count();
    }

    /**
     * Batalkan invoice yang belum dibayar (FR-BIL-004; izin invoices.cancel).
     * Pemakaian air dilepas lagi supaya bisa ditagih ulang.
     */
    public function cancel(Invoice $invoice, User $user, string $reason): Invoice
    {
        return DB::transaction(function () use ($invoice, $user, $reason) {
            $invoice = Invoice::whereKey($invoice->getKey())->lockForUpdate()->firstOrFail();
            $invoice->status->assertCanTransitionTo(InvoiceStatus::Cancelled);

            if (Money::of($invoice->paid_amount)->isPositive()) {
                throw new RuntimeException('Invoice yang sudah dibayar tidak bisa dibatalkan. Batalkan pembayarannya lebih dulu.');
            }

            $held = $invoice->allocations()->whereHas('payment', fn ($q) => $q->whereIn('status', [PaymentStatus::PendingVerification, PaymentStatus::CorrectionRequired]))->exists();
            if ($held) {
                throw new RuntimeException('Invoice ini sedang diajukan pembayarannya. Tolak pengajuan itu lebih dulu.');
            }

            $old = $invoice->only(['status']);
            $invoice->fill([
                'status' => InvoiceStatus::Cancelled,
                'cancelled_at' => now(),
                'cancelled_by' => $user->getKey(),
                'cancellation_reason' => $reason,
            ])->save();

            if ($invoice->invoice_type === InvoiceType::Water) {
                $usageIds = $invoice->details()->where('source_type', 'water_usage_record')->pluck('source_id');
                WaterUsageRecord::whereIn('id', $usageIds)->update(['billed_at' => null]);
            }

            $this->audit->changed('invoices.cancelled', $invoice, $reason, $old);

            return $invoice;
        });
    }

    /**
     * Tandai invoice lewat jatuh tempo (dijalankan terjadwal tiap hari).
     */
    public function markOverdue(?CarbonInterface $today = null): int
    {
        $today = ($today ?? today())->toDateString();

        return Invoice::query()
            ->where('status', InvoiceStatus::Issued)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $today)
            ->update(['status' => InvoiceStatus::Overdue, 'updated_at' => now()]);
    }

    /**
     * Status setelah invoice kembali terbuka (pembalikan pembayaran).
     */
    public function openStatusFor(Invoice $invoice): InvoiceStatus
    {
        return $invoice->due_date && $invoice->due_date->lt(today()) ? InvoiceStatus::Overdue : InvoiceStatus::Issued;
    }

    /**
     * @param  list<array{component_type: string, source_type: ?string, source_id: ?string, description: string, quantity: string, unit_price: BigDecimal, subtotal?: BigDecimal, snapshot: array<string, mixed>}>  $lines
     */
    private function createInvoice(BillingPeriod $period, Carbon $month, string $houseId, InvoiceType $type, string $key, array $lines, ?string $contributionTypeId): Invoice
    {
        $invoiceDate = $type === InvoiceType::Water
            ? $month->copy()->addMonthNoOverflow()->day(min(config('billing.water_issue_day'), 28))
            : $month->copy()->endOfMonth();

        $total = Money::zero();
        foreach ($lines as &$line) {
            $line['subtotal'] ??= Money::of(Money::quantity($line['quantity'])->multipliedBy($line['unit_price']));
            $total = $total->plus($line['subtotal']);
        }
        unset($line);

        $invoice = Invoice::create([
            'invoice_number' => $this->codes->generate('INV', 'invoices', 'invoice_number'),
            'house_id' => $houseId,
            'billing_period_id' => $period->getKey(),
            'invoice_type' => $type,
            'billing_key' => $key,
            'contribution_type_id' => $contributionTypeId,
            'invoice_date' => $invoiceDate->toDateString(),
            'due_date' => $invoiceDate->copy()->addDays(config('billing.due_days'))->toDateString(),
            'total_amount' => Money::toDb($total),
            'paid_amount' => '0.00',
            'outstanding_amount' => Money::toDb($total),
            'status' => InvoiceStatus::Draft,
        ]);

        foreach ($lines as $line) {
            InvoiceDetail::create([
                'invoice_id' => $invoice->getKey(),
                'component_type' => $line['component_type'],
                'source_type' => $line['source_type'],
                'source_id' => $line['source_id'],
                'description' => $line['description'],
                'quantity' => (string) Money::quantity($line['quantity']),
                'unit_price' => Money::toDb($line['unit_price']),
                'subtotal' => Money::toDb($line['subtotal']),
                'calculation_snapshot' => $line['snapshot'],
            ]);
        }

        return $invoice;
    }

    /**
     * Rincian tagihan air dari usage record (abodemen + pemakaian [+ minimum]).
     *
     * @return list<array<string, mixed>>
     */
    private function waterLines(WaterUsageRecord $usage): array
    {
        $snapshot = $usage->calculation_snapshot ?? [];
        $base = Money::of($usage->base_fee);
        $usageAmount = Money::of($usage->usage_amount);
        $total = Money::of($usage->calculated_amount);
        $month = $usage->usage_period->translatedFormat('F Y');

        $lines = [
            [
                'component_type' => 'water_base_fee',
                'source_type' => 'water_usage_record',
                'source_id' => $usage->getKey(),
                'description' => "Abodemen air {$month}",
                'quantity' => '1',
                'unit_price' => $base,
                'snapshot' => $snapshot,
            ],
            [
                'component_type' => 'water_usage',
                'source_type' => 'water_usage_record',
                'source_id' => $usage->getKey(),
                'description' => 'Pemakaian air '.Money::formatQuantity($usage->previous_value).' → '.Money::formatQuantity($usage->current_value).' ('.Money::formatQuantity($usage->usage_volume).' m³)',
                'quantity' => (string) $usage->usage_volume,
                'unit_price' => Money::of($usage->rate_per_unit),
                'subtotal' => $usageAmount,
                'snapshot' => $snapshot,
            ],
        ];

        $adjustment = $total->minus($base)->minus($usageAmount);
        if (! $adjustment->isZero()) {
            $lines[] = [
                'component_type' => 'water_minimum',
                'source_type' => 'water_usage_record',
                'source_id' => $usage->getKey(),
                'description' => 'Penyesuaian tagihan minimum',
                'quantity' => '1',
                'unit_price' => $adjustment,
                'snapshot' => $snapshot,
            ];
        }

        return $lines;
    }
}

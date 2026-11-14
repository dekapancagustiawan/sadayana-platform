<?php

namespace App\Services\Reporting;

use App\Enums\Billing\InvoiceStatus;
use App\Enums\Finance\CashTransactionStatus;
use App\Models\BillingPeriod;
use App\Models\CashAccount;
use App\Models\CashTransaction;
use App\Models\House;
use App\Models\Invoice;
use App\Models\WaterUsageRecord;
use App\Services\Finance\CashService;
use App\Support\Money;
use Brick\Math\BigDecimal;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Laporan (FR-RPT-001..003, BRD §8, S-6..S-9). Semua angka dibaca dari
 * invoice / usage / buku kas — tidak ada angka yang disimpan terpisah.
 */
class ReportService
{
    public function __construct(private readonly CashService $cash) {}

    /**
     * "Daftar Tagihan Air & Iuran Warga" per bulan, satu baris per rumah
     * (format sampel daftar-tagihan, S-9) + tunggakan bulan sebelumnya (S-6).
     *
     * @return array{rows: Collection<int, array<string, mixed>>, contributionNames: Collection<string, string>, totals: array<string, BigDecimal>}
     */
    public function billingList(CarbonInterface $month): array
    {
        $period = BillingPeriod::where('period_code', $month->format('Y-m'))->first();

        $invoices = $period ? Invoice::with('contributionType')
            ->where('billing_period_id', $period->id)
            ->whereNotIn('status', [InvoiceStatus::Cancelled, InvoiceStatus::Draft])
            ->get()->groupBy('house_id') : collect();

        $usages = WaterUsageRecord::whereDate('usage_period', $month->copy()->startOfMonth()->toDateString())->get()->keyBy('house_id');

        $arrears = Invoice::query()
            ->whereIn('status', [InvoiceStatus::Issued, InvoiceStatus::Overdue, InvoiceStatus::PartiallyPaid])
            ->whereHas('period', fn ($q) => $q->whereDate('period_start', '<', $month->copy()->startOfMonth()->toDateString()))
            ->selectRaw('house_id, SUM(outstanding_amount) as total')
            ->groupBy('house_id')
            ->pluck('total', 'house_id');

        $contributionNames = $invoices->flatten()->filter(fn ($i) => $i->contribution_type_id)
            ->mapWithKeys(fn ($i) => [$i->contribution_type_id => $i->contributionType?->name ?? 'Iuran'])->sort();

        $houses = House::with(['activeOccupancies.resident'])->orderBy('house_code')->get();
        $totals = ['water' => Money::zero(), 'base' => Money::zero(), 'waste' => Money::zero(), 'contributions' => Money::zero(), 'arrears' => Money::zero(), 'current' => Money::zero(), 'grand' => Money::zero(), 'outstanding' => Money::zero()];

        $rows = $houses->map(function (House $house) use ($invoices, $usages, $arrears, $contributionNames, &$totals) {
            $houseInvoices = $invoices->get($house->id, collect());
            $usage = $usages->get($house->id);
            $water = $houseInvoices->firstWhere('invoice_type.value', 'water');
            $waste = $houseInvoices->firstWhere('invoice_type.value', 'waste');

            $contributions = $contributionNames->keys()->mapWithKeys(fn ($id) => [
                $id => $houseInvoices->firstWhere('contribution_type_id', $id)?->total_amount,
            ]);

            $current = Money::sum($houseInvoices->pluck('total_amount'));
            $arrear = Money::of($arrears[$house->id] ?? 0);
            $outstanding = Money::sum($houseInvoices->pluck('outstanding_amount'))->plus($arrear);

            $waterUsageAmount = $water ? Money::of($water->total_amount)->minus(Money::of($usage?->base_fee ?? 0)) : null;

            $totals['water'] = $totals['water']->plus($waterUsageAmount ?? Money::zero());
            $totals['base'] = $totals['base']->plus($water ? Money::of($usage?->base_fee ?? 0) : Money::zero());
            $totals['waste'] = $totals['waste']->plus(Money::of($waste?->total_amount ?? 0));
            $totals['contributions'] = $totals['contributions']->plus(Money::sum($contributions->filter()));
            $totals['arrears'] = $totals['arrears']->plus($arrear);
            $totals['current'] = $totals['current']->plus($current);
            $totals['grand'] = $totals['grand']->plus($current)->plus($arrear);
            $totals['outstanding'] = $totals['outstanding']->plus($outstanding);

            return [
                'house' => $house,
                'name' => $house->primaryResident()?->full_name,
                'usage' => $usage,
                'water_usage_amount' => $waterUsageAmount,
                'water_base_fee' => $water ? Money::of($usage?->base_fee ?? 0) : null,
                'waste' => $waste ? Money::of($waste->total_amount) : null,
                'contributions' => $contributions,
                'current' => $current,
                'arrears' => $arrear,
                'grand' => $current->plus($arrear),
                'outstanding' => $outstanding,
                'paid' => $houseInvoices->isNotEmpty() && $houseInvoices->every(fn ($i) => $i->status === InvoiceStatus::Paid),
                'has_invoices' => $houseInvoices->isNotEmpty(),
            ];
        });

        return ['rows' => $rows, 'contributionNames' => $contributionNames, 'totals' => $totals];
    }

    /**
     * Rumah yang menunggak (BRD §8, S-7: ≥ 3 bulan → putus sementara).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function arrears(): Collection
    {
        $open = Invoice::with(['house.activeOccupancies.resident', 'period'])
            ->whereIn('status', [InvoiceStatus::Issued, InvoiceStatus::Overdue, InvoiceStatus::PartiallyPaid])
            ->where('outstanding_amount', '>', 0)
            ->get()
            ->groupBy('house_id');

        $threshold = config('billing.disconnect_after_months');

        return $open->map(function (Collection $invoices) use ($threshold) {
            $house = $invoices->first()->house;
            $months = $invoices->filter(fn ($i) => $i->status === InvoiceStatus::Overdue)->pluck('billing_period_id')->unique()->count();

            return [
                'house' => $house,
                'name' => $house->primaryResident()?->full_name,
                'invoices' => $invoices->count(),
                'overdue_months' => $months,
                'oldest' => $invoices->min(fn ($i) => $i->period->period_start),
                'total' => Money::sum($invoices->pluck('outstanding_amount')),
                'flag' => $months >= $threshold,
            ];
        })->sortByDesc(fn ($r) => [$r['overdue_months'], (string) $r['total']])->values();
    }

    /**
     * Buku kas satu akun satu bulan: saldo awal, mutasi, saldo akhir (S-8).
     *
     * @return array{opening: BigDecimal, rows: Collection<int, array{tx: CashTransaction, balance: BigDecimal}>, closing: BigDecimal, in: BigDecimal, out: BigDecimal}
     */
    public function cashBook(CashAccount $account, CarbonInterface $month): array
    {
        $opening = $this->cash->balance($account, $month->copy()->startOfMonth()->subDay());
        $running = $opening;

        $transactions = CashTransaction::with('category')
            ->where('cash_account_id', $account->id)
            ->where('status', CashTransactionStatus::Posted)
            ->whereBetween('transaction_date', [$month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString()])
            ->orderBy('transaction_date')->orderBy('created_at')
            ->get();

        $rows = $transactions->map(function (CashTransaction $tx) use (&$running) {
            $running = $tx->transaction_type === 'in' ? $running->plus(Money::of($tx->amount)) : $running->minus(Money::of($tx->amount));

            return ['tx' => $tx, 'balance' => $running];
        });

        return [
            'opening' => $opening,
            'rows' => $rows,
            'closing' => $running,
            'in' => Money::sum($transactions->where('transaction_type', 'in')->pluck('amount')),
            'out' => Money::sum($transactions->where('transaction_type', 'out')->pluck('amount')),
        ];
    }

    /**
     * Rekap per bulan dalam setahun: pemasukan/pengeluaran per kategori dan
     * pemasukan tagihan per jenis (FR-FIN-008, FR-RPT-003).
     *
     * @return array{months: list<Carbon>, categories: Collection<int, array<string, mixed>>, byType: Collection<string, array<string, BigDecimal>>}
     */
    public function yearlyRecap(int $year): array
    {
        $months = collect(range(1, 12))->map(fn ($m) => Carbon::create($year, $m, 1))->all();
        $monthKey = DB::getDriverName() === 'pgsql' ? "to_char(transaction_date, 'YYYY-MM')" : "strftime('%Y-%m', transaction_date)";

        $categories = CashTransaction::query()
            ->join('finance_categories', 'finance_categories.id', '=', 'cash_transactions.finance_category_id')
            ->where('cash_transactions.status', CashTransactionStatus::Posted->value)
            ->whereYear('transaction_date', $year)
            ->selectRaw("finance_categories.name as category, cash_transactions.transaction_type as type, {$monthKey} as month, SUM(amount) as total")
            ->groupBy('finance_categories.name', 'cash_transactions.transaction_type', DB::raw($monthKey))
            ->get()
            ->groupBy(fn ($r) => $r->type.'|'.$r->category)
            ->map(fn ($rows, $key) => [
                'type' => explode('|', $key)[0],
                'category' => explode('|', $key)[1],
                'months' => $rows->mapWithKeys(fn ($r) => [$r->month => Money::of($r->total)]),
                'total' => Money::sum($rows->pluck('total')),
            ])->sortBy(fn ($r) => $r['type'].$r['category'])->values();

        $paymentMonth = DB::getDriverName() === 'pgsql' ? "to_char(payments.payment_date, 'YYYY-MM')" : "strftime('%Y-%m', payments.payment_date)";
        $byType = DB::table('payment_allocations')
            ->join('payments', 'payments.id', '=', 'payment_allocations.payment_id')
            ->join('invoices', 'invoices.id', '=', 'payment_allocations.invoice_id')
            ->where('payments.status', 'verified')
            ->whereYear('payments.payment_date', $year)
            ->selectRaw("invoices.invoice_type as type, {$paymentMonth} as month, SUM(payment_allocations.allocated_amount) as total")
            ->groupBy('invoices.invoice_type', DB::raw($paymentMonth))
            ->get()
            ->groupBy('type')
            ->map(fn ($rows) => $rows->mapWithKeys(fn ($r) => [$r->month => Money::of($r->total)])->all());

        return ['months' => $months, 'categories' => $categories, 'byType' => $byType];
    }
}

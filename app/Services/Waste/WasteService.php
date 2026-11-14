<?php

namespace App\Services\Waste;

use App\Models\House;
use App\Models\Invoice;
use App\Models\User;
use App\Models\WasteFeePeriod;
use App\Models\WasteServiceRecord;
use App\Models\WasteSubscription;
use App\Services\Audit\AuditService;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Layanan sampah (FR-WST-001..004, S-3): langganan per rumah, nominal
 * bulanan berperiode (Rp30.000), dan catatan pengambilan mingguan.
 */
class WasteService
{
    public const STATUSES = [
        'completed' => 'Diambil',
        'missed' => 'Terlewat',
        'not_served' => 'Tidak dilayani',
        'rescheduled' => 'Dijadwal ulang',
    ];

    public function __construct(private readonly AuditService $audit) {}

    public function openSubscription(House $house): ?WasteSubscription
    {
        return WasteSubscription::where('house_id', $house->getKey())->whereNull('end_date')->first();
    }

    public function subscribe(House $house, string $startDate, ?string $notes = null): WasteSubscription
    {
        return DB::transaction(function () use ($house, $startDate, $notes) {
            House::whereKey($house->getKey())->lockForUpdate()->first();

            if ($this->openSubscription($house)) {
                throw new RuntimeException("Rumah {$house->house_code} sudah berlangganan layanan sampah.");
            }

            $subscription = WasteSubscription::create([
                'house_id' => $house->getKey(),
                'start_date' => $startDate,
                'status' => 'active',
                'notes' => $notes,
            ]);
            $this->audit->created('waste.subscribed', $subscription, ['house_code' => $house->house_code]);

            return $subscription;
        });
    }

    public function unsubscribe(WasteSubscription $subscription, string $endDate, ?string $reason = null): WasteSubscription
    {
        if ($subscription->end_date !== null) {
            throw new RuntimeException('Langganan sampah ini sudah berakhir.');
        }

        if (Carbon::parse($endDate)->lt($subscription->start_date)) {
            throw new RuntimeException('Tanggal berhenti tidak boleh sebelum tanggal mulai.');
        }

        return DB::transaction(function () use ($subscription, $endDate, $reason) {
            $subscription->fill(['end_date' => $endDate, 'status' => 'inactive']);
            $this->audit->changed('waste.unsubscribed', $subscription, $reason);
            $subscription->save();

            return $subscription;
        });
    }

    /**
     * Langganan aktif yang mencakup bulan tertentu.
     *
     * @return Collection<int, WasteSubscription>
     */
    public function subscriptionsFor(CarbonInterface $month)
    {
        $start = $month->copy()->startOfMonth()->toDateString();
        $end = $month->copy()->endOfMonth()->toDateString();

        return WasteSubscription::query()
            ->whereDate('start_date', '<=', $end)
            ->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', $start))
            ->whereHas('house', fn ($q) => $q->where('is_active', true))
            ->get();
    }

    public function feeFor(CarbonInterface $month): ?WasteFeePeriod
    {
        $date = $month->copy()->startOfMonth()->toDateString();

        return WasteFeePeriod::query()
            ->whereDate('effective_from', '<=', $date)
            ->where(fn ($q) => $q->whereNull('effective_until')->orWhereDate('effective_until', '>=', $date))
            ->orderByDesc('effective_from')
            ->first();
    }

    /**
     * Nominal baru = periode baru; tidak boleh mundur ke bulan yang sudah
     * punya tagihan sampah (FR-WST-004).
     */
    public function addFeePeriod(string $effectiveFrom, string|int $amount, ?string $notes = null): WasteFeePeriod
    {
        $from = Carbon::parse($effectiveFrom)->startOfMonth();

        return DB::transaction(function () use ($from, $amount, $notes) {
            $latest = WasteFeePeriod::orderByDesc('effective_from')->lockForUpdate()->first();

            if ($latest && $from->lte($latest->effective_from)) {
                throw new RuntimeException('Tanggal berlaku harus setelah '.$latest->effective_from->format('d/m/Y').'.');
            }

            $billed = Invoice::where('invoice_type', 'waste')
                ->whereHas('period', fn ($q) => $q->whereDate('period_start', '>=', $from->toDateString()))
                ->where('status', '!=', 'cancelled')
                ->exists();
            if ($billed) {
                throw new RuntimeException('Sudah ada tagihan sampah untuk '.$from->translatedFormat('F Y').' atau sesudahnya.');
            }

            if ($latest) {
                $latest->update(['effective_until' => $from->copy()->subDay()->toDateString()]);
            }

            $period = WasteFeePeriod::create([
                'effective_from' => $from->toDateString(),
                'monthly_amount' => Money::toDb($amount),
                'notes' => $notes,
            ]);
            $this->audit->created('waste.fee_period_added', $period);

            return $period;
        });
    }

    /**
     * Catat satu putaran pengambilan sampah (FR-WST-003).
     *
     * @param  array<string, string>  $statuses  house_id => status
     */
    public function recordCollection(string $date, array $statuses, User $officer, ?string $notes = null): int
    {
        $statuses = array_filter($statuses, fn ($status) => array_key_exists($status, self::STATUSES));

        return DB::transaction(function () use ($date, $statuses, $officer, $notes) {
            $count = 0;

            foreach ($statuses as $houseId => $status) {
                WasteServiceRecord::updateOrCreate(
                    ['house_id' => $houseId, 'service_date' => $date],
                    ['status' => $status, 'recorded_by' => $officer->getKey(), 'notes' => $notes],
                );
                $count++;
            }

            $this->audit->record('waste.collection_recorded', null, ['date' => $date, 'houses' => $count]);

            return $count;
        });
    }
}

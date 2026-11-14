<?php

namespace App\Services\Water;

use App\Models\WaterTariff;
use App\Models\WaterTariffPeriod;
use App\Models\WaterUsageRecord;
use App\Services\Audit\AuditService;
use App\Support\Money;
use Brick\Math\BigDecimal;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Tarif air flat berperiode (FR-WAT-005, Q4, S-1).
 *
 *   tagihan = abodemen (base_fee) + tarif × m³, minimal `minimum_charge` (bila diisi)
 *
 * Tarif historis tidak diubah: tarif baru = periode baru yang menutup periode
 * sebelumnya. Periode yang sudah dipakai menghitung pemakaian dikunci.
 */
class WaterTariffService
{
    public function __construct(private readonly AuditService $audit) {}

    /**
     * Tarif utama komunitas (mode satu tarif untuk semua rumah).
     */
    public function defaultTariff(): ?WaterTariff
    {
        return WaterTariff::where('status', 'active')->orderBy('created_at')->first();
    }

    public function ensureDefaultTariff(): WaterTariff
    {
        return $this->defaultTariff() ?? WaterTariff::create([
            'code' => 'RT',
            'name' => 'Tarif Rumah Tangga',
            'calculation_method' => 'flat',
            'unit_name' => 'm³',
            'status' => 'active',
        ]);
    }

    /**
     * Periode tarif yang berlaku untuk bulan pemakaian tertentu.
     */
    public function periodFor(CarbonInterface $usagePeriod): ?WaterTariffPeriod
    {
        $tariff = $this->defaultTariff();
        if (! $tariff) {
            return null;
        }

        $date = $usagePeriod->copy()->startOfMonth()->toDateString();

        return WaterTariffPeriod::query()
            ->where('water_tariff_id', $tariff->getKey())
            ->whereDate('effective_from', '<=', $date)
            ->where(fn ($q) => $q->whereNull('effective_until')->orWhereDate('effective_until', '>=', $date))
            ->orderByDesc('effective_from')
            ->first();
    }

    /**
     * Tambah periode tarif baru; periode terbuka sebelumnya ditutup sehari
     * sebelum tanggal berlaku yang baru.
     *
     * @param  array{effective_from: string, base_fee: string|int, rate_per_unit: string|int, minimum_charge?: string|int|null, notes?: ?string}  $data
     */
    public function addPeriod(array $data, ?WaterTariff $tariff = null): WaterTariffPeriod
    {
        $tariff ??= $this->ensureDefaultTariff();
        $from = Carbon::parse($data['effective_from'])->startOfDay();

        return DB::transaction(function () use ($tariff, $from, $data) {
            WaterTariff::whereKey($tariff->getKey())->lockForUpdate()->first();

            $latest = WaterTariffPeriod::where('water_tariff_id', $tariff->getKey())
                ->orderByDesc('effective_from')
                ->first();

            if ($latest && $from->lte($latest->effective_from)) {
                throw new RuntimeException('Tanggal berlaku harus setelah '.$latest->effective_from->format('d/m/Y').' (periode tarif terakhir).');
            }

            $calculated = WaterUsageRecord::query()
                ->whereHas('house') // batasi ke komunitas aktif lewat scope House
                ->whereDate('usage_period', '>=', $from->copy()->startOfMonth()->toDateString())
                ->exists();
            if ($calculated) {
                throw new RuntimeException('Sudah ada pemakaian air yang dihitung untuk bulan '.$from->translatedFormat('F Y').' atau sesudahnya. Tarif baru hanya bisa berlaku untuk bulan yang belum dihitung.');
            }

            if ($latest) {
                $old = $latest->only(['effective_until']);
                $latest->effective_until = $from->copy()->subDay()->toDateString();
                $this->audit->changed('water_tariffs.period_closed', $latest, null, $old);
                $latest->save();
            }

            $period = WaterTariffPeriod::create([
                'water_tariff_id' => $tariff->getKey(),
                'effective_from' => $from->toDateString(),
                'base_fee' => Money::toDb($data['base_fee']),
                'rate_per_unit' => Money::toDb($data['rate_per_unit']),
                'minimum_charge' => filled($data['minimum_charge'] ?? null) ? Money::toDb($data['minimum_charge']) : null,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->audit->created('water_tariffs.period_added', $period);

            return $period;
        });
    }

    /**
     * Hitung tagihan air untuk volume tertentu.
     *
     * @return array{base_fee: BigDecimal, usage_amount: BigDecimal, total: BigDecimal, minimum_applied: bool}
     */
    public function calculate(WaterTariffPeriod $period, BigDecimal|string $volume): array
    {
        $volume = Money::quantity($volume);
        $base = Money::of($period->base_fee);
        $usage = Money::of(BigDecimal::of($period->rate_per_unit)->multipliedBy($volume));
        $total = $base->plus($usage);
        $minimumApplied = false;

        if ($period->minimum_charge !== null && $total->isLessThan(Money::of($period->minimum_charge))) {
            $total = Money::of($period->minimum_charge);
            $minimumApplied = true;
        }

        return [
            'base_fee' => $base,
            'usage_amount' => $usage,
            'total' => Money::of($total),
            'minimum_applied' => $minimumApplied,
        ];
    }
}

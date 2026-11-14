<?php

namespace App\Services\Water;

use App\Enums\Water\MeterReadingStatus;
use App\Models\WaterCustomer;
use App\Models\WaterMeter;
use App\Models\WaterMeterReading;
use App\Models\WaterUsageRecord;
use App\Support\Money;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonInterface;
use RuntimeException;

/**
 * Perhitungan pemakaian air (FR-WAT-004):
 *
 *   pemakaian = angka bulan ini − angka sebelumnya (+ sisa meter lama bila
 *               ini pembacaan pertama meter pengganti)
 *
 * "Angka sebelumnya" = pembacaan VALID terakhir di meter yang sama, atau
 * angka awal meter bila belum ada. Hasil + snapshot tarif disimpan di
 * water_usage_records dan tidak dihitung ulang setelah ditagih.
 */
class WaterUsageService
{
    public function __construct(private readonly WaterTariffService $tariffs) {}

    /**
     * Pembacaan valid terakhir sebelum bulan tertentu (null = belum ada).
     */
    public function previousReading(WaterMeter $meter, CarbonInterface $period, ?string $exceptId = null): ?WaterMeterReading
    {
        return WaterMeterReading::query()
            ->where('water_meter_id', $meter->getKey())
            ->where('status', MeterReadingStatus::Validated)
            ->whereDate('reading_period', '<', $period->copy()->startOfMonth()->toDateString())
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))
            ->orderByDesc('reading_period')
            ->first();
    }

    /**
     * Angka pembanding untuk pembacaan baru.
     *
     * @return array{reading: ?WaterMeterReading, value: BigDecimal, carried: BigDecimal}
     */
    public function baseline(WaterMeter $meter, CarbonInterface $period, ?string $exceptId = null): array
    {
        $previous = $this->previousReading($meter, $period, $exceptId);

        return [
            'reading' => $previous,
            'value' => Money::quantity($previous?->reading_value ?? $meter->initial_reading),
            // Sisa meter lama hanya dibebankan sekali, di pembacaan pertama.
            'carried' => $previous === null ? Money::quantity($meter->carried_volume ?? 0) : Money::quantity(0),
        ];
    }

    /**
     * Rata-rata pemakaian 3 bulan terakhir rumah ini (untuk deteksi lonjakan).
     */
    public function recentAverage(string $houseId, CarbonInterface $before): ?BigDecimal
    {
        $volumes = WaterUsageRecord::query()
            ->where('house_id', $houseId)
            ->whereDate('usage_period', '<', $before->copy()->startOfMonth()->toDateString())
            ->orderByDesc('usage_period')
            ->limit(3)
            ->pluck('usage_volume');

        if ($volumes->isEmpty()) {
            return null;
        }

        $sum = $volumes->reduce(fn (BigDecimal $carry, $v) => $carry->plus(BigDecimal::of($v)), BigDecimal::zero());

        return $sum->dividedBy($volumes->count(), 3, RoundingMode::HalfUp);
    }

    /**
     * Buat atau perbarui usage record untuk pembacaan tervalidasi.
     */
    public function calculateFor(WaterMeterReading $reading): WaterUsageRecord
    {
        if ($reading->status !== MeterReadingStatus::Validated) {
            throw new RuntimeException('Pemakaian hanya dihitung dari pembacaan yang valid.');
        }

        $existing = WaterUsageRecord::where('current_reading_id', $reading->getKey())->first();
        if ($existing?->isBilled()) {
            throw new RuntimeException('Pemakaian bulan ini sudah ditagihkan dan tidak bisa dihitung ulang.');
        }

        $meter = $reading->meter;
        $period = $reading->reading_period;

        $customer = WaterCustomer::query()
            ->where('house_id', $reading->house_id)
            ->whereDate('start_date', '<=', $period->copy()->endOfMonth()->toDateString())
            ->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', $period->toDateString()))
            ->orderByDesc('start_date')
            ->first();
        if (! $customer) {
            throw new RuntimeException('Rumah ini bukan pelanggan air pada '.$period->translatedFormat('F Y').'.');
        }

        $tariffPeriod = $this->tariffs->periodFor($period);
        if (! $tariffPeriod) {
            throw new RuntimeException('Tarif air untuk '.$period->translatedFormat('F Y').' belum diatur. Hubungi bendahara.');
        }

        $baseline = $this->baseline($meter, $period, $reading->getKey());
        $current = Money::quantity($reading->reading_value);
        $volume = $current->minus($baseline['value'])->plus($baseline['carried']);

        if ($volume->isNegative()) {
            throw new RuntimeException("Angka meter ({$current}) lebih kecil dari angka sebelumnya ({$baseline['value']}). Tolak atau koreksi angkanya.");
        }

        $amounts = $this->tariffs->calculate($tariffPeriod, $volume);

        $attributes = [
            'water_customer_id' => $customer->getKey(),
            'house_id' => $reading->house_id,
            'water_meter_id' => $meter->getKey(),
            'usage_period' => $period->toDateString(),
            'previous_reading_id' => $baseline['reading']?->getKey(),
            'current_reading_id' => $reading->getKey(),
            'previous_value' => (string) $baseline['value'],
            'current_value' => (string) $current,
            'usage_volume' => (string) $volume,
            'tariff_period_id' => $tariffPeriod->getKey(),
            'base_fee' => (string) $amounts['base_fee'],
            'rate_per_unit' => Money::toDb($tariffPeriod->rate_per_unit),
            'usage_amount' => (string) $amounts['usage_amount'],
            'calculated_amount' => (string) $amounts['total'],
            'calculation_snapshot' => [
                'formula' => 'abodemen + tarif × pemakaian',
                'previous_value' => (string) $baseline['value'],
                'current_value' => (string) $current,
                'carried_volume' => (string) $baseline['carried'],
                'usage_volume' => (string) $volume,
                'tariff_period_id' => $tariffPeriod->getKey(),
                'tariff_effective_from' => $tariffPeriod->effective_from->toDateString(),
                'base_fee' => (string) $amounts['base_fee'],
                'rate_per_unit' => Money::toDb($tariffPeriod->rate_per_unit),
                'minimum_charge' => $tariffPeriod->minimum_charge,
                'minimum_applied' => $amounts['minimum_applied'],
                'total' => (string) $amounts['total'],
            ],
        ];

        if ($existing) {
            $existing->update($attributes);

            return $existing;
        }

        return WaterUsageRecord::create($attributes);
    }

    /**
     * Pembacaan valid berikutnya di meter yang sama (terdampak bila angka
     * bulan ini dikoreksi).
     */
    public function nextReading(WaterMeterReading $reading): ?WaterMeterReading
    {
        return WaterMeterReading::query()
            ->where('water_meter_id', $reading->water_meter_id)
            ->where('status', MeterReadingStatus::Validated)
            ->whereDate('reading_period', '>', $reading->reading_period->toDateString())
            ->orderBy('reading_period')
            ->first();
    }
}

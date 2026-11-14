<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Hasil hitung pemakaian air satu bulan (FR-WAT-004) beserta snapshot tarif
 * (BR-006). Setelah `billed_at` terisi (Fase 5) record ini tidak boleh
 * berubah — koreksi lewat penyesuaian tagihan.
 */
class WaterUsageRecord extends Model
{
    use HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'water_customer_id', 'house_id', 'water_meter_id', 'usage_period',
        'previous_reading_id', 'current_reading_id', 'previous_value', 'current_value',
        'usage_volume', 'tariff_period_id', 'base_fee', 'rate_per_unit',
        'usage_amount', 'calculated_amount', 'calculation_snapshot', 'billed_at',
    ];

    protected function casts(): array
    {
        return [
            'usage_period' => 'date',
            'previous_value' => 'decimal:3',
            'current_value' => 'decimal:3',
            'usage_volume' => 'decimal:3',
            'base_fee' => 'decimal:2',
            'rate_per_unit' => 'decimal:2',
            'usage_amount' => 'decimal:2',
            'calculated_amount' => 'decimal:2',
            'calculation_snapshot' => 'array',
            'billed_at' => 'datetime',
        ];
    }

    public function isBilled(): bool
    {
        return $this->billed_at !== null;
    }

    /**
     * @return BelongsTo<House, $this>
     */
    public function house(): BelongsTo
    {
        return $this->belongsTo(House::class)->withTrashed();
    }

    /**
     * @return BelongsTo<WaterCustomer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(WaterCustomer::class, 'water_customer_id');
    }

    /**
     * @return BelongsTo<WaterMeterReading, $this>
     */
    public function currentReading(): BelongsTo
    {
        return $this->belongsTo(WaterMeterReading::class, 'current_reading_id');
    }

    /**
     * @return BelongsTo<WaterMeterReading, $this>
     */
    public function previousReading(): BelongsTo
    {
        return $this->belongsTo(WaterMeterReading::class, 'previous_reading_id');
    }

    /**
     * @return BelongsTo<WaterTariffPeriod, $this>
     */
    public function tariffPeriod(): BelongsTo
    {
        return $this->belongsTo(WaterTariffPeriod::class, 'tariff_period_id');
    }
}

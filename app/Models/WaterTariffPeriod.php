<?php

namespace App\Models;

use App\Traits\Blameable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Nominal tarif air yang berlaku pada rentang tanggal (FR-WAT-005).
 * Periode yang sudah dipakai menghitung pemakaian tidak boleh diubah —
 * tarif baru = periode baru (WaterTariffService).
 */
class WaterTariffPeriod extends Model
{
    use Blameable, HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'water_tariff_id', 'effective_from', 'effective_until',
        'base_fee', 'rate_per_unit', 'minimum_charge', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_until' => 'date',
            'base_fee' => 'decimal:2',
            'rate_per_unit' => 'decimal:2',
            'minimum_charge' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<WaterTariff, $this>
     */
    public function tariff(): BelongsTo
    {
        return $this->belongsTo(WaterTariff::class, 'water_tariff_id');
    }

    public function isUsed(): bool
    {
        return WaterUsageRecord::where('tariff_period_id', $this->getKey())->exists();
    }
}

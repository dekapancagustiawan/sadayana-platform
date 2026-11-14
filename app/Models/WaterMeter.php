<?php

namespace App\Models;

use App\Enums\Water\WaterMeterStatus;
use App\Traits\Blameable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Meteran air fisik di rumah (FR-WAT-001, D-02). Pemakaian selalu dihitung
 * per meter; saat meter diganti, sisa pemakaian meter lama dibawa ke meter
 * baru lewat `carried_volume`.
 */
class WaterMeter extends Model
{
    use Blameable, HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'house_id', 'meter_number', 'installed_at', 'removed_at',
        'initial_reading', 'final_reading', 'carried_volume', 'status', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => WaterMeterStatus::class,
            'installed_at' => 'date',
            'removed_at' => 'date',
            'initial_reading' => 'decimal:3',
            'final_reading' => 'decimal:3',
            'carried_volume' => 'decimal:3',
        ];
    }

    /**
     * @return BelongsTo<House, $this>
     */
    public function house(): BelongsTo
    {
        return $this->belongsTo(House::class)->withTrashed();
    }

    /**
     * @return HasMany<WaterMeterReading, $this>
     */
    public function readings(): HasMany
    {
        return $this->hasMany(WaterMeterReading::class);
    }
}

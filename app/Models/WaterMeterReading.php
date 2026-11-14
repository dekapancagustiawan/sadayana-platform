<?php

namespace App\Models;

use App\Enums\Water\MeterReadingStatus;
use App\Enums\Water\ReadingAnomaly;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Pembacaan meter bulanan (FR-WAT-002). `reading_period` = tanggal 1 bulan
 * pemakaian. Tidak punya global scope komunitas — selalu akses lewat House
 * (yang ter-scope) atau batasi dengan whereHas('house').
 */
class WaterMeterReading extends Model
{
    use HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'water_meter_id', 'house_id', 'reading_period', 'reading_value', 'reading_date',
        'photo_file_id', 'recorded_by', 'status', 'anomalies',
        'validated_by', 'validated_at', 'rejection_reason', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'reading_period' => 'date',
            'reading_value' => 'decimal:3',
            'reading_date' => 'datetime',
            'status' => MeterReadingStatus::class,
            'anomalies' => 'array',
            'validated_at' => 'datetime',
        ];
    }

    /**
     * @return list<ReadingAnomaly>
     */
    public function anomalyList(): array
    {
        return array_values(array_filter(array_map(
            fn ($value) => ReadingAnomaly::tryFrom((string) $value),
            $this->anomalies ?? [],
        )));
    }

    /**
     * @return BelongsTo<WaterMeter, $this>
     */
    public function meter(): BelongsTo
    {
        return $this->belongsTo(WaterMeter::class, 'water_meter_id');
    }

    /**
     * @return BelongsTo<House, $this>
     */
    public function house(): BelongsTo
    {
        return $this->belongsTo(House::class);
    }

    /**
     * @return BelongsTo<StoredFile, $this>
     */
    public function photo(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'photo_file_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    /**
     * @return HasOne<WaterUsageRecord, $this>
     */
    public function usage(): HasOne
    {
        return $this->hasOne(WaterUsageRecord::class, 'current_reading_id');
    }

    /**
     * @return HasMany<WaterReadingCorrection, $this>
     */
    public function corrections(): HasMany
    {
        return $this->hasMany(WaterReadingCorrection::class)->latest();
    }
}

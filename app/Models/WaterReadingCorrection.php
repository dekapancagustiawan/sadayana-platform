<?php

namespace App\Models;

use App\Enums\Water\CorrectionStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Permintaan koreksi angka meter (FR-WAT-006). Nilai lama tetap tersimpan
 * di sini walaupun pembacaannya sudah diperbarui.
 */
class WaterReadingCorrection extends Model
{
    use HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'water_meter_reading_id', 'old_value', 'new_value', 'reason', 'photo_file_id',
        'status', 'requested_by', 'decided_by', 'decided_at', 'decision_notes',
    ];

    protected function casts(): array
    {
        return [
            'old_value' => 'decimal:3',
            'new_value' => 'decimal:3',
            'status' => CorrectionStatus::class,
            'decided_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<WaterMeterReading, $this>
     */
    public function reading(): BelongsTo
    {
        return $this->belongsTo(WaterMeterReading::class, 'water_meter_reading_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}

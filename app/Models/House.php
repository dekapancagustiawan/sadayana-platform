<?php

namespace App\Models;

use App\Enums\Resident\HouseResidentStatus;
use App\Enums\Resident\OccupancyStatus;
use App\Traits\BelongsToCommunity;
use App\Traits\Blameable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Rumah / unit hunian (FR-RES-002). `house_code` = "{blok}-{nomor}" dan
 * dibentuk oleh HouseService — jangan diisi manual.
 */
class House extends Model
{
    use BelongsToCommunity, Blameable, HasUuids, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'block_id',
        'house_number',
        'house_code',
        'address',
        'house_type',
        'area_size',
        'occupancy_status',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'occupancy_status' => OccupancyStatus::class,
            'is_active' => 'boolean',
            'area_size' => 'decimal:2',
        ];
    }

    public static function codeFor(string $blockCode, string $houseNumber): string
    {
        return strtoupper(trim($blockCode)).'-'.strtoupper(trim($houseNumber));
    }

    /**
     * @return BelongsTo<Block, $this>
     */
    public function block(): BelongsTo
    {
        return $this->belongsTo(Block::class)->withTrashed();
    }

    /**
     * Seluruh riwayat hunian, terbaru dulu.
     *
     * @return HasMany<HouseResident, $this>
     */
    public function occupancies(): HasMany
    {
        return $this->hasMany(HouseResident::class)->latest('start_date');
    }

    /**
     * @return HasMany<HouseResident, $this>
     */
    public function activeOccupancies(): HasMany
    {
        return $this->hasMany(HouseResident::class)->where('status', HouseResidentStatus::Active);
    }

    /**
     * Kontak utama yang sedang menghuni (untuk daftar & tagihan).
     */
    public function primaryResident(): ?Resident
    {
        $occupancies = $this->relationLoaded('activeOccupancies')
            ? $this->activeOccupancies
            : $this->activeOccupancies()->with('resident')->get();

        return ($occupancies->firstWhere('is_primary_contact', true) ?? $occupancies->first())?->resident;
    }
}

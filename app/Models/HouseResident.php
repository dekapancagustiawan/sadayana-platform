<?php

namespace App\Models;

use App\Enums\Resident\HouseResidentStatus;
use App\Enums\Resident\RelationshipType;
use App\Traits\Blameable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu periode hunian: penghuni X di rumah Y dari start_date s.d. end_date
 * (FR-RES-004). Tidak pernah dihapus — penghuni pindah = end_date diisi.
 */
class HouseResident extends Model
{
    use Blameable, HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'house_id',
        'resident_id',
        'relationship_type',
        'is_primary_contact',
        'start_date',
        'end_date',
        'status',
        'end_reason',
    ];

    protected function casts(): array
    {
        return [
            'relationship_type' => RelationshipType::class,
            'status' => HouseResidentStatus::class,
            'is_primary_contact' => 'boolean',
            'start_date' => 'date',
            'end_date' => 'date',
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
     * @return BelongsTo<Resident, $this>
     */
    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class)->withTrashed();
    }

    public function isActive(): bool
    {
        return $this->status === HouseResidentStatus::Active;
    }
}

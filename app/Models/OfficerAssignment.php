<?php

namespace App\Models;

use App\Enums\Resident\AssignmentType;
use App\Traits\BelongsToCommunity;
use App\Traits\Blameable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Penugasan petugas ke satu blok atau satu rumah (D-05).
 */
class OfficerAssignment extends Model
{
    use BelongsToCommunity, Blameable, HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'assignment_type',
        'block_id',
        'house_id',
        'assigned_from',
        'assigned_until',
    ];

    protected function casts(): array
    {
        return [
            'assignment_type' => AssignmentType::class,
            'assigned_from' => 'date',
            'assigned_until' => 'date',
        ];
    }

    /**
     * Penugasan yang berlaku pada tanggal tertentu (default hari ini).
     *
     * @param  Builder<OfficerAssignment>  $query
     */
    public function scopeCurrent(Builder $query, ?string $date = null): void
    {
        $date ??= today()->toDateString();

        $query->whereDate('assigned_from', '<=', $date)
            ->where(fn (Builder $q) => $q->whereNull('assigned_until')->orWhereDate('assigned_until', '>=', $date));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Block, $this>
     */
    public function block(): BelongsTo
    {
        return $this->belongsTo(Block::class)->withTrashed();
    }

    /**
     * @return BelongsTo<House, $this>
     */
    public function house(): BelongsTo
    {
        return $this->belongsTo(House::class)->withTrashed();
    }

    public function isCurrent(): bool
    {
        return $this->assigned_from->lte(today())
            && ($this->assigned_until === null || $this->assigned_until->gte(today()));
    }
}

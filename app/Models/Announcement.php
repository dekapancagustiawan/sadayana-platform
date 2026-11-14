<?php

namespace App\Models;

use App\Traits\BelongsToCommunity;
use App\Traits\Blameable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pengumuman pengurus (FR-NOT-002).
 */
class Announcement extends Model
{
    use BelongsToCommunity, Blameable, HasUuids;

    /** @var list<string> */
    protected $fillable = ['title', 'content', 'publish_at', 'expires_at', 'status'];

    protected function casts(): array
    {
        return [
            'publish_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @param  Builder<Announcement>  $query
     */
    public function scopeVisible(Builder $query): void
    {
        $query->where('status', 'published')
            ->where('publish_at', '<=', now())
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }
}

<?php

namespace App\Traits;

use App\Models\Community;
use App\Support\CurrentCommunity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Untuk model tingkat atas yang punya kolom `community_id`.
 *
 * - `community_id` diisi otomatis dari CurrentCommunity saat create.
 * - Global scope membatasi query ke komunitas aktif (Dok 06 §16 butir 1).
 *   Gunakan `withoutGlobalScope('community')` hanya untuk proses lintas
 *   komunitas yang disengaja.
 */
trait BelongsToCommunity
{
    public static function bootBelongsToCommunity(): void
    {
        static::creating(function ($model) {
            if (empty($model->community_id)) {
                $model->community_id = app(CurrentCommunity::class)->id();
            }
        });

        static::addGlobalScope('community', function (Builder $query) {
            $query->where(
                $query->getModel()->qualifyColumn('community_id'),
                app(CurrentCommunity::class)->id(),
            );
        });
    }

    /**
     * @return BelongsTo<Community, $this>
     */
    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }
}

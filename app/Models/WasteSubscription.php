<?php

namespace App\Models;

use App\Traits\BelongsToCommunity;
use App\Traits\Blameable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Langganan layanan sampah sebuah rumah (FR-WST-001, S-3: tidak semua rumah ikut).
 */
class WasteSubscription extends Model
{
    use BelongsToCommunity, Blameable, HasUuids;

    protected $table = 'waste_service_subscriptions';

    /** @var list<string> */
    protected $fillable = ['house_id', 'start_date', 'end_date', 'status', 'notes'];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function house(): BelongsTo
    {
        return $this->belongsTo(House::class)->withTrashed();
    }
}

<?php

namespace App\Models;

use App\Traits\BelongsToCommunity;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Catatan pengambilan sampah per rumah per tanggal (FR-WST-003).
 */
class WasteServiceRecord extends Model
{
    use BelongsToCommunity, HasUuids;

    /** @var list<string> */
    protected $fillable = ['house_id', 'service_date', 'status', 'notes', 'recorded_by'];

    protected function casts(): array
    {
        return [
            'service_date' => 'date',
        ];
    }

    public function house(): BelongsTo
    {
        return $this->belongsTo(House::class)->withTrashed();
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}

<?php

namespace App\Models;

use App\Traits\BelongsToCommunity;
use App\Traits\Blameable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pembebasan tagihan rumah sampai tanggal tertentu (S-4, "BM — Belum Menempati").
 */
class BillingExemption extends Model
{
    use BelongsToCommunity, Blameable, HasUuids;

    /** @var list<string> */
    protected $fillable = ['house_id', 'billing_type', 'valid_from', 'valid_until', 'reason'];

    protected function casts(): array
    {
        return [
            'valid_from' => 'date',
            'valid_until' => 'date',
        ];
    }

    public function house(): BelongsTo
    {
        return $this->belongsTo(House::class)->withTrashed();
    }
}

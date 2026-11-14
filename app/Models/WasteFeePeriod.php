<?php

namespace App\Models;

use App\Traits\BelongsToCommunity;
use App\Traits\Blameable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Nominal iuran sampah bulanan berperiode (FR-WST-001/004, S-3: Rp30.000).
 */
class WasteFeePeriod extends Model
{
    use BelongsToCommunity, Blameable, HasUuids;

    /** @var list<string> */
    protected $fillable = ['effective_from', 'effective_until', 'monthly_amount', 'notes'];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_until' => 'date',
            'monthly_amount' => 'decimal:2',
        ];
    }
}

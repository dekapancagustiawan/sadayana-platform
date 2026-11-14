<?php

namespace App\Models;

use App\Traits\Blameable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Nominal iuran berperiode (FR-COM-001). Tagihan lama menyimpan snapshot nominal.
 */
class ContributionPeriod extends Model
{
    use Blameable, HasUuids;

    /** @var list<string> */
    protected $fillable = ['contribution_type_id', 'effective_from', 'effective_until', 'amount', 'notes'];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_until' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(ContributionType::class, 'contribution_type_id');
    }
}

<?php

namespace App\Models;

use App\Traits\Blameable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rumah yang dikenai iuran bertipe `assigned`, opsional dengan nominal khusus.
 */
class ContributionAssignment extends Model
{
    use Blameable, HasUuids;

    /** @var list<string> */
    protected $fillable = ['contribution_type_id', 'house_id', 'assigned_from', 'assigned_until', 'custom_amount'];

    protected function casts(): array
    {
        return [
            'assigned_from' => 'date',
            'assigned_until' => 'date',
            'custom_amount' => 'decimal:2',
        ];
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(ContributionType::class, 'contribution_type_id');
    }

    public function house(): BelongsTo
    {
        return $this->belongsTo(House::class)->withTrashed();
    }
}

<?php

namespace App\Models;

use App\Enums\Resident\BlockStatus;
use App\Traits\BelongsToCommunity;
use App\Traits\Blameable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Blok perumahan (FR-RES-001).
 */
class Block extends Model
{
    use BelongsToCommunity, Blameable, HasUuids, SoftDeletes;

    /** @var list<string> */
    protected $fillable = ['code', 'name', 'description', 'status'];

    protected function casts(): array
    {
        return [
            'status' => BlockStatus::class,
        ];
    }

    /**
     * @return HasMany<House, $this>
     */
    public function houses(): HasMany
    {
        return $this->hasMany(House::class);
    }

    public function label(): string
    {
        return $this->name ? "{$this->code} — {$this->name}" : "Blok {$this->code}";
    }
}

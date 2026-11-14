<?php

namespace App\Models;

use App\Traits\BelongsToCommunity;
use App\Traits\Blameable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Jenis iuran yang dikonfigurasi pengurus (FR-COM-001, Dok 05 §11.2) — mis. Iuran Perelek.
 * routine = ditagih tiap bulan; incidental = sekali pada bulan periode berlaku.
 */
class ContributionType extends Model
{
    use BelongsToCommunity, Blameable, HasUuids;

    /** @var list<string> */
    protected $fillable = ['code', 'name', 'description', 'contribution_category', 'applies_to', 'status'];

    public function periods(): HasMany
    {
        return $this->hasMany(ContributionPeriod::class)->orderByDesc('effective_from');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ContributionAssignment::class);
    }
}

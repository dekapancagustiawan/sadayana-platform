<?php

namespace App\Models;

use App\Traits\BelongsToCommunity;
use App\Traits\Blameable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Master tarif air (FR-WAT-005). Nominal ada di periode-periodenya.
 */
class WaterTariff extends Model
{
    use BelongsToCommunity, Blameable, HasUuids;

    /** @var list<string> */
    protected $fillable = ['code', 'name', 'calculation_method', 'unit_name', 'status'];

    /**
     * @return HasMany<WaterTariffPeriod, $this>
     */
    public function periods(): HasMany
    {
        return $this->hasMany(WaterTariffPeriod::class)->orderByDesc('effective_from');
    }
}

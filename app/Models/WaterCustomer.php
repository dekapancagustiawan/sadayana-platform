<?php

namespace App\Models;

use App\Enums\Water\WaterCustomerStatus;
use App\Traits\BelongsToCommunity;
use App\Traits\Blameable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Langganan air sebuah rumah (FR-WAT-001, S-3: tidak semua rumah ikut).
 * Langganan tidak dihapus; berhenti = end_date diisi + status inactive.
 */
class WaterCustomer extends Model
{
    use BelongsToCommunity, Blameable, HasUuids;

    /** @var list<string> */
    protected $fillable = ['house_id', 'customer_number', 'status', 'start_date', 'end_date', 'notes'];

    protected function casts(): array
    {
        return [
            'status' => WaterCustomerStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    /**
     * Langganan yang berjalan (belum diakhiri).
     *
     * @param  Builder<WaterCustomer>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('end_date');
    }

    /**
     * @return BelongsTo<House, $this>
     */
    public function house(): BelongsTo
    {
        return $this->belongsTo(House::class)->withTrashed();
    }

    /**
     * @return HasMany<WaterUsageRecord, $this>
     */
    public function usageRecords(): HasMany
    {
        return $this->hasMany(WaterUsageRecord::class);
    }

    /**
     * Apakah langganan berlaku pada bulan pemakaian tertentu.
     */
    public function coversPeriod(CarbonInterface $period): bool
    {
        return $this->start_date->lte($period->copy()->endOfMonth())
            && ($this->end_date === null || $this->end_date->gte($period->copy()->startOfMonth()));
    }
}

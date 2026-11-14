<?php

namespace App\Http\Controllers\Resident;

use App\Http\Controllers\Controller;
use App\Models\House;
use App\Models\WaterMeterReading;
use App\Models\WaterUsageRecord;
use App\Services\Resident\HouseAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Portal warga: rumah yang sedang dihuni (FR-RES-005) beserta pemakaian air
 * dan foto meternya (SRS ROLE-006). Data berperiode dibatasi ke rentang
 * hunian warga sendiri lewat HouseAccessService::occupancyPeriods().
 */
class MyHouseController extends Controller
{
    public function __construct(private readonly HouseAccessService $access) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $houses = $this->access->housesFor($user)->load(['activeOccupancies.resident']);

        $waterUsages = $houses->mapWithKeys(fn (House $house) => [
            $house->getKey() => $this->withinOccupancy(
                WaterUsageRecord::query()->where('house_id', $house->getKey()),
                $this->access->occupancyPeriods($user, $house),
            )->orderByDesc('usage_period')->limit(12)->get(),
        ]);

        $photos = WaterMeterReading::query()
            ->whereIn('id', $waterUsages->flatten()->pluck('current_reading_id'))
            ->pluck('photo_file_id', 'id');

        return view('resident.my-house.index', compact('houses', 'waterUsages', 'photos'));
    }

    /**
     * @param  Builder<WaterUsageRecord>  $query
     * @param  Collection<int, array{start: Carbon, end: ?Carbon}>  $periods
     * @return Builder<WaterUsageRecord>
     */
    private function withinOccupancy(Builder $query, $periods): Builder
    {
        return $query->where(function (Builder $q) use ($periods) {
            foreach ($periods as $period) {
                $q->orWhere(function (Builder $q) use ($period) {
                    $q->whereDate('usage_period', '>=', $period['start']->copy()->startOfMonth()->toDateString());
                    if ($period['end']) {
                        $q->whereDate('usage_period', '<=', $period['end']->toDateString());
                    }
                });
            }

            if ($periods->isEmpty()) {
                $q->whereRaw('1 = 0');
            }
        });
    }
}

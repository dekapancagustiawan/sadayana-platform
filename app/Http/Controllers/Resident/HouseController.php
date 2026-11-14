<?php

namespace App\Http\Controllers\Resident;

use App\Enums\Resident\OccupancyStatus;
use App\Enums\Resident\RelationshipType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Resident\HouseRequest;
use App\Models\Block;
use App\Models\House;
use App\Models\Resident;
use App\Models\WaterCustomer;
use App\Models\WaterUsageRecord;
use App\Services\Resident\HouseService;
use App\Services\Water\WaterCustomerService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use RuntimeException;

/**
 * Daftar & detail rumah (FR-RES-002). Lihat: houses.view; ubah: houses.manage.
 */
class HouseController extends Controller
{
    public function __construct(private readonly HouseService $houses) {}

    public function index(Request $request): View
    {
        Gate::authorize('houses.view');

        $search = trim((string) $request->input('search'));

        $houses = House::query()
            ->with(['block', 'activeOccupancies.resident'])
            ->when($request->filled('block'), fn (Builder $q) => $q->where('block_id', $request->input('block')))
            ->when($request->filled('occupancy'), fn (Builder $q) => $q->where('occupancy_status', $request->input('occupancy')))
            ->when($search !== '', function (Builder $q) use ($search) {
                $like = '%'.mb_strtolower($search).'%';
                $q->where(function (Builder $q) use ($like) {
                    $q->whereRaw('LOWER(house_code) LIKE ?', [$like])
                        ->orWhereHas('activeOccupancies.resident', fn (Builder $r) => $r->whereRaw('LOWER(full_name) LIKE ?', [$like]));
                });
            })
            ->orderBy('house_code')
            ->paginate($request->integer('per_page', 25))
            ->withQueryString();

        return view('resident.houses.index', [
            'houses' => $houses,
            'blocks' => Block::orderBy('code')->get(),
            'occupancyStatuses' => OccupancyStatus::cases(),
            'totals' => House::query()->selectRaw('occupancy_status, COUNT(*) as total')->groupBy('occupancy_status')->pluck('total', 'occupancy_status'),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('houses.manage');

        return view('resident.houses.form', [
            'house' => new House(['block_id' => $request->input('block'), 'is_active' => true]),
            'blocks' => Block::orderBy('code')->get(),
        ]);
    }

    public function store(HouseRequest $request): RedirectResponse
    {
        $house = $this->houses->createHouse($request->validated());

        return redirect()->route('houses.show', $house)->with('success', "Rumah {$house->house_code} ditambahkan.");
    }

    public function show(House $house, WaterCustomerService $waterCustomers): View
    {
        Gate::authorize('houses.view');

        $house->load(['block', 'occupancies.resident.users']);

        return view('resident.houses.show', [
            'house' => $house,
            // Kartu Air (Fase 3)
            'waterCustomer' => WaterCustomer::where('house_id', $house->getKey())->orderByDesc('start_date')->first(),
            'waterMeter' => $waterCustomers->activeMeter($house),
            'waterUsages' => WaterUsageRecord::where('house_id', $house->getKey())->orderByDesc('usage_period')->limit(6)->get(),
            'relationshipTypes' => RelationshipType::cases(),
            // Kandidat penghuni untuk form "pindah masuk".
            'residents' => Resident::orderBy('full_name')->get(['id', 'full_name', 'phone']),
        ]);
    }

    public function edit(House $house): View
    {
        Gate::authorize('houses.manage');

        return view('resident.houses.form', [
            'house' => $house,
            'blocks' => Block::orderBy('code')->get(),
        ]);
    }

    public function update(HouseRequest $request, House $house): RedirectResponse
    {
        $this->houses->updateHouse($house, $request->validated());

        return redirect()->route('houses.show', $house)->with('success', "Rumah {$house->house_code} diperbarui.");
    }

    public function destroy(House $house): RedirectResponse
    {
        Gate::authorize('houses.manage');

        try {
            $this->houses->deleteHouse($house);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('houses.index')->with('success', "Rumah {$house->house_code} dihapus.");
    }
}

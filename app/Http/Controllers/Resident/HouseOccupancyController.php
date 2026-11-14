<?php

namespace App\Http\Controllers\Resident;

use App\Http\Controllers\Controller;
use App\Http\Requests\Resident\MoveInRequest;
use App\Models\House;
use App\Models\HouseResident;
use App\Models\Resident;
use App\Services\Resident\ResidencyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

/**
 * Pindah masuk / pindah keluar (FR-RES-004, EX-009).
 */
class HouseOccupancyController extends Controller
{
    public function __construct(private readonly ResidencyService $residency) {}

    public function store(MoveInRequest $request, House $house): RedirectResponse
    {
        $data = $request->validated();

        try {
            $occupancy = DB::transaction(function () use ($house, $data) {
                $resident = filled($data['resident_id'] ?? null)
                    ? Resident::findOrFail($data['resident_id'])
                    : $this->residency->createResident([
                        'full_name' => $data['full_name'],
                        'phone' => $data['phone'] ?? null,
                    ]);

                return $this->residency->moveIn($house, $resident, $data);
            });
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('houses.show', $house)
            ->with('success', "{$occupancy->resident->full_name} tercatat menghuni {$house->house_code}.");
    }

    public function end(Request $request, HouseResident $houseResident): RedirectResponse
    {
        Gate::authorize('residents.manage');

        // Pastikan rumahnya milik komunitas aktif (HouseResident tidak punya
        // global scope sendiri).
        $house = House::findOrFail($houseResident->house_id);

        $validated = $request->validate([
            'end_date' => ['required', 'date', 'before_or_equal:today'],
            'end_reason' => ['nullable', 'string', 'max:500'],
            'deactivate_accounts' => ['boolean'],
        ], [], ['end_date' => 'tanggal keluar', 'end_reason' => 'alasan']);

        try {
            $this->residency->moveOut(
                $houseResident,
                $validated['end_date'],
                $validated['end_reason'] ?? null,
                $request->boolean('deactivate_accounts'),
            );
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('houses.show', $house)
            ->with('success', "Periode hunian {$houseResident->resident->full_name} di {$house->house_code} ditutup.");
    }
}

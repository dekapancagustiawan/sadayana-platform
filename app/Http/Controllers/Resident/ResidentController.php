<?php

namespace App\Http\Controllers\Resident;

use App\Enums\Resident\ResidentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Resident\ResidentRequest;
use App\Models\Resident;
use App\Services\Resident\ResidencyService;
use App\Support\Phone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Data penghuni (FR-RES-003). Lihat: residents.view; ubah: residents.manage.
 */
class ResidentController extends Controller
{
    public function __construct(private readonly ResidencyService $residency) {}

    public function index(Request $request): View
    {
        Gate::authorize('residents.view');

        $search = trim((string) $request->input('search'));

        $residents = Resident::query()
            ->with(['activeOccupancies.house', 'users:id'])
            ->when($request->filled('status'), fn (Builder $q) => $q->where('status', $request->input('status')))
            ->when($search !== '', function (Builder $q) use ($search) {
                $like = '%'.mb_strtolower($search).'%';
                $phone = Phone::normalize($search);
                $q->where(function (Builder $q) use ($like, $phone) {
                    $q->whereRaw('LOWER(full_name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(email) LIKE ?', [$like]);
                    if ($phone !== null && strlen($phone) >= 4) {
                        $q->orWhere('phone', 'like', '%'.$phone.'%');
                    }
                });
            })
            ->orderBy('full_name')
            ->paginate($request->integer('per_page', 25))
            ->withQueryString();

        return view('resident.residents.index', [
            'residents' => $residents,
            'statuses' => ResidentStatus::cases(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('residents.manage');

        return view('resident.residents.form', ['resident' => new Resident(['status' => ResidentStatus::Active])]);
    }

    public function store(ResidentRequest $request): RedirectResponse
    {
        $resident = $this->residency->createResident($request->validated());

        return redirect()->route('residents.show', $resident)->with('success', "Penghuni {$resident->full_name} ditambahkan.");
    }

    public function show(Resident $resident): View
    {
        Gate::authorize('residents.view');

        $resident->load(['occupancies.house', 'users']);

        return view('resident.residents.show', [
            'resident' => $resident,
            'canViewIdentity' => request()->user()->can('residents.view-identity'),
        ]);
    }

    public function edit(Resident $resident): View
    {
        Gate::authorize('residents.manage');

        return view('resident.residents.form', compact('resident'));
    }

    public function update(ResidentRequest $request, Resident $resident): RedirectResponse
    {
        $this->residency->updateResident($resident, $request->validated());

        return redirect()->route('residents.show', $resident)->with('success', 'Data penghuni diperbarui.');
    }
}

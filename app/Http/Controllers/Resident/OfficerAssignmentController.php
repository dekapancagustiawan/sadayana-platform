<?php

namespace App\Http\Controllers\Resident;

use App\Enums\Resident\AssignmentType;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Resident\OfficerAssignmentRequest;
use App\Models\Block;
use App\Models\House;
use App\Models\OfficerAssignment;
use App\Models\User;
use App\Services\Resident\OfficerAssignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use RuntimeException;

/**
 * Penugasan petugas lapangan & sampah (D-05). Gate menu
 * access-officer-assignments; ubah: officer-assignments.manage.
 */
class OfficerAssignmentController extends Controller
{
    public function __construct(private readonly OfficerAssignmentService $assignments) {}

    public function index(Request $request): View
    {
        $showEnded = $request->boolean('ended');

        $assignments = OfficerAssignment::query()
            ->with(['user', 'block', 'house'])
            ->unless($showEnded, fn ($q) => $q->current())
            ->orderBy('assignment_type')
            ->latest('assigned_from')
            ->paginate(50)
            ->withQueryString();

        return view('resident.officer-assignments.index', [
            'assignments' => $assignments,
            'showEnded' => $showEnded,
            'types' => AssignmentType::cases(),
            // Semua user aktif. Setelah Fase 3/4 menambah izin pencatatan
            // (meter-readings.create, waste-services.record), daftar ini bisa
            // dipersempit ke pemegang izin tersebut.
            'officers' => User::where('status', UserStatus::Active)->orderBy('name')->get(['id', 'name', 'email', 'phone']),
            'blocks' => Block::orderBy('code')->get(),
            'houses' => House::orderBy('house_code')->get(['id', 'house_code']),
        ]);
    }

    public function store(OfficerAssignmentRequest $request): RedirectResponse
    {
        try {
            $this->assignments->assign($request->validated());
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('officer-assignments.index')->with('success', 'Penugasan ditambahkan.');
    }

    public function end(Request $request, OfficerAssignment $officerAssignment): RedirectResponse
    {
        Gate::authorize('officer-assignments.manage');

        $validated = $request->validate(['assigned_until' => ['nullable', 'date']]);

        try {
            $this->assignments->end($officerAssignment, $validated['assigned_until'] ?? null);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Penugasan diakhiri.');
    }
}

<?php

namespace App\Http\Controllers\Communication;

use App\Enums\Communication\ComplaintCategory;
use App\Enums\Communication\ComplaintStatus;
use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\House;
use App\Models\User;
use App\Services\Communication\CommunityService;
use App\Services\Resident\HouseAccessService;
use App\Support\Uploads;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

/**
 * Pengaduan warga (FR-NOT-003), termasuk "sampah belum diambil" (S-3).
 * Warga melihat pengaduannya sendiri; pengelola (complaints.manage) melihat semua.
 */
class ComplaintController extends Controller
{
    public function __construct(
        private readonly CommunityService $community,
        private readonly HouseAccessService $access,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $manage = $user->can('complaints.manage');

        return view('communication.complaints.index', [
            'complaints' => Complaint::with(['house', 'submitter:id,name'])
                ->when(! $manage, fn ($q) => $q->where('submitted_by', $user->getKey()))
                ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
                ->when($request->filled('category'), fn ($q) => $q->where('category', $request->input('category')))
                ->latest()
                ->paginate(30)
                ->withQueryString(),
            'manage' => $manage,
            'statuses' => ComplaintStatus::cases(),
            'categories' => ComplaintCategory::cases(),
            'houses' => $this->access->housesFor($user),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('complaints.submit');
        $houses = $this->access->housesFor($request->user());

        $validated = $request->validate([
            'category' => ['required', Rule::enum(ComplaintCategory::class)],
            'house_id' => ['nullable', 'uuid', Rule::in($houses->pluck('id')->all())],
            'description' => ['required', 'string', 'max:2000'],
            'attachment' => ['nullable', ...Uploads::imageRules()],
        ], [], ['category' => 'kategori', 'description' => 'isi pengaduan', 'attachment' => 'foto']);

        $house = isset($validated['house_id']) ? House::find($validated['house_id']) : ($houses->count() === 1 ? $houses->first() : null);

        $complaint = $this->community->submitComplaint(
            $request->user(),
            $house,
            ComplaintCategory::from($validated['category']),
            $validated['description'],
            $request->file('attachment'),
        );

        return redirect()->route('complaints.show', $complaint)->with('success', "Pengaduan {$complaint->complaint_number} terkirim ke pengurus.");
    }

    public function show(Request $request, Complaint $complaint): View
    {
        $user = $request->user();
        abort_unless($user->can('complaints.manage') || $complaint->submitted_by === $user->getKey(), 403);

        return view('communication.complaints.show', [
            'complaint' => $complaint->load(['house', 'submitter', 'assignee']),
            'manage' => $user->can('complaints.manage'),
            'statuses' => ComplaintStatus::cases(),
            'staff' => User::where('status', 'active')->get()->filter(fn (User $u) => $u->can('complaints.manage'))->values(),
        ]);
    }

    public function update(Request $request, Complaint $complaint): RedirectResponse
    {
        Gate::authorize('complaints.manage');

        $validated = $request->validate([
            'status' => ['required', Rule::enum(ComplaintStatus::class)],
            'resolution_notes' => ['nullable', 'string', 'max:2000'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        try {
            $this->community->updateComplaint($complaint, ComplaintStatus::from($validated['status']), $validated['resolution_notes'] ?? null, $request->user(), $validated['assigned_to'] ?? null);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Pengaduan diperbarui; pelapor diberi notifikasi.');
    }
}

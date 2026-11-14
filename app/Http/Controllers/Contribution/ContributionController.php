<?php

namespace App\Http\Controllers\Contribution;

use App\Http\Controllers\Controller;
use App\Models\ContributionType;
use App\Models\House;
use App\Services\Contribution\ContributionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use RuntimeException;

/**
 * Jenis iuran warga yang dapat dikonfigurasi (FR-COM-001..003).
 */
class ContributionController extends Controller
{
    public function __construct(private readonly ContributionService $contributions) {}

    public function index(): View
    {
        Gate::authorize('access-contributions');

        $types = ContributionType::with(['periods', 'assignments.house'])->orderBy('status')->orderBy('name')->get();

        return view('contributions.index', [
            'types' => $types,
            'houses' => House::where('is_active', true)->orderBy('house_code')->get(['id', 'house_code']),
            'current' => $types->mapWithKeys(fn (ContributionType $t) => [$t->id => $this->contributions->periodFor($t, now())]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('contributions.manage');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'contribution_category' => ['required', 'in:routine,incidental'],
            'applies_to' => ['required', 'in:all_houses,assigned'],
            'amount' => ['required', 'numeric', 'min:0', 'max:100000000'],
            'effective_from' => ['required', 'date'],
        ], [], ['name' => 'nama iuran', 'amount' => 'nominal', 'effective_from' => 'berlaku mulai']);

        try {
            $type = $this->contributions->createType([...$validated, 'amount' => (string) $validated['amount']]);
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', "Iuran {$type->name} dibuat.");
    }

    public function storePeriod(Request $request, ContributionType $type): RedirectResponse
    {
        Gate::authorize('contributions.manage');

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0', 'max:100000000'],
            'effective_from' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->contributions->addPeriod($type, $validated['effective_from'], (string) $validated['amount'], $validated['notes'] ?? null);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Nominal {$type->name} diperbarui.");
    }

    public function toggle(ContributionType $type): RedirectResponse
    {
        Gate::authorize('contributions.manage');

        $this->contributions->setStatus($type, $type->status === 'active' ? 'inactive' : 'active');

        return back()->with('success', "Status {$type->name} diubah.");
    }

    public function assign(Request $request, ContributionType $type): RedirectResponse
    {
        Gate::authorize('contributions.manage');

        $validated = $request->validate([
            'house_ids' => ['required', 'array', 'min:1'],
            'house_ids.*' => ['uuid'],
            'assigned_from' => ['required', 'date'],
            'custom_amount' => ['nullable', 'numeric', 'min:0'],
        ], [], ['house_ids' => 'rumah']);

        try {
            $count = $this->contributions->assign($type, $validated['house_ids'], $validated['assigned_from'], $validated['custom_amount'] ?? null);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "{$count} rumah ditetapkan untuk {$type->name}.");
    }
}

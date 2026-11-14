<?php

namespace App\Http\Controllers\Water;

use App\Enums\Water\WaterCustomerStatus;
use App\Http\Controllers\Controller;
use App\Models\House;
use App\Models\WaterCustomer;
use App\Models\WaterMeter;
use App\Models\WaterUsageRecord;
use App\Services\Water\WaterCustomerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use RuntimeException;

/**
 * Pelanggan air & meteran (FR-WAT-001, S-3). Aksi dipicu dari detail rumah.
 */
class WaterCustomerController extends Controller
{
    public function __construct(private readonly WaterCustomerService $customers) {}

    public function index(Request $request): View
    {
        Gate::authorize('meter-readings.view');

        $customers = WaterCustomer::query()
            ->with('house.block')
            ->when(! $request->boolean('all'), fn ($q) => $q->open())
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->orderBy('customer_number')
            ->paginate(50)
            ->withQueryString();

        $houseIds = $customers->pluck('house_id');

        return view('water.customers.index', [
            'customers' => $customers,
            'meters' => WaterMeter::whereIn('house_id', $houseIds)->where('status', 'active')->get()->keyBy('house_id'),
            'lastUsage' => WaterUsageRecord::whereIn('house_id', $houseIds)
                ->orderByDesc('usage_period')
                ->get()
                ->unique('house_id')
                ->keyBy('house_id'),
            'statuses' => WaterCustomerStatus::cases(),
            'counts' => WaterCustomer::open()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    public function subscribe(Request $request, House $house): RedirectResponse
    {
        Gate::authorize('water-customers.manage');

        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'meter_number' => ['nullable', 'string', 'max:50'],
            'initial_reading' => ['nullable', 'numeric', 'min:0'],
        ], [], ['start_date' => 'mulai langganan', 'initial_reading' => 'angka awal meter']);

        try {
            $this->customers->subscribe($house, $validated);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Rumah {$house->house_code} terdaftar sebagai pelanggan air.");
    }

    public function unsubscribe(Request $request, WaterCustomer $customer): RedirectResponse
    {
        Gate::authorize('water-customers.manage');

        $validated = $request->validate([
            'end_date' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:500'],
        ], [], ['end_date' => 'tanggal berhenti']);

        try {
            $this->customers->unsubscribe($customer, $validated['end_date'], $validated['reason'] ?? null);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Langganan air dihentikan.');
    }

    public function suspension(Request $request, WaterCustomer $customer): RedirectResponse
    {
        Gate::authorize('water-customers.manage');

        $validated = $request->validate([
            'suspended' => ['required', 'boolean'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->customers->setSuspended($customer, (bool) $validated['suspended'], $validated['reason'] ?? null);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $validated['suspended'] ? 'Saluran air ditandai diputus sementara.' : 'Saluran air disambung kembali.');
    }

    public function replaceMeter(Request $request, House $house): RedirectResponse
    {
        Gate::authorize('water-customers.manage');

        $validated = $request->validate([
            'final_reading' => ['required', 'numeric', 'min:0'],
            'replaced_at' => ['required', 'date', 'before_or_equal:today'],
            'meter_number' => ['nullable', 'string', 'max:50'],
            'initial_reading' => ['nullable', 'numeric', 'min:0'],
            'old_status' => ['required', 'in:replaced,broken'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [], ['final_reading' => 'angka akhir meter lama', 'replaced_at' => 'tanggal penggantian', 'initial_reading' => 'angka awal meter baru']);

        try {
            $this->customers->replaceMeter($house, $validated);
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Meter diganti. Sisa pemakaian meter lama ditagihkan pada pembacaan berikutnya.');
    }
}

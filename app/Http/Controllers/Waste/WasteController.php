<?php

namespace App\Http\Controllers\Waste;

use App\Enums\Resident\AssignmentType;
use App\Http\Controllers\Controller;
use App\Models\Block;
use App\Models\House;
use App\Models\WasteFeePeriod;
use App\Models\WasteServiceRecord;
use App\Models\WasteSubscription;
use App\Services\Resident\HouseAccessService;
use App\Services\Waste\WasteService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use RuntimeException;

/**
 * Layanan sampah (FR-WST-*, S-3): langganan per rumah, nominal bulanan, dan
 * checklist pengambilan mingguan untuk petugas sampah.
 */
class WasteController extends Controller
{
    public function __construct(
        private readonly WasteService $waste,
        private readonly HouseAccessService $access,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('waste.view');

        $houses = House::query()
            ->with(['block', 'activeOccupancies.resident'])
            ->when($request->filled('block'), fn ($q) => $q->where('block_id', $request->input('block')))
            ->orderBy('house_code')
            ->get();

        return view('waste.index', [
            'houses' => $houses,
            'subscriptions' => WasteSubscription::whereNull('end_date')->get()->keyBy('house_id'),
            'blocks' => Block::orderBy('code')->get(),
            'fee' => $this->waste->feeFor(now()),
            'fees' => WasteFeePeriod::orderByDesc('effective_from')->get(),
            'recentMissed' => WasteServiceRecord::with('house')->whereIn('status', ['missed', 'not_served'])->latest('service_date')->limit(10)->get(),
        ]);
    }

    public function subscribe(Request $request, House $house): RedirectResponse
    {
        Gate::authorize('waste.manage');
        $validated = $request->validate(['start_date' => ['required', 'date']]);

        try {
            $this->waste->subscribe($house, $validated['start_date']);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "{$house->house_code} berlangganan layanan sampah.");
    }

    public function bulkSubscribe(Request $request): RedirectResponse
    {
        Gate::authorize('waste.manage');
        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'house_ids' => ['required', 'array', 'min:1'],
            'house_ids.*' => ['uuid'],
        ], [], ['house_ids' => 'rumah']);

        $done = 0;
        foreach (House::whereIn('id', $validated['house_ids'])->get() as $house) {
            if (! $this->waste->openSubscription($house)) {
                $this->waste->subscribe($house, $validated['start_date']);
                $done++;
            }
        }

        return back()->with('success', "{$done} rumah didaftarkan ke layanan sampah.");
    }

    public function unsubscribe(Request $request, WasteSubscription $subscription): RedirectResponse
    {
        Gate::authorize('waste.manage');
        $validated = $request->validate(['end_date' => ['required', 'date'], 'reason' => ['nullable', 'string', 'max:500']]);

        try {
            $this->waste->unsubscribe($subscription, $validated['end_date'], $validated['reason'] ?? null);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Langganan sampah dihentikan.');
    }

    public function storeFee(Request $request): RedirectResponse
    {
        Gate::authorize('waste.manage');
        $validated = $request->validate([
            'effective_from' => ['required', 'date'],
            'monthly_amount' => ['required', 'numeric', 'min:0', 'max:100000000'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [], ['effective_from' => 'berlaku mulai', 'monthly_amount' => 'iuran per bulan']);

        try {
            $this->waste->addFeePeriod($validated['effective_from'], (string) $validated['monthly_amount'], $validated['notes'] ?? null);
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Iuran sampah baru disimpan.');
    }

    /**
     * Checklist pengambilan sampah (petugas sampah, dari HP).
     */
    public function collection(Request $request): View
    {
        Gate::authorize('waste-services.record');

        $date = $this->date($request);
        $houses = $this->collectableHouses($request)->load(['block', 'activeOccupancies.resident']);

        return view('waste.collection', [
            'date' => $date,
            'houses' => $houses,
            'records' => WasteServiceRecord::whereDate('service_date', $date->toDateString())->whereIn('house_id', $houses->pluck('id'))->get()->keyBy('house_id'),
            'statuses' => WasteService::STATUSES,
        ]);
    }

    public function storeCollection(Request $request): RedirectResponse
    {
        Gate::authorize('waste-services.record');

        $validated = $request->validate([
            'service_date' => ['required', 'date', 'before_or_equal:today'],
            'status' => ['required', 'array'],
            'status.*' => ['in:'.implode(',', array_keys(WasteService::STATUSES))],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $allowed = $this->collectableHouses($request)->pluck('id')->all();
        $statuses = array_intersect_key($validated['status'], array_flip($allowed));

        $count = $this->waste->recordCollection($validated['service_date'], $statuses, $request->user(), $validated['notes'] ?? null);

        return redirect()->route('waste.collection', ['date' => $validated['service_date']])
            ->with('success', "Pengambilan sampah {$count} rumah dicatat.");
    }

    /**
     * Rumah berlangganan sampah yang menjadi tugas user ini.
     *
     * @return Collection<int, House>
     */
    private function collectableHouses(Request $request)
    {
        $subscribed = WasteSubscription::whereNull('end_date')->pluck('house_id');
        $query = House::whereIn('id', $subscribed)->orderBy('house_code');

        if (! $request->user()->can('waste.manage')) {
            $assigned = $this->access->officerHouseIds($request->user(), AssignmentType::Waste);
            // Tanpa penugasan khusus, petugas sampah melayani semua rumah berlangganan.
            if ($assigned->isNotEmpty()) {
                $query->whereIn('id', $assigned);
            }
        }

        return $query->get();
    }

    private function date(Request $request): Carbon
    {
        try {
            $date = Carbon::parse((string) $request->input('date', today()->toDateString()));
        } catch (\Throwable) {
            $date = today();
        }

        return $date->isFuture() ? today() : $date->startOfDay();
    }
}

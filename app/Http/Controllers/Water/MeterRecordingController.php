<?php

namespace App\Http\Controllers\Water;

use App\Enums\Resident\AssignmentType;
use App\Enums\Water\MeterReadingStatus;
use App\Enums\Water\WaterCustomerStatus;
use App\Http\Controllers\Controller;
use App\Models\House;
use App\Models\User;
use App\Models\WaterCustomer;
use App\Models\WaterMeterReading;
use App\Services\Resident\HouseAccessService;
use App\Services\Water\MeterReadingService;
use App\Services\Water\WaterCustomerService;
use App\Services\Water\WaterUsageService;
use App\Support\Uploads;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use RuntimeException;

/**
 * Layar petugas lapangan untuk mencatat meter dari HP (FR-WAT-002/003).
 * Petugas hanya melihat rumah yang ditugaskan kepadanya (D-05), kecuali
 * pemegang izin `meter-readings.record-any`.
 */
class MeterRecordingController extends Controller
{
    public function __construct(
        private readonly MeterReadingService $readings,
        private readonly WaterCustomerService $customers,
        private readonly WaterUsageService $usage,
        private readonly HouseAccessService $access,
    ) {}

    public function index(Request $request): View
    {
        $period = $this->period($request);
        $houseIds = $this->houseIds($request->user());
        $search = mb_strtolower(trim((string) $request->input('search')));

        $houses = House::query()
            ->whereIn('id', $houseIds)
            ->with(['block', 'activeOccupancies.resident'])
            ->when($search !== '', fn ($q) => $q->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(house_code) LIKE ?', ['%'.$search.'%'])
                    ->orWhereHas('activeOccupancies.resident', fn ($r) => $r->whereRaw('LOWER(full_name) LIKE ?', ['%'.$search.'%']));
            }))
            ->orderBy('house_code')
            ->get();

        $readings = WaterMeterReading::query()
            ->whereIn('house_id', $houses->pluck('id'))
            ->whereDate('reading_period', $period->toDateString())
            ->where('status', '!=', MeterReadingStatus::Rejected)
            ->get()
            ->keyBy('house_id');

        return view('water.recording.index', [
            'period' => $period,
            'houses' => $houses,
            'readings' => $readings,
            'done' => $readings->count(),
            'hasAssignments' => $houseIds->isNotEmpty(),
        ]);
    }

    public function create(Request $request, House $house): View|RedirectResponse
    {
        $this->authorizeHouse($request->user(), $house);
        $period = $this->period($request);

        $meter = $this->customers->activeMeter($house);
        if (! $meter) {
            return redirect()->route('meter-recording.index', ['period' => $period->format('Y-m')])
                ->with('error', "Rumah {$house->house_code} belum punya meter aktif.");
        }

        $existing = WaterMeterReading::query()
            ->where('water_meter_id', $meter->getKey())
            ->whereDate('reading_period', $period->toDateString())
            ->latest()
            ->first();

        return view('water.recording.create', [
            'house' => $house->load(['block', 'activeOccupancies.resident']),
            'period' => $period,
            'meter' => $meter,
            'baseline' => $this->usage->baseline($meter, $period),
            'existing' => $existing,
        ]);
    }

    public function store(Request $request, House $house): RedirectResponse
    {
        $this->authorizeHouse($request->user(), $house);

        $validated = $request->validate([
            'period' => ['required', 'date_format:Y-m'],
            'reading_value' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'photo' => ['required', ...Uploads::imageRules()],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'photo.required' => 'Foto meteran wajib diunggah.',
        ], ['reading_value' => 'angka meter', 'photo' => 'foto meteran']);

        $period = Carbon::createFromFormat('!Y-m', $validated['period'])->startOfMonth();

        try {
            $reading = $this->readings->record(
                $house,
                $period,
                (string) $validated['reading_value'],
                $request->file('photo'),
                $request->user(),
                $validated['notes'] ?? null,
            );
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $message = $reading->status === MeterReadingStatus::Pending
            ? "Meter {$house->house_code} tersimpan, tetapi angkanya janggal sehingga menunggu pemeriksaan pengurus."
            : "Meter {$house->house_code} tersimpan: pemakaian {$reading->usage?->usage_volume} m³.";

        return redirect()->route('meter-recording.index', ['period' => $period->format('Y-m')])
            ->with($reading->status === MeterReadingStatus::Pending ? 'warning' : 'success', $message);
    }

    private function period(Request $request): Carbon
    {
        $input = (string) $request->input('period');

        if (preg_match('/^\d{4}-\d{2}$/', $input)) {
            $period = Carbon::createFromFormat('!Y-m', $input)->startOfMonth();
            if (! $period->isAfter(now()->startOfMonth())) {
                return $period;
            }
        }

        return MeterReadingService::defaultPeriod();
    }

    /**
     * Rumah pelanggan air yang boleh dicatat user ini.
     *
     * @return Collection<int, string>
     */
    private function houseIds(User $user): Collection
    {
        $customerHouses = WaterCustomer::query()
            ->open()
            ->whereIn('status', [WaterCustomerStatus::Active, WaterCustomerStatus::Suspended])
            ->pluck('house_id');

        if ($user->can('meter-readings.record-any')) {
            return $customerHouses;
        }

        return $this->access->officerHouseIds($user, AssignmentType::Water)->intersect($customerHouses)->values();
    }

    private function authorizeHouse(User $user, House $house): void
    {
        abort_unless($this->houseIds($user)->contains($house->getKey()), 403, 'Rumah ini tidak termasuk tugas Anda.');
    }
}

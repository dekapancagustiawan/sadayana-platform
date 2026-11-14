<?php

namespace App\Http\Controllers\Water;

use App\Enums\Water\CorrectionStatus;
use App\Enums\Water\MeterReadingStatus;
use App\Http\Controllers\Controller;
use App\Models\House;
use App\Models\WaterMeterReading;
use App\Models\WaterReadingCorrection;
use App\Models\WaterUsageRecord;
use App\Services\Water\MeterReadingService;
use App\Services\Water\WaterUsageService;
use App\Support\Uploads;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use RuntimeException;

/**
 * Pembacaan meter untuk pengurus: daftar per bulan, pemeriksaan pembacaan
 * janggal, dan koreksi (FR-WAT-002, FR-WAT-006, Q5).
 */
class MeterReadingController extends Controller
{
    public function __construct(
        private readonly MeterReadingService $readings,
        private readonly WaterUsageService $usage,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('meter-readings.view');

        $period = preg_match('/^\d{4}-\d{2}$/', (string) $request->input('period'))
            ? Carbon::createFromFormat('!Y-m', $request->input('period'))->startOfMonth()
            : MeterReadingService::defaultPeriod();

        $readings = WaterMeterReading::query()
            ->whereHas('house') // scope komunitas
            ->with(['house', 'usage', 'recorder:id,name'])
            ->whereDate('reading_period', $period->toDateString())
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->orderBy(House::select('house_code')->whereColumn('houses.id', 'water_meter_readings.house_id'))
            ->get();

        $summary = WaterUsageRecord::query()
            ->whereHas('house')
            ->whereDate('usage_period', $period->toDateString())
            ->selectRaw('COUNT(*) as houses, COALESCE(SUM(usage_volume), 0) as volume, COALESCE(SUM(calculated_amount), 0) as amount')
            ->first();

        return view('water.readings.index', [
            'period' => $period,
            'readings' => $readings,
            'summary' => $summary,
            'pendingCount' => WaterMeterReading::whereHas('house')->where('status', MeterReadingStatus::Pending)->count(),
            'pendingCorrections' => WaterReadingCorrection::query()
                ->where('status', CorrectionStatus::Pending)
                ->whereHas('reading.house')
                ->with(['reading.house', 'requester:id,name'])
                ->latest()
                ->get(),
            'statuses' => MeterReadingStatus::cases(),
        ]);
    }

    public function show(WaterMeterReading $reading): View
    {
        Gate::authorize('meter-readings.view');
        $house = House::findOrFail($reading->house_id);

        $reading->load(['meter', 'photo', 'recorder:id,name', 'validator:id,name', 'usage.tariffPeriod', 'corrections.requester:id,name', 'corrections.decider:id,name']);

        return view('water.readings.show', [
            'reading' => $reading,
            'house' => $house,
            'baseline' => $this->usage->baseline($reading->meter, $reading->reading_period, $reading->getKey()),
            'history' => WaterUsageRecord::where('house_id', $house->getKey())->orderByDesc('usage_period')->limit(12)->get(),
        ]);
    }

    public function validateReading(Request $request, WaterMeterReading $reading): RedirectResponse
    {
        Gate::authorize('meter-readings.validate');
        House::findOrFail($reading->house_id);

        $validated = $request->validate([
            'corrected_value' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500', 'required_with:corrected_value'],
        ], [], ['corrected_value' => 'angka yang benar', 'notes' => 'catatan']);

        try {
            $this->readings->validate($reading, $request->user(), $validated['corrected_value'] ?? null, $validated['notes'] ?? null);
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Pembacaan divalidasi dan pemakaian dihitung.');
    }

    public function reject(Request $request, WaterMeterReading $reading): RedirectResponse
    {
        Gate::authorize('meter-readings.validate');
        House::findOrFail($reading->house_id);

        $validated = $request->validate(['reason' => ['required', 'string', 'max:500']], [], ['reason' => 'alasan']);

        try {
            $this->readings->reject($reading, $request->user(), $validated['reason']);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Pembacaan ditolak. Petugas dapat mencatat ulang meter bulan ini.');
    }

    /**
     * Ajukan koreksi (petugas maupun pengurus — izin meter-readings.correct).
     */
    public function requestCorrection(Request $request, WaterMeterReading $reading): RedirectResponse
    {
        Gate::authorize('meter-readings.correct');
        House::findOrFail($reading->house_id);

        $validated = $request->validate([
            'new_value' => ['required', 'numeric', 'min:0'],
            'reason' => ['required', 'string', 'max:500'],
            'photo' => ['nullable', ...Uploads::imageRules()],
        ], [], ['new_value' => 'angka yang benar', 'reason' => 'alasan', 'photo' => 'foto']);

        try {
            $this->readings->requestCorrection($reading, (string) $validated['new_value'], $validated['reason'], $request->user(), $request->file('photo'));
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Koreksi diajukan dan menunggu persetujuan pengurus.');
    }

    public function approveCorrection(Request $request, WaterReadingCorrection $correction): RedirectResponse
    {
        Gate::authorize('meter-readings.validate');
        House::findOrFail($correction->reading->house_id);

        try {
            $this->readings->approveCorrection($correction, $request->user(), $request->input('notes'));
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Koreksi disetujui; pemakaian dihitung ulang.');
    }

    public function rejectCorrection(Request $request, WaterReadingCorrection $correction): RedirectResponse
    {
        Gate::authorize('meter-readings.validate');
        House::findOrFail($correction->reading->house_id);

        $validated = $request->validate(['notes' => ['required', 'string', 'max:500']], [], ['notes' => 'alasan']);

        try {
            $this->readings->rejectCorrection($correction, $request->user(), $validated['notes']);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Koreksi ditolak.');
    }
}

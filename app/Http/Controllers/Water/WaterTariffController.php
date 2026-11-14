<?php

namespace App\Http\Controllers\Water;

use App\Http\Controllers\Controller;
use App\Services\Water\WaterTariffService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use RuntimeException;

/**
 * Tarif air berperiode (FR-WAT-005). Lihat: meter-readings.view;
 * ubah: water-tariffs.manage (bendahara).
 */
class WaterTariffController extends Controller
{
    public function __construct(private readonly WaterTariffService $tariffs) {}

    public function index(): View
    {
        Gate::authorize('meter-readings.view');

        $tariff = $this->tariffs->defaultTariff();

        return view('water.tariffs.index', [
            'tariff' => $tariff,
            'periods' => $tariff?->periods()->get() ?? collect(),
            'current' => $this->tariffs->periodFor(now()),
        ]);
    }

    public function storePeriod(Request $request): RedirectResponse
    {
        Gate::authorize('water-tariffs.manage');

        $validated = $request->validate([
            'effective_from' => ['required', 'date'],
            'base_fee' => ['required', 'numeric', 'min:0', 'max:100000000'],
            'rate_per_unit' => ['required', 'numeric', 'min:0', 'max:100000000'],
            'minimum_charge' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [], [
            'effective_from' => 'berlaku mulai',
            'base_fee' => 'abodemen',
            'rate_per_unit' => 'tarif per m³',
            'minimum_charge' => 'tagihan minimum',
        ]);

        try {
            $this->tariffs->addPeriod([
                ...$validated,
                'base_fee' => (string) $validated['base_fee'],
                'rate_per_unit' => (string) $validated['rate_per_unit'],
                'minimum_charge' => isset($validated['minimum_charge']) ? (string) $validated['minimum_charge'] : null,
            ]);
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Tarif air baru disimpan.');
    }
}

<?php

namespace App\Http\Controllers\Billing;

use App\Enums\Billing\InvoiceStatus;
use App\Enums\Billing\InvoiceType;
use App\Http\Controllers\Controller;
use App\Models\BillingExemption;
use App\Models\BillingPeriod;
use App\Models\House;
use App\Models\Invoice;
use App\Services\Audit\AuditService;
use App\Services\Billing\BillingService;
use App\Services\Resident\HouseAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use RuntimeException;

/**
 * Tagihan untuk pengurus/bendahara (FR-BIL-001..005).
 */
class InvoiceController extends Controller
{
    public function __construct(
        private readonly BillingService $billing,
        private readonly HouseAccessService $access,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('invoices.view');

        $month = $this->month($request);
        $period = BillingPeriod::where('period_code', $month->format('Y-m'))->first();
        $search = mb_strtolower(trim((string) $request->input('search')));

        $invoices = Invoice::query()
            ->with(['house', 'contributionType'])
            ->when($period, fn ($q) => $q->where('billing_period_id', $period->id), fn ($q) => $q->whereRaw('1 = 0'))
            ->when($request->filled('type'), fn ($q) => $q->where('invoice_type', $request->input('type')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($search !== '', fn ($q) => $q->whereHas('house', fn ($h) => $h->whereRaw('LOWER(house_code) LIKE ?', ['%'.$search.'%'])))
            ->orderBy(House::select('house_code')->whereColumn('houses.id', 'invoices.house_id'))
            ->orderBy('invoice_type')
            ->paginate(100)
            ->withQueryString();

        $totals = $period ? Invoice::where('billing_period_id', $period->id)
            ->where('status', '!=', InvoiceStatus::Cancelled)
            ->selectRaw('status, COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total, COALESCE(SUM(outstanding_amount), 0) as outstanding')
            ->groupBy('status')->get()->keyBy(fn ($row) => $row->status instanceof InvoiceStatus ? $row->status->value : $row->status) : collect();

        return view('billing.invoices.index', [
            'month' => $month,
            'period' => $period,
            'invoices' => $invoices,
            'totals' => $totals,
            'types' => InvoiceType::cases(),
            'statuses' => InvoiceStatus::cases(),
        ]);
    }

    public function show(Request $request, Invoice $invoice): View
    {
        $this->authorizeView($request, $invoice);

        $invoice->load(['house.block', 'period', 'details', 'contributionType', 'allocations.payment']);

        return view('billing.invoices.show', [
            'invoice' => $invoice,
            'resident' => $invoice->house->primaryResident(),
            'print' => $request->boolean('print'),
        ]);
    }

    public function generate(Request $request): RedirectResponse
    {
        Gate::authorize('invoices.generate');

        $validated = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'types' => ['required', 'array', 'min:1'],
            'types.*' => ['in:water,waste,contribution'],
        ], [], ['types' => 'jenis tagihan']);

        $month = Carbon::createFromFormat('!Y-m', $validated['month']);

        try {
            $result = $this->billing->generate($month, $validated['types']);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('invoices.index', ['month' => $validated['month']])->with('success', sprintf(
            'Draft dibuat: %d tagihan baru (%s). %d sudah ada sebelumnya, %d dilewati karena pembebasan.',
            $result['created'],
            collect($result['by_type'])->map(fn ($n, $t) => InvoiceType::from($t)->label().' '.$n)->join(', ') ?: '-',
            $result['skipped_existing'],
            $result['skipped_exempt'],
        ));
    }

    public function issue(Request $request): RedirectResponse
    {
        Gate::authorize('invoices.issue');

        $validated = $request->validate(['month' => ['required', 'date_format:Y-m']]);
        $period = BillingPeriod::where('period_code', $validated['month'])->firstOrFail();

        $count = $this->billing->issue($period, $request->user());

        return back()->with('success', "{$count} tagihan diterbitkan dan warga diberi notifikasi.");
    }

    public function cancel(Request $request, Invoice $invoice): RedirectResponse
    {
        Gate::authorize('invoices.cancel');
        $validated = $request->validate(['reason' => ['required', 'string', 'max:500']], [], ['reason' => 'alasan']);

        try {
            $this->billing->cancel($invoice, $request->user(), $validated['reason']);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Tagihan {$invoice->invoice_number} dibatalkan.");
    }

    public function exemptions(): View
    {
        Gate::authorize('invoices.view');

        return view('billing.exemptions', [
            'exemptions' => BillingExemption::with('house')->orderByDesc('valid_until')->get(),
            'houses' => House::orderBy('house_code')->get(['id', 'house_code']),
        ]);
    }

    public function storeExemption(Request $request): RedirectResponse
    {
        Gate::authorize('billing-exemptions.manage');

        $validated = $request->validate([
            'house_id' => ['required', 'uuid', 'exists:houses,id'],
            'billing_type' => ['nullable', 'in:water,waste,contribution'],
            'valid_from' => ['required', 'date'],
            'valid_until' => ['required', 'date', 'after_or_equal:valid_from'],
            'reason' => ['required', 'string', 'max:255'],
        ], [], ['house_id' => 'rumah', 'valid_until' => 'berlaku sampai', 'reason' => 'alasan']);

        DB::transaction(function () use ($validated) {
            $exemption = BillingExemption::create($validated);
            app(AuditService::class)->created('billing.exemption_created', $exemption);
        });

        return back()->with('success', 'Pembebasan tagihan disimpan.');
    }

    public function endExemption(BillingExemption $exemption): RedirectResponse
    {
        Gate::authorize('billing-exemptions.manage');

        $exemption->valid_until = max($exemption->valid_from, today()->subDay());
        app(AuditService::class)->changed('billing.exemption_ended', $exemption);
        $exemption->save();

        return back()->with('success', 'Pembebasan diakhiri.');
    }

    private function month(Request $request): Carbon
    {
        $input = (string) $request->input('month');

        return preg_match('/^\d{4}-\d{2}$/', $input)
            ? Carbon::createFromFormat('!Y-m', $input)
            : now()->subMonthNoOverflow()->startOfMonth();
    }

    /**
     * Pengurus dengan invoices.view, atau warga rumah itu pada periode
     * tagihannya (FR-BIL-005, FR-RES-004).
     */
    private function authorizeView(Request $request, Invoice $invoice): void
    {
        $user = $request->user();
        if ($user->can('invoices.view')) {
            return;
        }

        $house = House::findOrFail($invoice->house_id);
        $period = $invoice->period;
        $allowed = $invoice->status !== InvoiceStatus::Draft && $this->access->occupancyPeriods($user, $house)->contains(
            fn (array $o) => $o['start']->lte($period->period_end) && ($o['end'] === null || $o['end']->gte($period->period_start)),
        );

        abort_unless($allowed, 403);
    }
}

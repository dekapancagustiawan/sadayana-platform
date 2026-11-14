<?php

namespace App\Http\Controllers\Billing;

use App\Enums\Billing\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Models\House;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Payment\PaymentService;
use App\Services\Resident\HouseAccessService;
use App\Support\Uploads;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * Portal warga: tagihan, pengajuan pembayaran, dan riwayat (FR-BIL-005,
 * FR-PAY-001, SRS ROLE-006). Data dibatasi ke rumah yang dihuni dan ke
 * periode hunian warga itu sendiri (FR-RES-004).
 */
class MyBillController extends Controller
{
    public function __construct(
        private readonly HouseAccessService $access,
        private readonly PaymentService $payments,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $houses = $this->access->housesFor($user);

        $data = $houses->map(function (House $house) use ($user) {
            $periods = $this->access->occupancyPeriods($user, $house);
            $earliest = $periods->min(fn ($p) => $p['start']);

            $invoices = Invoice::query()
                ->with(['period', 'contributionType'])
                ->where('house_id', $house->getKey())
                ->where('status', '!=', InvoiceStatus::Draft)
                ->when($earliest, fn ($q) => $q->whereHas('period', fn ($p) => $p->whereDate('period_end', '>=', $earliest->toDateString())))
                ->orderByDesc('invoice_date')
                ->limit(60)
                ->get();

            return [
                'house' => $house,
                'payable' => $this->payments->payableInvoices($house),
                'invoices' => $invoices,
                'payments' => Payment::with('allocations.invoice')
                    ->where('house_id', $house->getKey())
                    ->when($earliest, fn ($q) => $q->whereDate('payment_date', '>=', $earliest->toDateString()))
                    ->latest('created_at')->limit(20)->get(),
            ];
        });

        return view('billing.my-bills', ['items' => $data]);
    }

    public function store(Request $request, House $house): RedirectResponse
    {
        abort_unless($request->user()->can('payments.submit') && $this->access->housesFor($request->user())->contains('id', $house->getKey()), 403);

        $validated = $request->validate([
            'invoice_ids' => ['required', 'array', 'min:1'],
            'invoice_ids.*' => ['uuid'],
            'payment_date' => ['required', 'date', 'before_or_equal:today'],
            'external_reference' => ['nullable', 'string', 'max:100'],
            'proof' => ['required', ...Uploads::documentRules()],
            'notes' => ['nullable', 'string', 'max:500'],
        ], ['proof.required' => 'Bukti transfer wajib diunggah.'], [
            'invoice_ids' => 'tagihan', 'payment_date' => 'tanggal transfer', 'proof' => 'bukti transfer',
        ]);

        try {
            $payment = $this->payments->submit($house, $validated['invoice_ids'], $validated, $request->file('proof'), $request->user());
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('my-bills.index')->with('success', "Pembayaran {$payment->payment_number} terkirim dan menunggu verifikasi bendahara. Tagihan berstatus lunas setelah diverifikasi.");
    }

    public function resubmit(Request $request, Payment $payment): RedirectResponse
    {
        $house = House::findOrFail($payment->house_id);
        abort_unless($this->access->housesFor($request->user())->contains('id', $house->getKey()), 403);

        $validated = $request->validate([
            'proof' => ['required', ...Uploads::documentRules()],
            'external_reference' => ['nullable', 'string', 'max:100'],
        ], [], ['proof' => 'bukti transfer']);

        try {
            $this->payments->resubmit($payment, $request->file('proof'), $validated['external_reference'] ?? null, $request->user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Bukti baru terkirim untuk diverifikasi ulang.');
    }

    public function cancel(Request $request, Payment $payment): RedirectResponse
    {
        $house = House::findOrFail($payment->house_id);
        abort_unless($this->access->housesFor($request->user())->contains('id', $house->getKey()), 403);

        try {
            $this->payments->cancel($payment, $request->user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Pengajuan pembayaran dibatalkan.');
    }
}

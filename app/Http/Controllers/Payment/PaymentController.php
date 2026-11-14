<?php

namespace App\Http\Controllers\Payment;

use App\Enums\Payment\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\CashAccount;
use App\Models\House;
use App\Models\Payment;
use App\Services\Payment\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use RuntimeException;

/**
 * Verifikasi pembayaran & pencatatan tunai oleh bendahara (FR-PAY-002..006, S-5).
 */
class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    public function index(Request $request): View
    {
        Gate::authorize('payments.view');

        $status = $request->input('status', 'pending_verification');

        return view('payments.index', [
            'payments' => Payment::with(['house', 'payer:id,name'])
                ->when($status !== 'all', fn ($q) => $q->where('status', $status))
                ->latest('created_at')
                ->paginate(50)
                ->withQueryString(),
            'status' => $status,
            'statuses' => PaymentStatus::cases(),
            'pendingCount' => Payment::where('status', PaymentStatus::PendingVerification)->count(),
        ]);
    }

    public function show(Payment $payment): View
    {
        Gate::authorize('payments.view');

        $payment->load(['house', 'payer', 'verifier', 'allocations.invoice.contributionType', 'allocations.invoice.period', 'verifications.verifier', 'cashTransaction']);

        return view('payments.show', [
            'payment' => $payment,
            'accounts' => CashAccount::where('status', 'active')->orderByDesc('is_default')->get(),
        ]);
    }

    public function verify(Request $request, Payment $payment): RedirectResponse
    {
        Gate::authorize('payments.verify');
        $validated = $request->validate([
            'cash_account_id' => ['nullable', 'uuid', 'exists:cash_accounts,id'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->payments->verify($payment, $request->user(), $validated['notes'] ?? null, $validated['cash_account_id'] ?? null);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('payments.index')->with('success', "Pembayaran {$payment->payment_number} diverifikasi; tagihan lunas & kas bertambah.");
    }

    public function reject(Request $request, Payment $payment): RedirectResponse
    {
        Gate::authorize('payments.verify');
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
            'action' => ['required', 'in:reject,correction'],
        ], [], ['reason' => 'alasan']);

        try {
            $validated['action'] === 'correction'
                ? $this->payments->requestCorrection($payment, $request->user(), $validated['reason'])
                : $this->payments->reject($payment, $request->user(), $validated['reason']);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('payments.index')->with('success', $validated['action'] === 'correction' ? 'Warga diminta memperbaiki bukti.' : 'Pembayaran ditolak.');
    }

    public function reverse(Request $request, Payment $payment): RedirectResponse
    {
        Gate::authorize('payments.reverse');
        $validated = $request->validate(['reason' => ['required', 'string', 'max:500']], [], ['reason' => 'alasan']);

        try {
            $this->payments->reverse($payment, $request->user(), $validated['reason']);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Pembayaran dibatalkan; tagihan kembali terbuka dan kas dibalik.');
    }

    /**
     * Bendahara menerima uang tunai (S-5).
     */
    public function cash(Request $request): View
    {
        Gate::authorize('payments.verify');

        $house = $request->filled('house') ? House::find($request->input('house')) : null;

        return view('payments.cash', [
            'houses' => House::orderBy('house_code')->get(['id', 'house_code']),
            'house' => $house,
            'invoices' => $house ? $this->payments->payableInvoices($house) : collect(),
            'resident' => $house?->primaryResident(),
            'accounts' => CashAccount::where('status', 'active')->orderByDesc('is_default')->get(),
        ]);
    }

    public function storeCash(Request $request): RedirectResponse
    {
        Gate::authorize('payments.verify');

        $validated = $request->validate([
            'house_id' => ['required', 'uuid'],
            'invoice_ids' => ['required', 'array', 'min:1'],
            'invoice_ids.*' => ['uuid'],
            'payment_date' => ['required', 'date', 'before_or_equal:today'],
            'cash_account_id' => ['nullable', 'uuid', 'exists:cash_accounts,id'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [], ['invoice_ids' => 'tagihan']);

        $house = House::findOrFail($validated['house_id']);

        try {
            $payment = $this->payments->recordCash($house, $validated['invoice_ids'], $validated, $request->user());
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('payments.cash', ['house' => $house->id])
            ->with('success', "Pembayaran tunai {$payment->payment_number} {$house->house_code} tercatat & lunas.");
    }
}

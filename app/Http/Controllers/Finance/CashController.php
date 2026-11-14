<?php

namespace App\Http\Controllers\Finance;

use App\Enums\Finance\CashTransactionStatus;
use App\Http\Controllers\Controller;
use App\Models\CashAccount;
use App\Models\CashTransaction;
use App\Models\FinanceCategory;
use App\Services\Audit\AuditService;
use App\Services\Finance\CashService;
use App\Support\Money;
use App\Support\Uploads;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use RuntimeException;

/**
 * Buku kas warga (FR-FIN-001..008, S-8): saldo awal bulan, mutasi, saldo
 * akhir; pemasukan, pengeluaran (disetujui Ketua RT), dan pembalikan.
 */
class CashController extends Controller
{
    public function __construct(private readonly CashService $cash) {}

    public function index(Request $request): View
    {
        Gate::authorize('cash.view');
        $this->cash->ensureDefaults();

        $accounts = CashAccount::where('status', 'active')->orderByDesc('is_default')->orderBy('account_name')->get();
        $account = $accounts->firstWhere('id', $request->input('account')) ?? $accounts->first();
        $month = preg_match('/^\d{4}-\d{2}$/', (string) $request->input('month'))
            ? Carbon::createFromFormat('!Y-m', $request->input('month'))
            : now()->startOfMonth();

        $transactions = CashTransaction::with(['category', 'recorder:id,name', 'approver:id,name', 'payment'])
            ->where('cash_account_id', $account->id)
            ->whereBetween('transaction_date', [$month->toDateString(), $month->copy()->endOfMonth()->toDateString()])
            ->orderBy('transaction_date')->orderBy('created_at')
            ->get();

        $opening = $this->cash->balance($account, $month->copy()->subDay());
        $running = $opening;
        $rows = $transactions->map(function (CashTransaction $t) use (&$running) {
            if ($t->status === CashTransactionStatus::Posted) {
                $running = $t->transaction_type === 'in' ? $running->plus(Money::of($t->amount)) : $running->minus(Money::of($t->amount));
            }

            return ['tx' => $t, 'balance' => $running];
        });

        return view('finance.cash.index', [
            'accounts' => $accounts,
            'account' => $account,
            'month' => $month,
            'opening' => $opening,
            'closing' => $running,
            'rows' => $rows,
            'balances' => $accounts->mapWithKeys(fn ($a) => [$a->id => $this->cash->balance($a)]),
            'pending' => CashTransaction::with(['category', 'recorder:id,name', 'account'])->where('status', CashTransactionStatus::PendingApproval)->orderBy('transaction_date')->get(),
            'categories' => FinanceCategory::where('status', 'active')->orderBy('name')->get()->groupBy('transaction_type'),
            'totalIn' => Money::sum($transactions->where('status', CashTransactionStatus::Posted)->where('transaction_type', 'in')->pluck('amount')),
            'totalOut' => Money::sum($transactions->where('status', CashTransactionStatus::Posted)->where('transaction_type', 'out')->pluck('amount')),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('cash.record');

        $validated = $request->validate([
            'transaction_type' => ['required', 'in:in,out'],
            'cash_account_id' => ['required', 'uuid', 'exists:cash_accounts,id'],
            'finance_category_id' => ['required', 'uuid', 'exists:finance_categories,id'],
            'transaction_date' => ['required', 'date', 'before_or_equal:today'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:10000000000'],
            'description' => ['required', 'string', 'max:500'],
            'counterparty' => ['nullable', 'string', 'max:150'],
            'evidence' => ['nullable', ...Uploads::documentRules()],
        ], [], ['finance_category_id' => 'kategori', 'amount' => 'nominal', 'description' => 'keterangan', 'evidence' => 'bukti']);

        $data = [...$validated, 'amount' => (string) $validated['amount']];

        try {
            $tx = $validated['transaction_type'] === 'in'
                ? $this->cash->recordIncome($data, $request->user(), $request->file('evidence'))
                : $this->cash->recordExpense($data, $request->user(), $request->file('evidence'));
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', $tx->transaction_type === 'in'
            ? 'Pemasukan dicatat.'
            : 'Pengeluaran dicatat dan menunggu persetujuan Ketua RT.');
    }

    public function approve(Request $request, CashTransaction $transaction): RedirectResponse
    {
        Gate::authorize('cash.approve');

        try {
            $this->cash->approve($transaction, $request->user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Pengeluaran disetujui dan tercatat di buku kas.');
    }

    public function reject(Request $request, CashTransaction $transaction): RedirectResponse
    {
        Gate::authorize('cash.approve');
        $validated = $request->validate(['reason' => ['required', 'string', 'max:500']], [], ['reason' => 'alasan']);

        try {
            $this->cash->reject($transaction, $request->user(), $validated['reason']);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Pengeluaran ditolak.');
    }

    public function reverse(Request $request, CashTransaction $transaction): RedirectResponse
    {
        Gate::authorize('cash.reverse');
        $validated = $request->validate(['reason' => ['required', 'string', 'max:500']], [], ['reason' => 'alasan']);

        if ($transaction->payment_id) {
            return back()->with('error', 'Penerimaan dari pembayaran warga dibatalkan lewat halaman Pembayaran (agar tagihannya ikut terbuka kembali).');
        }

        try {
            $this->cash->reverse($transaction, $request->user(), $validated['reason']);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Transaksi pembalik dicatat.');
    }

    public function storeAccount(Request $request): RedirectResponse
    {
        Gate::authorize('cash-accounts.manage');

        $validated = $request->validate([
            'account_name' => ['required', 'string', 'max:100'],
            'account_code' => ['required', 'string', 'max:50', 'unique:cash_accounts,account_code'],
            'account_type' => ['required', 'in:cash,bank'],
            'opening_balance' => ['required', 'numeric', 'min:0'],
            'opening_date' => ['required', 'date'],
        ], [], ['account_name' => 'nama akun', 'opening_balance' => 'saldo awal']);

        $account = CashAccount::create([...$validated, 'account_code' => strtoupper($validated['account_code']), 'opening_balance' => Money::toDb((string) $validated['opening_balance']), 'status' => 'active']);
        app(AuditService::class)->created('cash.account_created', $account);

        return back()->with('success', "Akun {$account->account_name} dibuat.");
    }
}

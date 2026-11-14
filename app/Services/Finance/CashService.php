<?php

namespace App\Services\Finance;

use App\Enums\Finance\CashTransactionStatus;
use App\Models\CashAccount;
use App\Models\CashTransaction;
use App\Models\FinanceCategory;
use App\Models\Payment;
use App\Models\User;
use App\Services\Audit\AuditService;
use App\Services\CodeGeneratorService;
use App\Services\Files\FileService;
use App\Support\Money;
use Brick\Math\BigDecimal;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Buku kas warga (FR-FIN-001..008, Q12, D-07, S-8).
 *
 * - Pemasukan langsung tercatat (posted).
 * - Pengeluaran menunggu persetujuan Ketua RT (izin cash.approve); pencatat
 *   tidak boleh menyetujui transaksinya sendiri; saldo dicek saat disetujui.
 * - Transaksi posted tidak diubah/dihapus: koreksi = transaksi pembalik.
 * - Saldo = saldo awal + Σ masuk posted − Σ keluar posted (tidak disimpan).
 */
class CashService
{
    public const PAYMENT_CATEGORY = 'TAGIHAN';

    public const EVIDENCE_CATEGORY = 'cash_evidence';

    public const DEFAULT_CATEGORIES = [
        'in' => [
            'TAGIHAN' => 'Pembayaran tagihan warga',
            'SUMBANGAN' => 'Sumbangan / donasi',
            'KEGIATAN_MASUK' => 'Pemasukan kegiatan',
            'LAIN_MASUK' => 'Pemasukan lainnya',
        ],
        'out' => [
            'OPERASIONAL' => 'Operasional',
            'PEMELIHARAAN' => 'Pemeliharaan',
            'PETUGAS' => 'Honor petugas',
            'KEGIATAN' => 'Kegiatan warga',
            'PENGADAAN' => 'Pengadaan',
            'SOSIAL' => 'Santunan / sosial',
            'LAIN_KELUAR' => 'Pengeluaran lainnya',
        ],
    ];

    public function __construct(
        private readonly CodeGeneratorService $codes,
        private readonly FileService $files,
        private readonly AuditService $audit,
    ) {}

    /**
     * Akun kas default & kategori standar (aman dipanggil berulang).
     */
    public function ensureDefaults(): CashAccount
    {
        foreach (self::DEFAULT_CATEGORIES as $type => $categories) {
            foreach ($categories as $code => $name) {
                FinanceCategory::firstOrCreate(['code' => $code], ['name' => $name, 'transaction_type' => $type, 'status' => 'active']);
            }
        }

        return $this->defaultAccount() ?? CashAccount::create([
            'account_code' => 'KAS',
            'account_name' => 'Kas Bendahara',
            'account_type' => 'cash',
            'opening_balance' => '0.00',
            'opening_date' => today()->startOfMonth()->toDateString(),
            'is_default' => true,
            'status' => 'active',
        ]);
    }

    public function defaultAccount(): ?CashAccount
    {
        return CashAccount::where('status', 'active')->orderByDesc('is_default')->orderBy('created_at')->first();
    }

    public function balance(CashAccount $account, ?CarbonInterface $until = null): BigDecimal
    {
        $sums = CashTransaction::query()
            ->where('cash_account_id', $account->getKey())
            ->where('status', CashTransactionStatus::Posted)
            ->when($until, fn ($q) => $q->whereDate('transaction_date', '<=', $until->toDateString()))
            ->selectRaw("COALESCE(SUM(CASE WHEN transaction_type = 'in' THEN amount ELSE 0 END), 0) AS total_in")
            ->selectRaw("COALESCE(SUM(CASE WHEN transaction_type = 'out' THEN amount ELSE 0 END), 0) AS total_out")
            ->first();

        return Money::of($account->opening_balance)->plus(Money::of($sums->total_in))->minus(Money::of($sums->total_out));
    }

    /**
     * @param  array{cash_account_id: string, finance_category_id: string, transaction_date: string, amount: string|int, description: string, counterparty?: ?string}  $data
     */
    public function recordIncome(array $data, User $user, ?UploadedFile $evidence = null): CashTransaction
    {
        return $this->record('in', $data, $user, $evidence, CashTransactionStatus::Posted);
    }

    /**
     * Pengeluaran selalu menunggu persetujuan (Q12).
     *
     * @param  array{cash_account_id: string, finance_category_id: string, transaction_date: string, amount: string|int, description: string, counterparty?: ?string}  $data
     */
    public function recordExpense(array $data, User $user, ?UploadedFile $evidence = null): CashTransaction
    {
        return $this->record('out', $data, $user, $evidence, CashTransactionStatus::PendingApproval);
    }

    public function approve(CashTransaction $transaction, User $approver): CashTransaction
    {
        return DB::transaction(function () use ($transaction, $approver) {
            $transaction = CashTransaction::whereKey($transaction->getKey())->lockForUpdate()->firstOrFail();
            $transaction->status->assertCanTransitionTo(CashTransactionStatus::Posted);

            if ($transaction->recorded_by === $approver->getKey()) {
                throw new RuntimeException('Pengeluaran tidak boleh disetujui oleh pencatatnya sendiri.');
            }

            $account = CashAccount::whereKey($transaction->cash_account_id)->lockForUpdate()->firstOrFail();
            $balance = $this->balance($account);
            if ($balance->isLessThan(Money::of($transaction->amount))) {
                throw new RuntimeException('Saldo '.$account->account_name.' ('.Money::format($balance).') tidak mencukupi untuk pengeluaran ini (EX-007).');
            }

            $old = $transaction->only(['status']);
            $transaction->fill([
                'status' => CashTransactionStatus::Posted,
                'approved_by' => $approver->getKey(),
                'approved_at' => now(),
            ])->save();
            $this->audit->changed('cash.approved', $transaction, null, $old);

            return $transaction;
        });
    }

    public function reject(CashTransaction $transaction, User $approver, string $reason): CashTransaction
    {
        return DB::transaction(function () use ($transaction, $approver, $reason) {
            $transaction = CashTransaction::whereKey($transaction->getKey())->lockForUpdate()->firstOrFail();
            $transaction->status->assertCanTransitionTo(CashTransactionStatus::Rejected);

            $old = $transaction->only(['status']);
            $transaction->fill([
                'status' => CashTransactionStatus::Rejected,
                'approved_by' => $approver->getKey(),
                'approved_at' => now(),
                'rejection_reason' => $reason,
            ])->save();
            $this->audit->changed('cash.rejected', $transaction, $reason, $old);

            return $transaction;
        });
    }

    /**
     * Transaksi pembalik (FR-FIN-007). Transaksi asal tetap di buku kas.
     */
    public function reverse(CashTransaction $transaction, User $user, string $reason): CashTransaction
    {
        return DB::transaction(function () use ($transaction, $user, $reason) {
            $transaction = CashTransaction::whereKey($transaction->getKey())->lockForUpdate()->firstOrFail();

            if ($transaction->status !== CashTransactionStatus::Posted) {
                throw new RuntimeException('Hanya transaksi yang sudah tercatat yang bisa dibalik.');
            }
            if ($transaction->reversal_of_id !== null) {
                throw new RuntimeException('Transaksi pembalik tidak bisa dibalik lagi.');
            }
            if (CashTransaction::where('reversal_of_id', $transaction->getKey())->exists()) {
                throw new RuntimeException('Transaksi ini sudah dibalik.');
            }

            $reversal = CashTransaction::create([
                'cash_account_id' => $transaction->cash_account_id,
                'finance_category_id' => $transaction->finance_category_id,
                'transaction_number' => $this->codes->generate('KAS', 'cash_transactions', 'transaction_number'),
                'transaction_date' => today()->toDateString(),
                'transaction_type' => $transaction->transaction_type === 'in' ? 'out' : 'in',
                'amount' => $transaction->amount,
                'description' => "Pembalikan {$transaction->transaction_number}: {$reason}",
                'counterparty' => $transaction->counterparty,
                'reversal_of_id' => $transaction->getKey(),
                'status' => CashTransactionStatus::Posted,
                'recorded_by' => $user->getKey(),
                'approved_by' => $user->getKey(),
                'approved_at' => now(),
            ]);
            $this->audit->created('cash.reversed', $reversal, ['reason' => $reason, 'original' => $transaction->transaction_number]);

            return $reversal;
        });
    }

    /**
     * Penerimaan kas dari pembayaran terverifikasi — dipanggil DI DALAM
     * transaksi verifikasi (Dok 05 §8.2). payment_id unik mencegah posting
     * ganda (BR-015, EX-006).
     */
    public function postPaymentReceipt(Payment $payment, User $user, string $description): CashTransaction
    {
        $this->ensureDefaults();
        $account = $payment->cash_account_id ? CashAccount::findOrFail($payment->cash_account_id) : $this->defaultAccount();
        $category = FinanceCategory::where('code', self::PAYMENT_CATEGORY)->firstOrFail();

        return CashTransaction::create([
            'cash_account_id' => $account->getKey(),
            'finance_category_id' => $category->getKey(),
            'transaction_number' => $this->codes->generate('KAS', 'cash_transactions', 'transaction_number'),
            'transaction_date' => $payment->payment_date->toDateString(),
            'transaction_type' => 'in',
            'amount' => $payment->amount,
            'description' => $description,
            'counterparty' => $payment->house?->house_code,
            'payment_id' => $payment->getKey(),
            'status' => CashTransactionStatus::Posted,
            'recorded_by' => $user->getKey(),
            'approved_by' => $user->getKey(),
            'approved_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function record(string $type, array $data, User $user, ?UploadedFile $evidence, CashTransactionStatus $status): CashTransaction
    {
        $amount = Money::of($data['amount']);
        if (! $amount->isPositive()) {
            throw new RuntimeException('Nominal harus lebih besar dari nol.');
        }

        $category = FinanceCategory::findOrFail($data['finance_category_id']);
        if ($category->transaction_type !== $type) {
            throw new RuntimeException('Kategori tidak sesuai jenis transaksi.');
        }
        CashAccount::findOrFail($data['cash_account_id']);

        return DB::transaction(function () use ($type, $data, $user, $evidence, $status, $amount) {
            $file = $evidence ? $this->files->store($evidence, self::EVIDENCE_CATEGORY) : null;

            $transaction = CashTransaction::create([
                'cash_account_id' => $data['cash_account_id'],
                'finance_category_id' => $data['finance_category_id'],
                'transaction_number' => $this->codes->generate('KAS', 'cash_transactions', 'transaction_number'),
                'transaction_date' => $data['transaction_date'],
                'transaction_type' => $type,
                'amount' => (string) $amount,
                'description' => $data['description'],
                'counterparty' => $data['counterparty'] ?? null,
                'evidence_file_id' => $file?->getKey(),
                'status' => $status,
                'recorded_by' => $user->getKey(),
                'approved_by' => $status === CashTransactionStatus::Posted ? $user->getKey() : null,
                'approved_at' => $status === CashTransactionStatus::Posted ? now() : null,
            ]);
            $this->audit->created($type === 'in' ? 'cash.income_recorded' : 'cash.expense_recorded', $transaction);

            return $transaction;
        });
    }
}

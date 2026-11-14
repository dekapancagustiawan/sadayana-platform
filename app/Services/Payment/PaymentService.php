<?php

namespace App\Services\Payment;

use App\Enums\Billing\InvoiceStatus;
use App\Enums\Payment\PaymentStatus;
use App\Models\House;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\PaymentVerification;
use App\Models\User;
use App\Services\Audit\AuditService;
use App\Services\Billing\BillingService;
use App\Services\CodeGeneratorService;
use App\Services\Communication\NotificationService;
use App\Services\Files\FileService;
use App\Services\Finance\CashService;
use App\Support\Money;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Pembayaran tagihan (FR-PAY-001..006, BR-011..015, Q10, S-5).
 *
 * - Satu pembayaran melunasi satu ATAU beberapa invoice sekaligus; nominal
 *   = total sisa tagihan terpilih (tidak ada pembayaran parsial).
 * - Pengajuan tidak mengubah invoice (BR-012); invoice yang sedang diajukan
 *   tidak bisa diajukan lagi.
 * - verify() dalam SATU transaksi: lunasi invoice + posting SATU penerimaan
 *   kas (payment_id unik → tidak ada posting ganda, EX-006).
 * - Tunai diterima langsung bendahara → dicatat & terverifikasi sekaligus.
 */
class PaymentService
{
    public const PROOF_CATEGORY = 'payment_proof';

    public function __construct(
        private readonly CashService $cash,
        private readonly BillingService $billing,
        private readonly FileService $files,
        private readonly CodeGeneratorService $codes,
        private readonly NotificationService $notifications,
        private readonly AuditService $audit,
    ) {}

    /**
     * Invoice rumah ini yang bisa dibayar sekarang.
     *
     * @return EloquentCollection<int, Invoice>
     */
    public function payableInvoices(House $house): EloquentCollection
    {
        return Invoice::query()
            ->with(['period', 'contributionType'])
            ->where('house_id', $house->getKey())
            ->whereIn('status', [InvoiceStatus::Issued, InvoiceStatus::Overdue, InvoiceStatus::PartiallyPaid])
            ->where('outstanding_amount', '>', 0)
            ->whereDoesntHave('allocations.payment', fn ($q) => $q->whereIn('status', [PaymentStatus::PendingVerification, PaymentStatus::CorrectionRequired]))
            ->orderBy('due_date')
            ->get();
    }

    /**
     * Warga mengajukan pembayaran transfer dengan bukti.
     *
     * @param  list<string>  $invoiceIds
     * @param  array{payment_date: string, external_reference?: ?string, notes?: ?string}  $data
     */
    public function submit(House $house, array $invoiceIds, array $data, UploadedFile $proof, User $payer): Payment
    {
        $payment = DB::transaction(function () use ($house, $invoiceIds, $data, $proof, $payer) {
            $invoices = $this->lockPayable($house, $invoiceIds);
            $amount = Money::sum($invoices->pluck('outstanding_amount'));

            $reference = trim((string) ($data['external_reference'] ?? '')) ?: null;
            if ($reference) {
                $duplicate = Payment::where('external_reference', $reference)
                    ->whereNotIn('status', [PaymentStatus::Rejected, PaymentStatus::Cancelled])
                    ->exists();
                if ($duplicate) {
                    throw new RuntimeException('Nomor referensi transfer ini sudah pernah diajukan (EX-012).');
                }
            }

            $file = $this->files->store($proof, self::PROOF_CATEGORY);

            $payment = Payment::create([
                'payment_number' => $this->codes->generate('PAY', 'payments', 'payment_number'),
                'house_id' => $house->getKey(),
                'payer_user_id' => $payer->getKey(),
                'payment_date' => $data['payment_date'],
                'amount' => (string) $amount,
                'payment_method' => 'transfer',
                'external_reference' => $reference,
                'proof_file_id' => $file->getKey(),
                'status' => PaymentStatus::PendingVerification,
                'submitted_at' => now(),
                'notes' => $data['notes'] ?? null,
            ]);
            $this->allocate($payment, $invoices);
            $this->audit->created('payments.submitted', $payment, ['invoices' => $invoices->pluck('invoice_number')->all()]);

            return $payment;
        });

        $this->notifications->notifyPermission(
            'payments.verify',
            'Pembayaran baru menunggu verifikasi',
            "{$house->house_code} mengajukan pembayaran ".Money::format($payment->amount).'.',
            route('payments.show', $payment),
            'fas fa-money-check-alt',
            'payment_submitted',
        );

        return $payment;
    }

    /**
     * Warga memperbaiki pengajuan yang diminta perbaikan.
     */
    public function resubmit(Payment $payment, UploadedFile $proof, ?string $reference, User $payer): Payment
    {
        return DB::transaction(function () use ($payment, $proof, $reference, $payer) {
            $payment = Payment::whereKey($payment->getKey())->lockForUpdate()->firstOrFail();
            $payment->status->assertCanTransitionTo(PaymentStatus::PendingVerification);

            $file = $this->files->store($proof, self::PROOF_CATEGORY);
            $payment->fill([
                'proof_file_id' => $file->getKey(),
                'external_reference' => $reference ?: $payment->external_reference,
                'status' => PaymentStatus::PendingVerification,
                'submitted_at' => now(),
            ])->save();
            $this->audit->record('payments.resubmitted', $payment, ['by' => $payer->getKey()]);

            return $payment;
        });
    }

    /**
     * Bendahara mencatat uang tunai yang ia terima langsung (S-5): dicatat
     * dan terverifikasi dalam satu langkah.
     *
     * @param  list<string>  $invoiceIds
     * @param  array{payment_date: string, notes?: ?string, cash_account_id?: ?string}  $data
     */
    public function recordCash(House $house, array $invoiceIds, array $data, User $treasurer): Payment
    {
        return DB::transaction(function () use ($house, $invoiceIds, $data, $treasurer) {
            $invoices = $this->lockPayable($house, $invoiceIds);

            $payment = Payment::create([
                'payment_number' => $this->codes->generate('PAY', 'payments', 'payment_number'),
                'house_id' => $house->getKey(),
                'payment_date' => $data['payment_date'],
                'amount' => (string) Money::sum($invoices->pluck('outstanding_amount')),
                'payment_method' => 'cash',
                'cash_account_id' => $data['cash_account_id'] ?? null,
                'status' => PaymentStatus::PendingVerification,
                'submitted_at' => now(),
                'notes' => $data['notes'] ?? null,
            ]);
            $this->allocate($payment, $invoices);
            $this->audit->created('payments.cash_recorded', $payment);

            return $this->verify($payment, $treasurer, 'Pembayaran tunai diterima bendahara.');
        });
    }

    public function verify(Payment $payment, User $verifier, ?string $notes = null, ?string $cashAccountId = null): Payment
    {
        $payment = DB::transaction(function () use ($payment, $verifier, $notes, $cashAccountId) {
            $payment = Payment::whereKey($payment->getKey())->lockForUpdate()->firstOrFail();
            // Idempoten: pembayaran yang sudah diverifikasi melempar di sini.
            $payment->status->assertCanTransitionTo(PaymentStatus::Verified);

            $allocations = $payment->allocations()->get();
            $invoices = Invoice::whereIn('id', $allocations->pluck('invoice_id'))->lockForUpdate()->get()->keyBy('id');

            foreach ($allocations as $allocation) {
                $invoice = $invoices[$allocation->invoice_id];
                if (! $invoice->status->isPayable() || ! Money::of($invoice->outstanding_amount)->isEqualTo(Money::of($allocation->allocated_amount))) {
                    throw new RuntimeException("Tagihan {$invoice->invoice_number} sudah berubah (lunas/dibatalkan). Tolak pengajuan ini.");
                }

                $invoice->status->assertCanTransitionTo(InvoiceStatus::Paid);
                $invoice->fill([
                    'paid_amount' => Money::toDb(Money::of($invoice->paid_amount)->plus(Money::of($allocation->allocated_amount))),
                    'outstanding_amount' => '0.00',
                    'status' => InvoiceStatus::Paid,
                    'paid_at' => now(),
                ])->save();
            }

            if ($cashAccountId) {
                $payment->cash_account_id = $cashAccountId;
            }
            $payment->fill([
                'status' => PaymentStatus::Verified,
                'verified_at' => now(),
                'verified_by' => $verifier->getKey(),
            ])->save();

            $this->cash->postPaymentReceipt(
                $payment->load('house'),
                $verifier,
                "Pembayaran {$payment->payment_number} {$payment->house->house_code}: ".$invoices->map->title()->unique()->join(', '),
            );

            $this->logVerification($payment, 'approved', $notes, $verifier);
            $this->audit->record('payments.verified', $payment, ['amount' => $payment->amount, 'notes' => $notes]);

            return $payment;
        });

        $this->notifyPayer($payment, 'Pembayaran diterima', 'Pembayaran '.Money::format($payment->amount).' untuk '.$payment->house->house_code.' sudah diverifikasi. Terima kasih.', 'fas fa-check-circle');

        return $payment;
    }

    public function reject(Payment $payment, User $verifier, string $reason): Payment
    {
        $payment = $this->transition($payment, PaymentStatus::Rejected, $verifier, $reason, 'rejected');
        $this->notifyPayer($payment, 'Pembayaran ditolak', "Pengajuan {$payment->payment_number} ditolak: {$reason}", 'fas fa-times-circle');

        return $payment;
    }

    public function requestCorrection(Payment $payment, User $verifier, string $reason): Payment
    {
        $payment = $this->transition($payment, PaymentStatus::CorrectionRequired, $verifier, $reason, 'correction_required');
        $this->notifyPayer($payment, 'Bukti pembayaran perlu diperbaiki', "Pengajuan {$payment->payment_number}: {$reason}", 'fas fa-exclamation-circle');

        return $payment;
    }

    public function cancel(Payment $payment, User $user): Payment
    {
        return $this->transition($payment, PaymentStatus::Cancelled, $user, 'Dibatalkan oleh pengaju.', 'cancelled');
    }

    /**
     * Batalkan pembayaran terverifikasi (izin payments.reverse): tagihan
     * kembali terbuka dan penerimaan kas dibalik (FR-PAY-006).
     */
    public function reverse(Payment $payment, User $user, string $reason): Payment
    {
        return DB::transaction(function () use ($payment, $user, $reason) {
            $payment = Payment::whereKey($payment->getKey())->lockForUpdate()->firstOrFail();
            $payment->status->assertCanTransitionTo(PaymentStatus::Reversed);

            foreach ($payment->allocations()->get() as $allocation) {
                $invoice = Invoice::whereKey($allocation->invoice_id)->lockForUpdate()->firstOrFail();
                $invoice->fill([
                    'paid_amount' => Money::toDb(Money::of($invoice->paid_amount)->minus(Money::of($allocation->allocated_amount))),
                    'outstanding_amount' => Money::toDb(Money::of($invoice->outstanding_amount)->plus(Money::of($allocation->allocated_amount))),
                    'paid_at' => null,
                ]);
                $invoice->status = $this->billing->openStatusFor($invoice);
                $invoice->save();
            }

            if ($receipt = $payment->cashTransaction()->first()) {
                $this->cash->reverse($receipt, $user, "Pembatalan pembayaran {$payment->payment_number}: {$reason}");
            }

            $payment->fill(['status' => PaymentStatus::Reversed, 'rejection_reason' => $reason])->save();
            $this->logVerification($payment, 'reversed', $reason, $user);
            $this->audit->record('payments.reversed', $payment, ['reason' => $reason]);

            return $payment;
        });
    }

    /**
     * @param  list<string>  $invoiceIds
     * @return EloquentCollection<int, Invoice>
     */
    private function lockPayable(House $house, array $invoiceIds): EloquentCollection
    {
        $invoiceIds = array_values(array_unique(array_filter($invoiceIds)));
        if ($invoiceIds === []) {
            throw new RuntimeException('Pilih minimal satu tagihan.');
        }

        $invoices = Invoice::whereIn('id', $invoiceIds)->lockForUpdate()->get();
        $payable = $this->payableInvoices($house)->pluck('id');

        if ($invoices->count() !== count($invoiceIds) || $invoices->contains(fn (Invoice $i) => ! $payable->contains($i->getKey()))) {
            throw new RuntimeException('Sebagian tagihan yang dipilih sudah lunas, dibatalkan, sedang diajukan, atau bukan milik rumah ini. Muat ulang halaman.');
        }

        return $invoices;
    }

    /**
     * @param  EloquentCollection<int, Invoice>  $invoices
     */
    private function allocate(Payment $payment, EloquentCollection $invoices): void
    {
        foreach ($invoices as $invoice) {
            PaymentAllocation::create([
                'payment_id' => $payment->getKey(),
                'invoice_id' => $invoice->getKey(),
                'allocated_amount' => Money::toDb($invoice->outstanding_amount),
            ]);
        }
    }

    private function transition(Payment $payment, PaymentStatus $to, User $user, string $reason, string $verification): Payment
    {
        return DB::transaction(function () use ($payment, $to, $user, $reason, $verification) {
            $payment = Payment::whereKey($payment->getKey())->lockForUpdate()->firstOrFail();
            $payment->status->assertCanTransitionTo($to);

            $old = $payment->only(['status']);
            $payment->fill(['status' => $to, 'rejection_reason' => $reason])->save();
            $this->logVerification($payment, $verification, $reason, $user);
            $this->audit->changed('payments.'.$verification, $payment, $reason, $old);

            return $payment;
        });
    }

    private function logVerification(Payment $payment, string $status, ?string $notes, User $user): void
    {
        PaymentVerification::create([
            'payment_id' => $payment->getKey(),
            'verification_status' => $status,
            'notes' => $notes,
            'verified_by' => $user->getKey(),
            'verified_at' => now(),
        ]);
    }

    private function notifyPayer(Payment $payment, string $title, string $message, string $icon): void
    {
        $house = $payment->house ?? House::find($payment->house_id);
        if ($house) {
            $this->notifications->notifyHouse($house, $title, $message, route('my-bills.index'), $icon, 'payment');
        }
    }
}

<?php

namespace App\Models;

use App\Enums\Finance\CashTransactionStatus;
use App\Traits\BelongsToCommunity;
use App\Traits\Blameable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Buku kas (FR-FIN-003..007). Tidak pernah dihapus atau diubah setelah posted;
 * koreksi = transaksi pembalik (reversal_of_id).
 */
class CashTransaction extends Model
{
    use BelongsToCommunity, Blameable, HasUuids;

    /** @var list<string> */
    protected $fillable = ['cash_account_id', 'finance_category_id', 'transaction_number', 'transaction_date', 'transaction_type', 'amount', 'description', 'counterparty', 'payment_id', 'reversal_of_id', 'evidence_file_id', 'status', 'recorded_by', 'approved_by', 'approved_at', 'rejection_reason'];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'amount' => 'decimal:2',
            'status' => CashTransactionStatus::class,
            'approved_at' => 'datetime',
            'recorded_by' => 'integer',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class, 'cash_account_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(FinanceCategory::class, 'finance_category_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(CashTransaction::class, 'reversal_of_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function reversal(): HasOne
    {
        return $this->hasOne(CashTransaction::class, 'reversal_of_id');
    }
}

<?php

namespace App\Models;

use App\Enums\Billing\InvoiceStatus;
use App\Enums\Billing\InvoiceType;
use App\Traits\BelongsToCommunity;
use App\Traits\Blameable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tagihan satu jenis untuk satu rumah pada satu periode (FR-BIL-002, Q8).
 * Nominal & status hanya diubah lewat InvoiceService / PaymentService.
 */
class Invoice extends Model
{
    use BelongsToCommunity, Blameable, HasUuids;

    /** @var list<string> */
    protected $fillable = ['invoice_number', 'house_id', 'billing_period_id', 'invoice_type', 'billing_key', 'contribution_type_id', 'invoice_date', 'due_date', 'total_amount', 'paid_amount', 'outstanding_amount', 'status', 'issued_at', 'issued_by', 'paid_at', 'cancelled_at', 'cancelled_by', 'cancellation_reason'];

    protected function casts(): array
    {
        return [
            'invoice_type' => InvoiceType::class,
            'status' => InvoiceStatus::class,
            'invoice_date' => 'date',
            'due_date' => 'date',
            'total_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'outstanding_amount' => 'decimal:2',
            'issued_at' => 'datetime',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function house(): BelongsTo
    {
        return $this->belongsTo(House::class)->withTrashed();
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(BillingPeriod::class, 'billing_period_id');
    }

    public function contributionType(): BelongsTo
    {
        return $this->belongsTo(ContributionType::class, 'contribution_type_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(InvoiceDetail::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function title(): string
    {
        return $this->invoice_type === InvoiceType::Contribution && $this->contributionType
            ? $this->contributionType->name
            : $this->invoice_type->label();
    }
}

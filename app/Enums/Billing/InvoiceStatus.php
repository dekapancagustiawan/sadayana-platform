<?php

namespace App\Enums\Billing;

use App\Enums\Concerns\HasTransitions;

/**
 * Lifecycle invoice (SRS §5.2, Dok 06 §22.1). `partially_paid` disimpan
 * untuk masa depan; selama pembayaran parsial tidak diizinkan (Q10) tidak
 * pernah dicapai.
 */
enum InvoiceStatus: string
{
    use HasTransitions;

    case Draft = 'draft';
    case Issued = 'issued';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Overdue = 'overdue';
    case Cancelled = 'cancelled';

    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Issued, self::Cancelled],
            self::Issued => [self::Paid, self::PartiallyPaid, self::Overdue, self::Cancelled],
            self::PartiallyPaid => [self::Paid, self::Overdue, self::Issued],
            self::Overdue => [self::Paid, self::PartiallyPaid, self::Cancelled],
            // Lunas hanya kembali terbuka lewat pembalikan pembayaran (FR-BIL-004).
            self::Paid => [self::Issued, self::Overdue],
            self::Cancelled => [],
        };
    }

    /** Masih bisa dibayar. */
    public function isPayable(): bool
    {
        return in_array($this, [self::Issued, self::Overdue, self::PartiallyPaid], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Issued => 'Belum dibayar',
            self::PartiallyPaid => 'Dibayar sebagian',
            self::Paid => 'Lunas',
            self::Overdue => 'Lewat jatuh tempo',
            self::Cancelled => 'Dibatalkan',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Draft => 'secondary',
            self::Issued => 'warning',
            self::PartiallyPaid => 'info',
            self::Paid => 'success',
            self::Overdue => 'danger',
            self::Cancelled => 'dark',
        };
    }
}

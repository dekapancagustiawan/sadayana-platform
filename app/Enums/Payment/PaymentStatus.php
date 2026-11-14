<?php

namespace App\Enums\Payment;

use App\Enums\Concerns\HasTransitions;

/**
 * Lifecycle pembayaran (SRS §5.3, Dok 06 §22.1).
 */
enum PaymentStatus: string
{
    use HasTransitions;

    case PendingVerification = 'pending_verification';
    case CorrectionRequired = 'correction_required';
    case Verified = 'verified';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Reversed = 'reversed';

    public function allowedTransitions(): array
    {
        return match ($this) {
            self::PendingVerification => [self::Verified, self::Rejected, self::CorrectionRequired, self::Cancelled],
            self::CorrectionRequired => [self::PendingVerification, self::Rejected, self::Cancelled],
            self::Verified => [self::Reversed],
            self::Rejected, self::Cancelled, self::Reversed => [],
        };
    }

    /** Masih "memegang" invoice-nya (invoice tidak boleh diajukan lagi). */
    public function isOpen(): bool
    {
        return in_array($this, [self::PendingVerification, self::CorrectionRequired], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::PendingVerification => 'Menunggu verifikasi',
            self::CorrectionRequired => 'Perlu perbaikan',
            self::Verified => 'Terverifikasi',
            self::Rejected => 'Ditolak',
            self::Cancelled => 'Dibatalkan',
            self::Reversed => 'Dibatalkan (dibalik)',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::PendingVerification => 'warning',
            self::CorrectionRequired => 'info',
            self::Verified => 'success',
            self::Rejected => 'danger',
            self::Cancelled, self::Reversed => 'dark',
        };
    }
}

<?php

namespace App\Enums\Finance;

use App\Enums\Concerns\HasTransitions;

/**
 * Lifecycle transaksi kas (SRS §5.4). Pemasukan langsung `posted`;
 * pengeluaran menunggu persetujuan Ketua RT (Q12). Koreksi = transaksi
 * pembalik, bukan perubahan status.
 */
enum CashTransactionStatus: string
{
    use HasTransitions;

    case PendingApproval = 'pending_approval';
    case Posted = 'posted';
    case Rejected = 'rejected';

    public function allowedTransitions(): array
    {
        return match ($this) {
            self::PendingApproval => [self::Posted, self::Rejected],
            self::Posted, self::Rejected => [],
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::PendingApproval => 'Menunggu persetujuan',
            self::Posted => 'Tercatat',
            self::Rejected => 'Ditolak',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::PendingApproval => 'warning',
            self::Posted => 'success',
            self::Rejected => 'danger',
        };
    }
}

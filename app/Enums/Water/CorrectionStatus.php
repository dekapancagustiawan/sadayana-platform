<?php

namespace App\Enums\Water;

use App\Enums\Concerns\HasTransitions;

enum CorrectionStatus: string
{
    use HasTransitions;

    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Approved, self::Rejected],
            self::Approved, self::Rejected => [],
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu',
            self::Approved => 'Disetujui',
            self::Rejected => 'Ditolak',
        };
    }
}

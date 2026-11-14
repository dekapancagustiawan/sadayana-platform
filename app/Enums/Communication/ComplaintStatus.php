<?php

namespace App\Enums\Communication;

use App\Enums\Concerns\HasTransitions;

enum ComplaintStatus: string
{
    use HasTransitions;

    case Submitted = 'submitted';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
    case Rejected = 'rejected';
    case Closed = 'closed';

    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Submitted => [self::InProgress, self::Resolved, self::Rejected],
            self::InProgress => [self::Resolved, self::Rejected],
            self::Resolved => [self::Closed, self::InProgress],
            self::Rejected, self::Closed => [],
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Baru',
            self::InProgress => 'Diproses',
            self::Resolved => 'Selesai',
            self::Rejected => 'Ditolak',
            self::Closed => 'Ditutup',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Submitted => 'warning',
            self::InProgress => 'info',
            self::Resolved => 'success',
            self::Rejected => 'danger',
            self::Closed => 'secondary',
        };
    }
}

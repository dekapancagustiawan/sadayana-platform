<?php

namespace App\Enums\Water;

use App\Enums\Concerns\HasTransitions;

/**
 * Lifecycle pembacaan meter (Dok 06 amandemen §22.1).
 *
 * Pembacaan wajar langsung `validated`; yang janggal `pending` sampai
 * diperiksa pengurus (Q5). Nilai pembacaan tervalidasi hanya berubah lewat
 * koreksi beralasan (FR-WAT-006), bukan lewat transisi status.
 */
enum MeterReadingStatus: string
{
    use HasTransitions;

    case Pending = 'pending';
    case Validated = 'validated';
    case Rejected = 'rejected';

    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Validated, self::Rejected],
            self::Validated, self::Rejected => [],
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Perlu diperiksa',
            self::Validated => 'Valid',
            self::Rejected => 'Ditolak',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Validated => 'success',
            self::Rejected => 'danger',
        };
    }
}

<?php

namespace App\Enums\Resident;

enum ResidentStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case MovedOut = 'moved_out';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktif',
            self::Inactive => 'Nonaktif',
            self::MovedOut => 'Pindah',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Inactive => 'secondary',
            self::MovedOut => 'dark',
        };
    }
}

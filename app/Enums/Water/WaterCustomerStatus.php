<?php

namespace App\Enums\Water;

enum WaterCustomerStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktif',
            self::Suspended => 'Diputus sementara',
            self::Inactive => 'Berhenti',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Suspended => 'warning',
            self::Inactive => 'secondary',
        };
    }
}

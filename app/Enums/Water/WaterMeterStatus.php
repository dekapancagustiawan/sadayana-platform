<?php

namespace App\Enums\Water;

enum WaterMeterStatus: string
{
    case Active = 'active';
    case Replaced = 'replaced';
    case Broken = 'broken';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktif',
            self::Replaced => 'Diganti',
            self::Broken => 'Rusak',
            self::Inactive => 'Nonaktif',
        };
    }
}

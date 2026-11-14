<?php

namespace App\Enums\Resident;

/**
 * Jenis penugasan petugas (D-05).
 */
enum AssignmentType: string
{
    case Water = 'water';
    case Waste = 'waste';

    public function label(): string
    {
        return match ($this) {
            self::Water => 'Pencatatan meter air',
            self::Waste => 'Layanan sampah',
        };
    }
}

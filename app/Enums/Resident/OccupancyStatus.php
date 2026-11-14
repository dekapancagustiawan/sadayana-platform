<?php

namespace App\Enums\Resident;

/**
 * Status hunian rumah (Dok 06 §3.3). `occupied`/`vacant` diperbarui otomatis
 * oleh ResidencyService saat penghuni masuk/keluar; `under_renovation` diatur
 * manual.
 */
enum OccupancyStatus: string
{
    case Occupied = 'occupied';
    case Vacant = 'vacant';
    case UnderRenovation = 'under_renovation';

    public function label(): string
    {
        return match ($this) {
            self::Occupied => 'Dihuni',
            self::Vacant => 'Kosong',
            self::UnderRenovation => 'Renovasi',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Occupied => 'success',
            self::Vacant => 'secondary',
            self::UnderRenovation => 'warning',
        };
    }
}

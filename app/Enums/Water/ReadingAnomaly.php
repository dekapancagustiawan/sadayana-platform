<?php

namespace App\Enums\Water;

/**
 * Alasan pembacaan dianggap janggal (FR-WAT-002, EX-001).
 */
enum ReadingAnomaly: string
{
    /** Angka lebih kecil dari pembacaan sebelumnya. */
    case Decrease = 'decrease';

    /** Pemakaian jauh di atas rata-rata bulan-bulan sebelumnya. */
    case Spike = 'spike';

    public function label(): string
    {
        return match ($this) {
            self::Decrease => 'Angka lebih kecil dari bulan lalu',
            self::Spike => 'Pemakaian melonjak dibanding rata-rata',
        };
    }
}

<?php

namespace App\Enums\Communication;

enum ComplaintCategory: string
{
    /** S-3: warga melapor sampah belum diambil. */
    case WasteMissed = 'waste_missed';
    case Water = 'water';
    case Billing = 'billing';
    case Security = 'security';
    case Facility = 'facility';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::WasteMissed => 'Sampah belum diambil',
            self::Water => 'Air / meteran',
            self::Billing => 'Tagihan & pembayaran',
            self::Security => 'Keamanan',
            self::Facility => 'Fasilitas umum',
            self::Other => 'Lainnya',
        };
    }
}

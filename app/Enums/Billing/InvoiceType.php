<?php

namespace App\Enums\Billing;

/**
 * Jenis tagihan — satu invoice per jenis per rumah per bulan (Q8).
 */
enum InvoiceType: string
{
    case Water = 'water';
    case Waste = 'waste';
    case Contribution = 'contribution';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Water => 'Air',
            self::Waste => 'Sampah',
            self::Contribution => 'Iuran',
            self::Other => 'Lainnya',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Water => 'fas fa-faucet',
            self::Waste => 'fas fa-recycle',
            self::Contribution => 'fas fa-hand-holding-usd',
            self::Other => 'fas fa-file-invoice',
        };
    }
}

<?php

namespace App\Support;

/**
 * Pemisahan kewenangan (segregation of duties).
 *
 * SRS ROLE-001: Super Admin TIDAK otomatis berwenang menyetujui transaksi
 * keuangan. Ability di bawah ini dikecualikan dari bypass Admin di
 * `Gate::before` (AppServiceProvider): Admin hanya lolos bila permission-nya
 * diberikan secara eksplisit. Seeder juga tidak menempelkan izin ini ke Admin.
 */
final class Authorization
{
    /** @var list<string> */
    public const SEGREGATED_ABILITIES = [
        'invoices.issue',
        'invoices.cancel',
        'payments.verify',
        'payments.reverse',
        'cash.approve',
        'cash.reverse',
    ];

    public static function isSegregated(string $ability): bool
    {
        return in_array($ability, self::SEGREGATED_ABILITIES, true);
    }
}

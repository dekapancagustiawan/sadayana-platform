<?php

namespace App\Support;

/**
 * Normalisasi nomor HP Indonesia ke format 62xxxxxxxxxx.
 *
 * Nomor disimpan ternormalisasi supaya login dan cek unik tidak bergantung
 * pada cara penulisan ("0812-3456-7890", "+62 812 3456 7890", "812...").
 */
final class Phone
{
    public static function normalize(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        } elseif (str_starts_with($digits, '8')) {
            $digits = '62'.$digits;
        }

        return $digits;
    }

    /**
     * Apakah input login berbentuk nomor telepon (bukan email).
     */
    public static function looksLikePhone(string $value): bool
    {
        return ! str_contains($value, '@') && preg_match('/^\+?[\d\s\-().]{8,20}$/', trim($value)) === 1;
    }
}

<?php

namespace App\Support;

use Brick\Math\BigDecimal;
use Brick\Math\BigNumber;
use Brick\Math\RoundingMode;

/**
 * Aritmetika uang tanpa floating point.
 *
 * Kolom uang (`decimal(18,2)`) dibaca Eloquent sebagai string; semua
 * penjumlahan/pengurangan nominal di service WAJIB lewat kelas ini, bukan
 * operator `+`/`-` PHP yang diam-diam mengubah string menjadi float.
 *
 * Float sengaja tidak diterima (tipe parameter) supaya kesalahan konversi
 * ketahuan saat menulis kode, bukan saat laporan kas selisih satu rupiah.
 */
final class Money
{
    public const SCALE = 2;

    /** Skala kuantitas meter air (`decimal(14,3)`). */
    public const QUANTITY_SCALE = 3;

    public static function of(BigNumber|int|string|null $value): BigDecimal
    {
        return BigDecimal::of($value ?? 0)->toScale(self::SCALE, RoundingMode::HalfUp);
    }

    public static function zero(): BigDecimal
    {
        return self::of(0);
    }

    public static function quantity(BigNumber|int|string|null $value): BigDecimal
    {
        return BigDecimal::of($value ?? 0)->toScale(self::QUANTITY_SCALE, RoundingMode::HalfUp);
    }

    /**
     * Jumlahkan sekumpulan nominal.
     *
     * @param  iterable<BigNumber|int|string|null>  $values
     */
    public static function sum(iterable $values): BigDecimal
    {
        $total = self::zero();

        foreach ($values as $value) {
            $total = $total->plus(self::of($value));
        }

        return $total->toScale(self::SCALE, RoundingMode::HalfUp);
    }

    /**
     * Nilai siap simpan ke kolom `decimal(18,2)`, mis. "150000.00".
     */
    public static function toDb(BigNumber|int|string|null $value): string
    {
        return (string) self::of($value);
    }

    /**
     * Format tampilan Rupiah: `Rp150.000` atau `Rp25.500,50`.
     */
    public static function format(BigNumber|int|string|null $value): string
    {
        $amount = self::of($value);
        $negative = $amount->isNegative();
        [$integer, $fraction] = explode('.', (string) $amount->abs());

        $formatted = 'Rp'.number_format((int) $integer, 0, ',', '.');

        if ($fraction !== '00') {
            $formatted .= ','.$fraction;
        }

        return $negative ? '-'.$formatted : $formatted;
    }
}

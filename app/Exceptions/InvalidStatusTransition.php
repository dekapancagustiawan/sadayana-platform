<?php

namespace App\Exceptions;

use BackedEnum;
use RuntimeException;

/**
 * Turunan RuntimeException supaya controller yang sudah menangkap
 * RuntimeException → flash error ikut menangani kasus ini.
 */
class InvalidStatusTransition extends RuntimeException
{
    public static function between(BackedEnum $from, BackedEnum $to): self
    {
        return new self(sprintf(
            'Perubahan status dari "%s" ke "%s" tidak diizinkan.',
            $from->value,
            $to->value,
        ));
    }
}

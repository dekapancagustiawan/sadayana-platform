<?php

namespace Tests\Unit;

use App\Support\Phone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PhoneTest extends TestCase
{
    /**
     * @return iterable<string, array{?string, ?string}>
     */
    public static function numbers(): iterable
    {
        yield 'lokal 08' => ['0812-3456-7890', '6281234567890'];
        yield 'internasional +62' => ['+62 812 3456 7890', '6281234567890'];
        yield 'tanpa awalan' => ['81234567890', '6281234567890'];
        yield 'sudah 62' => ['6281234567890', '6281234567890'];
        yield 'kosong' => ['', null];
        yield 'null' => [null, null];
    }

    #[DataProvider('numbers')]
    public function test_normalize(?string $input, ?string $expected): void
    {
        $this->assertSame($expected, Phone::normalize($input));
    }

    public function test_looks_like_phone(): void
    {
        $this->assertTrue(Phone::looksLikePhone('0812 3456 7890'));
        $this->assertTrue(Phone::looksLikePhone('+6281234567890'));
        $this->assertFalse(Phone::looksLikePhone('warga@example.com'));
        $this->assertFalse(Phone::looksLikePhone('admin'));
    }
}

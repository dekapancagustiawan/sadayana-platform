<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_sum_has_no_floating_point_drift(): void
    {
        $this->assertSame('0.30', (string) Money::sum(['0.10', '0.20']));
        $this->assertSame('150000.00', (string) Money::sum(['100000', '25000.00', 25000]));
    }

    public function test_rounds_half_up_to_two_decimals(): void
    {
        $this->assertSame('10.13', Money::toDb('10.125'));
        $this->assertSame('0.00', Money::toDb(null));
    }

    public function test_quantity_keeps_three_decimals(): void
    {
        $this->assertSame('12.346', (string) Money::quantity('12.3455'));
    }

    public function test_format_rupiah(): void
    {
        $this->assertSame('Rp150.000', Money::format('150000.00'));
        $this->assertSame('Rp25.500,50', Money::format('25500.5'));
        $this->assertSame('-Rp1.000', Money::format('-1000'));
    }
}

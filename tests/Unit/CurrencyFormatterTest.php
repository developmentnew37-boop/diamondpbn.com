<?php

namespace Tests\Unit;

use App\Support\CurrencyFormatter;
use PHPUnit\Framework\TestCase;

class CurrencyFormatterTest extends TestCase
{
    public function test_idr_uses_zero_decimals(): void
    {
        $this->assertSame(0, CurrencyFormatter::decimals('IDR'));
        $this->assertSame('Rp1,500,000', CurrencyFormatter::format(1500000, 'IDR'));
    }

    public function test_eur_uses_two_decimals(): void
    {
        $this->assertSame(2, CurrencyFormatter::decimals('EUR'));
        $this->assertSame('€43.50', CurrencyFormatter::format(43.5, 'EUR'));
    }

    public function test_symbol_lookup(): void
    {
        $this->assertSame('€', CurrencyFormatter::symbol('EUR'));
        $this->assertSame('Rp', CurrencyFormatter::symbol('IDR'));
    }

    public function test_option_label_distinguishes_pakistani_and_sri_lankan_rupee(): void
    {
        $this->assertSame('PKR - Pakistani Rupee (₨)', CurrencyFormatter::optionLabel('PKR'));
        $this->assertSame('LKR - Sri Lankan Rupee (Rs)', CurrencyFormatter::optionLabel('LKR'));
    }
}

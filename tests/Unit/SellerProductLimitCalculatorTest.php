<?php

namespace Tests\Unit;

use App\Services\SellerProductLimitCalculator;
use PHPUnit\Framework\TestCase;

class SellerProductLimitCalculatorTest extends TestCase
{
    public function test_basic_defaults_to_forty_lifetime_slots(): void
    {
        $this->assertSame(40, SellerProductLimitCalculator::baseLimit(null));
        $this->assertSame(40, SellerProductLimitCalculator::baseLimit(20));
        $this->assertSame(40, SellerProductLimitCalculator::baseLimit(40));
        $this->assertSame(50, SellerProductLimitCalculator::baseLimit(50));
    }

    public function test_professional_period_slots_ignore_legacy_total(): void
    {
        $this->assertSame(100, SellerProductLimitCalculator::periodSlots(null));
        $this->assertSame(100, SellerProductLimitCalculator::periodSlots(140));
        $this->assertSame(100, SellerProductLimitCalculator::periodSlots(100));
        $this->assertSame(80, SellerProductLimitCalculator::periodSlots(80));
    }

    public function test_stacked_total_adds_one_hundred_per_professional_period(): void
    {
        $this->assertSame(40, SellerProductLimitCalculator::stackedTotal(40, 100, 0));
        $this->assertSame(140, SellerProductLimitCalculator::stackedTotal(40, 100, 1));
        $this->assertSame(240, SellerProductLimitCalculator::stackedTotal(40, 100, 2));
        $this->assertSame(340, SellerProductLimitCalculator::stackedTotal(40, 100, 3));
    }
}

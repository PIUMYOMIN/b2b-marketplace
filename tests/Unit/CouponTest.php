<?php

namespace Tests\Unit;

use App\Models\Coupon;
use PHPUnit\Framework\TestCase;

class CouponTest extends TestCase
{
    public function test_percentage_coupon_uses_the_provided_charged_subtotal(): void
    {
        $coupon = new Coupon([
            'type' => 'percentage',
            'value' => 20,
        ]);

        $this->assertSame(10.0, $coupon->calculateDiscount(50.0));
    }

    public function test_coupon_discount_never_exceeds_the_eligible_subtotal(): void
    {
        $percentageCoupon = new Coupon([
            'type' => 'percentage',
            'value' => 150,
        ]);
        $fixedCoupon = new Coupon([
            'type' => 'fixed',
            'value' => 75,
        ]);

        $this->assertSame(50.0, $percentageCoupon->calculateDiscount(50.0));
        $this->assertSame(50.0, $fixedCoupon->calculateDiscount(50.0));
        $this->assertSame(0.0, $fixedCoupon->calculateDiscount(0.0));
    }
}

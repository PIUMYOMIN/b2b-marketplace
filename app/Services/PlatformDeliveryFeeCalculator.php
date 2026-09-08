<?php

namespace App\Services;

class PlatformDeliveryFeeCalculator
{
    private const BASE_FEE = 5000.0;
    private const FEE_PER_KILOGRAM = 100.0;
    private const DEFAULT_WEIGHT = 5.0;

    public function calculate(?float $packageWeight): float
    {
        $weight = $packageWeight === null || $packageWeight <= 0
            ? self::DEFAULT_WEIGHT
            : min($packageWeight, 50.0);

        return round(self::BASE_FEE + ($weight * self::FEE_PER_KILOGRAM), 2);
    }
}

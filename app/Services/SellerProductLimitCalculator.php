<?php

namespace App\Services;

final class SellerProductLimitCalculator
{
    public const BASIC_LIFETIME = 40;

    public const PROFESSIONAL_PERIOD = 100;

    public const LEGACY_BASIC = 20;

    /** Some DBs stored Professional as 40+100 instead of per-period slots. */
    public const LEGACY_PROFESSIONAL_TOTAL = 140;

    public static function baseLimit(?int $stored): int
    {
        if ($stored === null || $stored <= 0 || $stored === self::LEGACY_BASIC) {
            return self::BASIC_LIFETIME;
        }

        return $stored;
    }

    public static function periodSlots(?int $stored): int
    {
        if ($stored === null || $stored <= 0 || $stored === -1) {
            return self::PROFESSIONAL_PERIOD;
        }

        if ($stored === self::LEGACY_PROFESSIONAL_TOTAL) {
            return self::PROFESSIONAL_PERIOD;
        }

        return $stored;
    }

    public static function stackedTotal(int $base, int $slots, int $periods): int
    {
        return $base + ($slots * max(0, $periods));
    }
}

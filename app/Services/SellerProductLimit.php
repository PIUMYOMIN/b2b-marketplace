<?php

namespace App\Services;

use App\Models\SubscriptionPlan;

class SellerProductLimit
{
    public function __construct(
        public bool $unlimited,
        public int $total,
        public int $baseLimit,
        public int $periodSlots,
        public int $paidPeriods,
        public string $planName,
        public string $planSlug,
        public ?SubscriptionPlan $plan = null,
    ) {}

    public function remaining(int $used): ?int
    {
        if ($this->unlimited) {
            return null;
        }

        return max(0, $this->total - $used);
    }

    public function isNearLimit(int $used): bool
    {
        return ! $this->unlimited && $used >= max(0, $this->total - 1);
    }

    public function hasReached(int $used): bool
    {
        return ! $this->unlimited && $used >= $this->total;
    }

    public function exceeds(int $used): bool
    {
        return ! $this->unlimited && $used > $this->total;
    }

    public function toUsageArray(int $used): array
    {
        $limit = $this->unlimited ? -1 : $this->total;

        return [
            'plan_name' => $this->planName,
            'plan_slug' => $this->planSlug,
            'product_limit' => $limit,
            'product_limit_total' => $limit,
            'stacked_product_limit' => $limit,
            'products_used' => $used,
            'remaining' => $this->remaining($used),
            'is_near_limit' => $this->isNearLimit($used),
            'base_product_limit' => $this->baseLimit,
            'lifetime_product_limit' => $this->baseLimit,
            'period_product_limit' => $this->periodSlots,
            'paid_product_slots' => $this->periodSlots,
            'paid_period_count' => $this->paidPeriods,
            'professional_period_count' => $this->paidPeriods,
        ];
    }
}

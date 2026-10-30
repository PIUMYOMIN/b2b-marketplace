<?php

namespace App\Services;

use App\Models\Product;
use App\Models\SellerSubscription;
use App\Models\SubscriptionPlan;

class SellerProductLimitService
{
    public function forSeller(int $userId): SellerProductLimit
    {
        $plans = SubscriptionPlan::query()->get()->keyBy(fn (SubscriptionPlan $plan) => strtolower((string) $plan->slug));
        $basic = $plans->get('basic');
        $professional = $plans->get('professional');

        $active = SellerSubscription::with('plan')
            ->where('user_id', $userId)
            ->active()
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->first();

        $plan = $active?->plan ?? $basic;
        $slug = strtolower((string) ($plan?->slug ?? 'basic'));
        $baseLimit = SellerProductLimitCalculator::baseLimit($basic?->product_limit);
        $periodSlots = SellerProductLimitCalculator::periodSlots($professional?->product_limit);

        if ($slug === 'enterprise' || (int) ($plan?->product_limit ?? 0) === -1) {
            return new SellerProductLimit(
                unlimited: true,
                total: -1,
                baseLimit: $baseLimit,
                periodSlots: 0,
                paidPeriods: 0,
                planName: $plan?->name ?? 'Enterprise',
                planSlug: $slug ?: 'enterprise',
                plan: $plan,
            );
        }

        $paidPeriods = $this->countPaidProfessionalPeriods($userId, $professional?->id);
        $total = SellerProductLimitCalculator::stackedTotal($baseLimit, $periodSlots, $paidPeriods);

        return new SellerProductLimit(
            unlimited: false,
            total: $total,
            baseLimit: $baseLimit,
            periodSlots: $periodSlots,
            paidPeriods: $paidPeriods,
            planName: $plan?->name ?? 'Basic',
            planSlug: $slug ?: 'basic',
            plan: $plan,
        );
    }

    /**
     * Quota after purchasing / renewing the given plan (used before creating a new period).
     */
    public function projectedForPlan(int $userId, SubscriptionPlan $target): SellerProductLimit
    {
        $current = $this->forSeller($userId);
        $slug = strtolower((string) $target->slug);

        if ($slug === 'enterprise' || (int) $target->product_limit === -1) {
            return new SellerProductLimit(
                unlimited: true,
                total: -1,
                baseLimit: $current->baseLimit,
                periodSlots: 0,
                paidPeriods: 0,
                planName: $target->name,
                planSlug: $slug,
                plan: $target,
            );
        }

        $periods = $current->paidPeriods;
        if ($slug === 'professional') {
            $periods += 1;
        }

        $total = SellerProductLimitCalculator::stackedTotal(
            $current->baseLimit,
            $current->periodSlots,
            $periods,
        );

        return new SellerProductLimit(
            unlimited: false,
            total: $total,
            baseLimit: $current->baseLimit,
            periodSlots: $current->periodSlots,
            paidPeriods: $periods,
            planName: $target->name,
            planSlug: $slug,
            plan: $target,
        );
    }

    public function countPaidProfessionalPeriods(int $userId, ?int $professionalPlanId = null): int
    {
        $professionalPlanId = $professionalPlanId
            ?? SubscriptionPlan::query()->where('slug', 'professional')->value('id');

        if (! $professionalPlanId) {
            return 0;
        }

        return SellerSubscription::query()
            ->where('user_id', $userId)
            ->where('plan_id', $professionalPlanId)
            ->where(function ($query) {
                $query->whereIn('status', ['active', 'expired'])
                    ->orWhere(function ($cancelled) {
                        $cancelled->where('status', 'cancelled')
                            ->where(function ($granted) {
                                $granted->whereNotNull('ends_at')
                                    ->orWhere(function ($notes) {
                                        $notes->where(function ($approved) {
                                            $approved->whereNull('notes')
                                                ->orWhere(function ($notPending) {
                                                    $notPending->where('notes', 'not like', 'Payment rejected%')
                                                        ->where('notes', 'not like', 'Waiting for admin%');
                                                });
                                        });
                                    });
                            });
                    });
            })
            ->count();
    }

    public function isProfessionalRenewal(?SellerSubscription $current, SubscriptionPlan $plan): bool
    {
        return $current
            && (int) $current->plan_id === (int) $plan->id
            && strtolower((string) $plan->slug) === 'professional'
            && (float) $plan->price_mmk > 0;
    }

    public function productCount(int $sellerId): int
    {
        return Product::query()
            ->where('seller_id', $sellerId)
            ->whereNull('deleted_at')
            ->count();
    }
}

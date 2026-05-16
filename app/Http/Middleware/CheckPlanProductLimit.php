<?php

namespace App\Http\Middleware;

use App\Models\Product;
use App\Models\SellerSubscription;
use App\Models\SubscriptionPlan;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks a seller from creating more products than their active plan allows.
 *
 * Apply only to POST /seller/products (the store route).
 * Read-only and update routes are unaffected.
 */
class CheckPlanProductLimit
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Resolve the seller's active plan (falls back to Basic when none exists)
        $plan = $this->resolvePlan($user->id);

        // -1 means unlimited — no check needed
        if ($plan->product_limit === -1) {
            return $next($request);
        }

        // Count only non-deleted, non-draft products owned by this seller
        $currentCount = Product::where('seller_id', $user->id)
            ->whereNull('deleted_at')
            ->count();

        if ($currentCount >= $plan->product_limit) {
            return response()->json([
                'success' => false,
                'message' => "Your {$plan->name} plan allows a maximum of {$plan->product_limit} products. "
                    . "You currently have {$currentCount}. "
                    . "Please upgrade your plan to add more products.",
                'error'   => 'plan_product_limit_reached',
                'data'    => [
                    'plan'          => $plan->slug,
                    'plan_name'     => $plan->name,
                    'product_limit' => $plan->product_limit,
                    'current_count' => $currentCount,
                    'upgrade_url'   => '/seller/subscription/plans',
                ],
            ], 403);
        }

        return $next($request);
    }

    /**
     * Find the active subscription plan for a seller.
     * Falls back to the Basic plan if no subscription record exists.
     */
    private function resolvePlan(int $userId): \App\Models\SubscriptionPlan
    {
        $subscription = SellerSubscription::with('plan')
            ->where('user_id', $userId)
            ->active()
            ->first();

        if ($subscription && $subscription->plan) {
            return $subscription->plan;
        }

        // No subscription record — fall back to Basic (free tier)
        return SubscriptionPlan::where('slug', 'basic')->firstOrFail();
    }
}
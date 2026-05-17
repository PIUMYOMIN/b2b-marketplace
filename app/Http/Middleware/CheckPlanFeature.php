<?php

namespace App\Http\Middleware;

use App\Models\SellerSubscription;
use App\Models\SubscriptionPlan;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks access to a route if the seller's active plan does not include
 * the requested feature flag.
 *
 * Usage on any seller route:
 *   ->middleware('plan.feature:analytics_enabled')
 *   ->middleware('plan.feature:bulk_import_enabled')
 *
 * The $feature parameter must match a boolean column on the subscription_plans table.
 */
class CheckPlanFeature
{
    // Human-readable labels for each feature flag (used in error messages).
    private const FEATURE_LABELS = [
        'analytics_enabled'   => 'Analytics Dashboard',
        'bulk_import_enabled' => 'Bulk Import / Export',
        'priority_support'    => 'Priority Support',
        'custom_storefront'   => 'Custom Storefront',
    ];

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        // Guard: only known feature flags are accepted.
        if (! array_key_exists($feature, self::FEATURE_LABELS)) {
            return response()->json([
                'success' => false,
                'message' => "Unknown feature flag: {$feature}.",
            ], 500);
        }

        $plan  = $this->resolvePlan($request->user()->id);
        $label = self::FEATURE_LABELS[$feature];

        if (! $plan->{$feature}) {
            return response()->json([
                'success' => false,
                'message' => "{$label} is not available on your current {$plan->name} plan. "
                    . 'Please upgrade to access this feature.',
                'error'   => 'plan_feature_unavailable',
                'data'    => [
                    'feature'     => $feature,
                    'feature_label' => $label,
                    'plan'        => $plan->slug,
                    'plan_name'   => $plan->name,
                    'upgrade_url' => '/seller/subscription/plans',
                ],
            ], 403);
        }

        return $next($request);
    }

    /**
     * Resolve the seller's active subscription plan.
     * Falls back to the Basic plan when no subscription record exists.
     */
    private function resolvePlan(int $userId): SubscriptionPlan
    {
        $subscription = SellerSubscription::with('plan')
            ->where('user_id', $userId)
            ->active()
            ->first();

        if ($subscription && $subscription->plan) {
            return $subscription->plan;
        }

        return SubscriptionPlan::where('slug', 'basic')->firstOrFail();
    }
}
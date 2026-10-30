<?php

namespace App\Http\Middleware;

use App\Services\SellerProductLimitService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks a seller from creating more products than their stacked plan allows.
 *
 * Apply only to POST /seller/products (the store route).
 * Read-only and update routes are unaffected.
 */
class CheckPlanProductLimit
{
    public function __construct(private SellerProductLimitService $limits) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $quota = $this->limits->forSeller((int) $user->id);

        if ($quota->unlimited) {
            return $next($request);
        }

        $currentCount = $this->limits->productCount((int) $user->id);

        if ($quota->hasReached($currentCount)) {
            return response()->json([
                'success' => false,
                'message' => "Your {$quota->planName} plan allows a maximum of {$quota->total} products. "
                    . "You currently have {$currentCount}. "
                    . "Please upgrade your plan to add more products.",
                'error'   => 'plan_product_limit_reached',
                'data'    => [
                    'plan'          => $quota->planSlug,
                    'plan_name'     => $quota->planName,
                    'product_limit' => $quota->total,
                    'current_count' => $currentCount,
                    'upgrade_url'   => '/seller/subscription/plans',
                    ...$quota->toUsageArray($currentCount),
                ],
            ], 403);
        }

        return $next($request);
    }
}

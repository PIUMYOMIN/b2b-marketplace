<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\SellerSubscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Notifications\SubscriptionApproved;
use App\Notifications\SubscriptionRejected;
use App\Notifications\SubscriptionRequestSubmitted;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SubscriptionController extends Controller
{
    // ══════════════════════════════════════════════════════════════════════
    //  SELLER ROUTES
    // ══════════════════════════════════════════════════════════════════════

    /**
     * GET /seller/subscription/plans
     * List all active plans with the seller's current usage.
     */
    public function plans(Request $request): JsonResponse
    {
        $seller = $request->user();
        $current = $this->activeSubscription($seller->id);
        $pending = $this->pendingSubscriptionRequest($seller->id);
        $productCount = Product::where('seller_id', $seller->id)->whereNull('deleted_at')->count();

        $plans = SubscriptionPlan::active()->get()->map(function (SubscriptionPlan $plan) use ($current, $pending, $productCount) {
            return [
                'id'                   => $plan->id,
                'slug'                 => $plan->slug,
                'name'                 => $plan->name,
                'description'          => $plan->description,
                'price_mmk'            => $plan->price_mmk,
                'billing_cycle'        => $plan->billing_cycle,
                'product_limit'        => $plan->product_limit,
                'product_limit_label'  => $plan->product_limit_label,
                'commission_rate'      => $plan->commission_rate,
                'commission_percent'   => $plan->commission_percent,
                'analytics_enabled'    => $plan->analytics_enabled,
                'bulk_import_enabled'  => $plan->bulk_import_enabled,
                'priority_support'     => $plan->priority_support,
                'custom_storefront'    => $plan->custom_storefront,
                'is_current'           => $current && $current->plan_id === $plan->id,
                'is_pending'           => $pending && $pending->plan_id === $plan->id,
                'products_used'        => $plan->id === ($current?->plan_id) ? $productCount : null,
            ];
        });

        return response()->json([
            'success'              => true,
            'data'                 => $plans,
            'current_subscription' => $current ? $this->formatSubscription($current) : null,
            'pending_request'      => $pending ? $this->formatSubscription($pending) : null,
        ]);
    }

    /**
     * GET /seller/subscription
     * Return the seller's current subscription details.
     */
    public function current(Request $request): JsonResponse
    {
        $subscription = $this->activeSubscription($request->user()->id);
        $pending = $this->pendingSubscriptionRequest($request->user()->id);

        if (! $subscription) {
            // Auto-assign Basic plan on first access
            $subscription = $this->assignBasicPlan($request->user()->id);
        }

        $productCount = Product::where('seller_id', $request->user()->id)
            ->whereNull('deleted_at')
            ->count();

        return response()->json([
            'success' => true,
            'data'    => array_merge(
                $this->formatSubscription($subscription),
                [
                    'products_used' => $productCount,
                    'pending_request' => $pending ? $this->formatSubscription($pending) : null,
                ]
            ),
        ]);
    }

    /**
     * POST /seller/subscription/upgrade
     * Body: { plan_slug: 'professional' | 'enterprise', payment_reference?: string }
     *
     * For paid plans this creates a pending payment request for admin approval.
     * The current active plan remains usable until an admin approves the request.
     * For Basic (free) downgrades it resets immediately.
     */
    public function upgrade(Request $request): JsonResponse
    {
        $request->validate([
            'plan_slug'         => 'required|string|exists:subscription_plans,slug',
            'payment_reference' => 'nullable|string|max:255',
        ]);

        $plan = SubscriptionPlan::where('slug', $request->plan_slug)
            ->where('is_active', true)
            ->firstOrFail();

        $seller = $request->user();

        // Prevent re-subscribing to the same active plan (would reset billing cycle for free)
        $current = $this->activeSubscription($seller->id);
        if ($current && $current->plan_id === $plan->id) {
            return response()->json([
                'success' => false,
                'message' => "You are already on the {$plan->name} plan.",
                'error'   => 'already_on_plan',
            ], 422);
        }

        // Paid plan but no payment reference supplied
        if ($plan->price_mmk > 0 && empty($request->payment_reference)) {
            return response()->json([
                'success' => false,
                'message' => 'A payment reference is required for paid plans.',
                'error'   => 'payment_reference_missing',
            ], 422);
        }

        // Check downgrade: if moving to a lower plan, ensure current product count is within new limit
        if ($plan->product_limit !== -1) {
            $productCount = Product::where('seller_id', $seller->id)->whereNull('deleted_at')->count();
            if ($productCount > $plan->product_limit) {
                return response()->json([
                    'success' => false,
                    'message' => "You have {$productCount} products, which exceeds the {$plan->name} plan limit of {$plan->product_limit}. "
                        . 'Please remove products before downgrading.',
                    'error'   => 'product_count_exceeds_plan_limit',
                    'data'    => [
                        'current_count' => $productCount,
                        'plan_limit'    => $plan->product_limit,
                    ],
                ], 422);
            }
        }

        DB::beginTransaction();
        try {
            if ($plan->price_mmk > 0) {
                SellerSubscription::where('user_id', $seller->id)
                    ->where('status', 'pending_payment')
                    ->update(['status' => 'cancelled']);

                $subscription = SellerSubscription::create([
                    'user_id'           => $seller->id,
                    'plan_id'           => $plan->id,
                    'status'            => 'pending_payment',
                    'starts_at'         => null,
                    'ends_at'           => null,
                    'next_billing_at'   => null,
                    'amount_paid_mmk'   => $plan->price_mmk,
                    'payment_reference' => $request->payment_reference,
                    'changed_by'        => $seller->id,
                    'notes'             => 'Waiting for admin payment approval.',
                ]);

                DB::commit();

                $this->notifyAdmins(new SubscriptionRequestSubmitted($subscription->load(['plan', 'user.sellerProfile'])));

                return response()->json([
                    'success' => true,
                    'message' => "Your {$plan->name} plan request was submitted. The plan will activate after admin approval.",
                    'data'    => $this->formatSubscription($subscription->load('plan')),
                ]);
            }

            // Cancel any existing active subscription
            SellerSubscription::where('user_id', $seller->id)
                ->where('status', 'active')
                ->update(['status' => 'cancelled']);

            $startsAt    = Carbon::today();
            $endsAt      = $plan->price_mmk > 0 ? $startsAt->copy()->addMonth() : null;
            $nextBilling = $endsAt?->copy();

            $subscription = SellerSubscription::create([
                'user_id'           => $seller->id,
                'plan_id'           => $plan->id,
                'status'            => 'active',
                'starts_at'         => $startsAt,
                'ends_at'           => $endsAt,
                'next_billing_at'   => $nextBilling,
                'amount_paid_mmk'   => $plan->price_mmk,
                'changed_by'        => $seller->id,
            ]);

            DB::commit();

            $pending->fresh(['plan', 'user'])->user?->notify(new SubscriptionApproved($pending->fresh(['plan'])));

            return response()->json([
                'success' => true,
                'message' => "Successfully upgraded to the {$plan->name} plan.",
                'data'    => $this->formatSubscription($subscription->load('plan')),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Subscription upgrade failed: ' . $e->getMessage(), ['user_id' => $seller->id]);
            return response()->json(['success' => false, 'message' => 'Upgrade failed. Please try again.'], 500);
        }
    }

    /**
     * GET /public/subscription-plans
     *
     * Returns all active plans without any seller-specific context
     * (no is_current flag, no products_used count).
     * Accessible by guests, buyers, and anyone visiting the Pricing page.
     */
    public function publicPlans(): JsonResponse
    {
        $plans = SubscriptionPlan::active()->get()->map(function (SubscriptionPlan $plan) {
            return [
                'id'                  => $plan->id,
                'slug'                => $plan->slug,
                'name'                => $plan->name,
                'description'         => $plan->description,
                'price_mmk'           => $plan->price_mmk,
                'billing_cycle'       => $plan->billing_cycle,
                'product_limit'       => $plan->product_limit,
                'product_limit_label' => $plan->product_limit_label,
                'commission_rate'     => $plan->commission_rate,
                'commission_percent'  => $plan->commission_percent,
                'analytics_enabled'   => $plan->analytics_enabled,
                'bulk_import_enabled' => $plan->bulk_import_enabled,
                'priority_support'    => $plan->priority_support,
                'custom_storefront'   => $plan->custom_storefront,
            ];
        });
 
        return response()->json([
            'success' => true,
            'data'    => $plans,
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════
    //  ADMIN ROUTES
    // ══════════════════════════════════════════════════════════════════════

    /**
     * GET /admin/subscriptions
     * Paginated list of all seller subscriptions.
     */
    public function adminIndex(Request $request): JsonResponse
    {
        $query = SellerSubscription::with(['plan', 'user.sellerProfile'])
            ->when($request->status,    fn ($q) => $q->where('status', $request->status))
            ->when($request->plan_slug, fn ($q) => $q->whereHas('plan', fn ($p) => $p->where('slug', $request->plan_slug)))
            ->when($request->search,    fn ($q) => $q->whereHas('user', fn ($u) =>
                $u->where('name', 'like', "%{$request->search}%")
                  ->orWhere('email', 'like', "%{$request->search}%")))
            ->latest();

        $subscriptions = $query->paginate($request->get('per_page', 25));

        return response()->json([
            'success' => true,
            'data'    => $subscriptions->getCollection()->map(fn ($s) => $this->formatSubscription($s)),
            'meta'    => [
                'current_page' => $subscriptions->currentPage(),
                'last_page'    => $subscriptions->lastPage(),
                'total'        => $subscriptions->total(),
                'per_page'     => $subscriptions->perPage(),
            ],
        ]);
    }

    /**
     * POST /admin/subscriptions/requests/{subscriptionId}/approve
     * Approve a seller's pending paid-plan request and activate it.
     */
    public function adminApproveRequest(Request $request, int $subscriptionId): JsonResponse
    {
        $request->validate([
            'notes' => 'nullable|string|max:500',
        ]);

        $pending = SellerSubscription::with(['plan', 'user.sellerProfile'])
            ->where('status', 'pending_payment')
            ->findOrFail($subscriptionId);

        DB::beginTransaction();
        try {
            SellerSubscription::where('user_id', $pending->user_id)
                ->where('status', 'active')
                ->update(['status' => 'cancelled']);

            $startsAt = Carbon::today();
            $endsAt = $pending->plan?->price_mmk > 0 ? $startsAt->copy()->addMonth() : null;

            $pending->update([
                'status' => 'active',
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'next_billing_at' => $endsAt?->copy(),
                'changed_by' => $request->user()->id,
                'notes' => $request->notes ?: 'Payment approved by admin.',
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Subscription request approved. {$pending->plan?->name} is now active.",
                'data' => $this->formatSubscription($pending->fresh(['plan', 'user.sellerProfile'])),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Subscription request approval failed: ' . $e->getMessage(), ['subscription_id' => $subscriptionId]);
            return response()->json(['success' => false, 'message' => 'Failed to approve subscription request.'], 500);
        }
    }

    /**
     * POST /admin/subscriptions/requests/{subscriptionId}/reject
     */
    public function adminRejectRequest(Request $request, int $subscriptionId): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $pending = SellerSubscription::with(['plan', 'user.sellerProfile'])
            ->where('status', 'pending_payment')
            ->findOrFail($subscriptionId);

        $pending->update([
            'status' => 'cancelled',
            'changed_by' => $request->user()->id,
            'notes' => 'Payment rejected: ' . $request->reason,
        ]);

        $pending->fresh(['plan', 'user'])->user?->notify(new SubscriptionRejected($pending->fresh(['plan']), $request->reason));

        return response()->json([
            'success' => true,
            'message' => 'Subscription request rejected.',
            'data' => $this->formatSubscription($pending->fresh(['plan', 'user.sellerProfile'])),
        ]);
    }

    /**
     * PUT /admin/subscriptions/{userId}
     * Admin manually sets/overrides a seller's plan.
     * Body: { plan_slug, ends_at?, notes? }
     */
    public function adminAssign(Request $request, int $userId): JsonResponse
    {
        $request->validate([
            'plan_slug' => 'required|string|exists:subscription_plans,slug',
            'ends_at'   => 'nullable|date|after:today',
            'notes'     => 'nullable|string|max:500',
        ]);

        // Verify the target user exists and is a seller
        $targetUser = User::find($userId);
        if (! $targetUser) {
            return response()->json([
                'success' => false,
                'message' => "User #{$userId} not found.",
            ], 404);
        }

        $isSeller = $targetUser->roles()->where('name', 'seller')->exists()
            || $targetUser->role === 'seller'
            || $targetUser->type === 'seller';

        if (! $isSeller) {
            return response()->json([
                'success' => false,
                'message' => "User #{$userId} is not a seller account. Subscriptions can only be assigned to sellers.",
                'error'   => 'user_not_seller',
            ], 422);
        }

        $plan = SubscriptionPlan::where('slug', $request->plan_slug)->firstOrFail();

        DB::beginTransaction();
        try {
            SellerSubscription::where('user_id', $userId)
                ->where('status', 'active')
                ->update(['status' => 'cancelled']);

            $subscription = SellerSubscription::create([
                'user_id'         => $userId,
                'plan_id'         => $plan->id,
                'status'          => 'active',
                'starts_at'       => Carbon::today(),
                'ends_at'         => $request->ends_at ? Carbon::parse($request->ends_at) : null,
                'next_billing_at' => $request->ends_at ? Carbon::parse($request->ends_at) : null,
                'amount_paid_mmk' => 0,
                'changed_by'      => $request->user()->id,
                'notes'           => $request->notes,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Plan set to {$plan->name} for seller #{$userId}.",
                'data'    => $this->formatSubscription($subscription->load('plan')),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Admin subscription assign failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to assign plan.'], 500);
        }
    }

    /**
     * GET /admin/subscription-plans
     * List all plans (for admin management UI).
     */
    public function adminPlans(): JsonResponse
    {
        $plans = SubscriptionPlan::orderBy('sort_order')->get();
        return response()->json(['success' => true, 'data' => $plans]);
    }

    /**
     * PUT /admin/subscription-plans/{id}
     * Update a plan's limits or pricing.
     */
    public function adminUpdatePlan(Request $request, int $id): JsonResponse
    {
        $plan = SubscriptionPlan::findOrFail($id);

        $request->validate([
            'price_mmk'           => 'sometimes|numeric|min:0',
            'product_limit'       => 'sometimes|integer|min:-1',
            'commission_rate'     => 'sometimes|numeric|min:0|max:1',
            'analytics_enabled'   => 'sometimes|boolean',
            'bulk_import_enabled' => 'sometimes|boolean',
            'priority_support'    => 'sometimes|boolean',
            'custom_storefront'   => 'sometimes|boolean',
            'is_active'           => 'sometimes|boolean',
            'description'         => 'sometimes|nullable|string',
        ]);

        $plan->update($request->only([
            'price_mmk', 'product_limit', 'commission_rate',
            'analytics_enabled', 'bulk_import_enabled', 'priority_support',
            'custom_storefront', 'is_active', 'description',
        ]));

        return response()->json([
            'success' => true,
            'message' => "Plan '{$plan->name}' updated.",
            'data'    => $plan->fresh(),
        ]);
    }

    // ── Private helpers ───────────────────────────────────────────────────

    private function activeSubscription(int $userId): ?SellerSubscription
    {
        return SellerSubscription::with('plan')
            ->where('user_id', $userId)
            ->active()
            ->first();
    }

    private function pendingSubscriptionRequest(int $userId): ?SellerSubscription
    {
        return SellerSubscription::with('plan')
            ->where('user_id', $userId)
            ->where('status', 'pending_payment')
            ->latest()
            ->first();
    }

    private function notifyAdmins($notification): void
    {
        $admins = User::where('type', 'admin')
            ->orWhereHas('roles', fn ($query) => $query->where('name', 'admin'))
            ->get()
            ->unique('id');

        foreach ($admins as $admin) {
            try {
                $admin->notify($notification);
            } catch (\Throwable $e) {
                Log::warning('Admin notification failed: ' . $e->getMessage(), ['admin_id' => $admin->id]);
            }
        }
    }

    /** Auto-assign the Basic (free) plan when no subscription record exists. */
    private function assignBasicPlan(int $userId): SellerSubscription
    {
        $basic = SubscriptionPlan::where('slug', 'basic')->firstOrFail();

        // Wrap in a transaction with a row-level lock to prevent duplicate
        // Basic plan rows when concurrent requests hit current() simultaneously.
        return DB::transaction(function () use ($userId, $basic) {
            // Re-check inside the transaction — another request may have
            // already created the Basic plan between the outer check and now.
            $existing = SellerSubscription::where('user_id', $userId)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing->load('plan');
            }

            return SellerSubscription::create([
                'user_id'         => $userId,
                'plan_id'         => $basic->id,
                'status'          => 'active',
                'starts_at'       => Carbon::today(),
                'ends_at'         => null,
                'amount_paid_mmk' => 0,
            ])->load('plan');
        });
    }

    private function formatSubscription(SellerSubscription $s): array
    {
        return [
            'id'                => $s->id,
            'user_id'           => $s->user_id,
            'status'            => $s->status,
            'status_label'      => $s->status_label,
            'starts_at'         => $s->starts_at?->toDateString(),
            'ends_at'           => $s->ends_at?->toDateString(),
            'next_billing_at'   => $s->next_billing_at?->toDateString(),
            'days_remaining'    => $s->days_remaining,
            'amount_paid_mmk'   => $s->amount_paid_mmk,
            'payment_reference' => $s->payment_reference,
            'notes'             => $s->notes,
            'plan'              => $s->plan ? [
                'id'                 => $s->plan->id,
                'slug'               => $s->plan->slug,
                'name'               => $s->plan->name,
                'price_mmk'          => $s->plan->price_mmk,
                'product_limit'      => $s->plan->product_limit,
                'product_limit_label'=> $s->plan->product_limit_label,
                'commission_rate'    => $s->plan->commission_rate,
                'commission_percent' => $s->plan->commission_percent,
                'analytics_enabled'  => $s->plan->analytics_enabled,
                'bulk_import_enabled'=> $s->plan->bulk_import_enabled,
                'priority_support'   => $s->plan->priority_support,
                'custom_storefront'  => $s->plan->custom_storefront,
            ] : null,
            'seller'            => isset($s->user) ? [
                'id'    => $s->user->id,
                'name'  => $s->user->name,
                'email' => $s->user->email,
                'store' => $s->user->sellerProfile?->store_name,
            ] : null,
        ];
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * CouponController
 *
 * Sellers create coupons for their own products.
 * Buyers validate and apply coupons at checkout.
 *
 * Completely separate from DiscountController, which handles
 * price reductions applied directly to product prices.
 */
class CouponController extends Controller
{
    // -------------------------------------------------------------------------
    // Seller: list their own coupons
    // -------------------------------------------------------------------------

    public function index(Request $request)
    {
        $request->validate([
            'status'   => 'sometimes|in:active,expired,upcoming',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $query = Coupon::forSeller((int) Auth::id())
            ->with(['usages'])
            ->withCount('usages');

        if ($request->has('status')) {
            $now = now();
            match ($request->status) {
                'active'   => $query->where('is_active', true)
                                    ->where(fn($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
                                    ->where(fn($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', $now)),
                'expired'  => $query->where(fn($q) => $q->whereNotNull('expires_at')->where('expires_at', '<', $now)),
                'upcoming' => $query->where('starts_at', '>', $now),
                default    => null,
            };
        }

        $coupons = $query->latest()->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'data'    => $coupons,
            'meta'    => [
                'current_page' => $coupons->currentPage(),
                'per_page'     => $coupons->perPage(),
                'total'        => $coupons->total(),
            ],
        ]);
    }

    // -------------------------------------------------------------------------
    // Seller: create a new coupon
    // -------------------------------------------------------------------------

    public function store(Request $request)
    {
        $request->validate([
            'name'                  => 'required|string|max:255',
            'code'                  => 'nullable|string|max:50|unique:coupons,code',
            'type'                  => 'required|in:percentage,fixed',
            'value'                 => 'required|numeric|min:0.01',
            'min_order_amount'      => 'nullable|numeric|min:0',
            // NULL = applies to all seller products; array = specific products only
            'applicable_product_ids'=> 'nullable|array',
            'applicable_product_ids.*' => 'integer|exists:products,id',
            'max_uses'              => 'nullable|integer|min:1',
            'max_uses_per_user'     => 'nullable|integer|min:1',
            'is_one_time_use'       => 'boolean',
            'is_active'             => 'boolean',
            'starts_at'             => 'nullable|date',
            'expires_at'            => 'nullable|date|after_or_equal:starts_at',
        ]);

        $sellerId = (int) Auth::id();

        // If specific products given, ensure they all belong to this seller
        if ($request->filled('applicable_product_ids')) {
            $this->authorizeProducts($request->applicable_product_ids, $sellerId);
        }

        $code = $request->filled('code')
            ? strtoupper($request->code)
            : strtoupper(Str::random(8));

        $coupon = Coupon::create([
            'seller_id'              => $sellerId,
            'name'                   => $request->name,
            'code'                   => $code,
            'type'                   => $request->type,
            'value'                  => $request->value,
            'min_order_amount'       => $request->min_order_amount,
            'applicable_product_ids' => $request->applicable_product_ids,
            'max_uses'               => $request->max_uses,
            'max_uses_per_user'      => $request->max_uses_per_user,
            'is_one_time_use'        => $request->boolean('is_one_time_use', false),
            'is_active'              => $request->boolean('is_active', true),
            'starts_at'              => $request->starts_at,
            'expires_at'             => $request->expires_at,
        ]);

        return response()->json([
            'success' => true,
            'data'    => $coupon,
            'message' => 'Coupon created successfully',
        ], 201);
    }

    // -------------------------------------------------------------------------
    // Seller: show a single coupon
    // -------------------------------------------------------------------------

    public function show(Coupon $coupon)
    {
        $this->authorizeSeller($coupon);

        return response()->json([
            'success' => true,
            'data'    => $coupon->load('usages'),
        ]);
    }

    // -------------------------------------------------------------------------
    // Seller: update a coupon
    // -------------------------------------------------------------------------

    public function update(Request $request, Coupon $coupon)
    {
        $this->authorizeSeller($coupon);

        $request->validate([
            'name'                  => 'sometimes|string|max:255',
            'code'                  => 'sometimes|string|max:50|unique:coupons,code,' . $coupon->id,
            'type'                  => 'sometimes|in:percentage,fixed',
            'value'                 => 'sometimes|numeric|min:0.01',
            'min_order_amount'      => 'nullable|numeric|min:0',
            'applicable_product_ids'=> 'nullable|array',
            'applicable_product_ids.*' => 'integer|exists:products,id',
            'max_uses'              => 'nullable|integer|min:1',
            'max_uses_per_user'     => 'nullable|integer|min:1',
            'is_one_time_use'       => 'boolean',
            'is_active'             => 'boolean',
            'starts_at'             => 'nullable|date',
            'expires_at'            => 'nullable|date|after_or_equal:starts_at',
        ]);

        $sellerId = (int) Auth::id();

        if ($request->filled('applicable_product_ids')) {
            $this->authorizeProducts($request->applicable_product_ids, $sellerId);
        }

        $data = $request->only([
            'name', 'type', 'value', 'min_order_amount',
            'applicable_product_ids', 'max_uses', 'max_uses_per_user',
            'is_one_time_use', 'is_active', 'starts_at', 'expires_at',
        ]);

        if ($request->filled('code')) {
            $data['code'] = strtoupper($request->code);
        }

        $coupon->update($data);

        return response()->json([
            'success' => true,
            'data'    => $coupon->fresh(),
            'message' => 'Coupon updated successfully',
        ]);
    }

    // -------------------------------------------------------------------------
    // Seller: toggle active status
    // -------------------------------------------------------------------------

    public function toggleStatus(Coupon $coupon)
    {
        $this->authorizeSeller($coupon);

        $coupon->update(['is_active' => !$coupon->is_active]);

        return response()->json([
            'success'   => true,
            'message'   => 'Coupon status updated',
            'is_active' => $coupon->is_active,
        ]);
    }

    // -------------------------------------------------------------------------
    // Seller: delete a coupon
    // -------------------------------------------------------------------------

    public function destroy(Coupon $coupon)
    {
        $this->authorizeSeller($coupon);

        $coupon->delete();

        return response()->json([
            'success' => true,
            'message' => 'Coupon deleted successfully',
        ]);
    }

    // -------------------------------------------------------------------------
    // Buyer: validate a coupon code at checkout
    // -------------------------------------------------------------------------

    /**
     * POST /buyer/coupons/validate
     *
     * Validates a coupon code against a set of cart items.
     * Returns the discount amount and which products it applies to.
     */
    public function validate(Request $request)
    {
        $request->validate([
            'code'        => 'required|string',
            'product_ids' => 'required|array|min:1',
            'product_ids.*' => 'integer|exists:products,id',
            'subtotal'    => 'required|numeric|min:0',
        ]);

        $coupon = Coupon::where('code', strtoupper($request->code))->first();

        if (!$coupon) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid coupon code',
            ], 404);
        }

        if (!$coupon->isValid()) {
            return response()->json([
                'success' => false,
                'message' => 'This coupon is no longer valid',
            ], 422);
        }

        // Per-user limit check
        if (Auth::check() && $coupon->hasUserExhausted((int) Auth::id())) {
            return response()->json([
                'success' => false,
                'message' => 'You have already used this coupon',
            ], 422);
        }

        // Minimum order check
        if ($coupon->min_order_amount && $request->subtotal < $coupon->min_order_amount) {
            return response()->json([
                'success' => false,
                'message' => 'Minimum order amount of ' . number_format($coupon->min_order_amount, 0) . ' MMK required',
            ], 422);
        }

        // Find which of the requested products this coupon applies to
        $products          = Product::whereIn('id', $request->product_ids)->get();
        $applicableProducts = $products->filter(fn($p) => $coupon->appliesToProduct($p));

        if ($applicableProducts->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'This coupon does not apply to any of your selected products',
            ], 422);
        }

        // Calculate discount based on the applicable subtotal
        $applicableSubtotal = $applicableProducts->sum('price');
        $discountAmount     = $coupon->calculateDiscount(min($applicableSubtotal, $request->subtotal));

        return response()->json([
            'success' => true,
            'data'    => [
                'coupon'                  => [
                    'id'   => $coupon->id,
                    'code' => $coupon->code,
                    'name' => $coupon->name,
                    'type' => $coupon->type,
                    'value'=> (float) $coupon->value,
                ],
                'applicable_product_ids'  => $applicableProducts->pluck('id')->values(),
                'discount_amount'         => $discountAmount,
                'final_amount'            => max(0, $request->subtotal - $discountAmount),
            ],
            'message' => 'Coupon applied successfully',
        ]);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function authorizeSeller(Coupon $coupon): void
    {
        if ((int) $coupon->seller_id !== (int) Auth::id()) {
            abort(403, 'Unauthorized to manage this coupon');
        }
    }

    /**
     * Ensure all given product IDs belong to the authenticated seller.
     */
    private function authorizeProducts(array $productIds, int $sellerId): void
    {
        $foreign = Product::whereIn('id', $productIds)
            ->where('seller_id', '!=', $sellerId)
            ->exists();

        if ($foreign) {
            abort(422, 'You can only apply coupons to your own products');
        }
    }
}

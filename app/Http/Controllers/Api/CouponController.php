<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\ProductVariant;
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
            'status' => 'sometimes|in:active,expired,upcoming',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $query = Coupon::forSeller((int) Auth::id())
            ->with(['usages'])
            ->withCount('usages');

        if ($request->has('status')) {
            $now = now();
            match ($request->status) {
                'active' => $query->where('is_active', true)
                    ->where(fn($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
                    ->where(fn($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', $now)),
                'expired' => $query->where(fn($q) => $q->whereNotNull('expires_at')->where('expires_at', '<', $now)),
                'upcoming' => $query->where('starts_at', '>', $now),
                default => null,
            };
        }

        $coupons = $query->latest()->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $coupons,
            'meta' => [
                'current_page' => $coupons->currentPage(),
                'per_page' => $coupons->perPage(),
                'total' => $coupons->total(),
            ],
        ]);
    }

    // -------------------------------------------------------------------------
    // Seller: create a new coupon
    // -------------------------------------------------------------------------

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50|unique:coupons,code',
            'type' => 'required|in:percentage,fixed',
            'value' => 'required|numeric|min:0.01',
            'min_order_amount' => 'nullable|numeric|min:0',
            // NULL = applies to all seller products; array = specific products only
            'applicable_product_ids' => 'nullable|array',
            'applicable_product_ids.*' => 'integer|exists:products,id',
            'max_uses' => 'nullable|integer|min:1',
            'max_uses_per_user' => 'nullable|integer|min:1',
            'is_one_time_use' => 'boolean',
            'is_active' => 'boolean',
            'starts_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after_or_equal:starts_at',
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
            'seller_id' => $sellerId,
            'name' => $request->name,
            'code' => $code,
            'type' => $request->type,
            'value' => $request->value,
            'min_order_amount' => $request->min_order_amount,
            'applicable_product_ids' => $request->applicable_product_ids,
            'max_uses' => $request->max_uses,
            'max_uses_per_user' => $request->max_uses_per_user,
            'is_one_time_use' => $request->boolean('is_one_time_use', false),
            'is_active' => $request->boolean('is_active', true),
            'starts_at' => $request->starts_at,
            'expires_at' => $request->expires_at,
        ]);

        return response()->json([
            'success' => true,
            'data' => $coupon,
            'message' => __('messages.coupons.created'),
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
            'data' => $coupon->load('usages'),
        ]);
    }

    // -------------------------------------------------------------------------
    // Seller: update a coupon
    // -------------------------------------------------------------------------

    public function update(Request $request, Coupon $coupon)
    {
        $this->authorizeSeller($coupon);

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|max:50|unique:coupons,code,' . $coupon->id,
            'type' => 'sometimes|in:percentage,fixed',
            'value' => 'sometimes|numeric|min:0.01',
            'min_order_amount' => 'nullable|numeric|min:0',
            'applicable_product_ids' => 'nullable|array',
            'applicable_product_ids.*' => 'integer|exists:products,id',
            'max_uses' => 'nullable|integer|min:1',
            'max_uses_per_user' => 'nullable|integer|min:1',
            'is_one_time_use' => 'boolean',
            'is_active' => 'boolean',
            'starts_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after_or_equal:starts_at',
        ]);

        $sellerId = (int) Auth::id();

        if ($request->filled('applicable_product_ids')) {
            $this->authorizeProducts($request->applicable_product_ids, $sellerId);
        }

        $data = $request->only([
            'name',
            'type',
            'value',
            'min_order_amount',
            'applicable_product_ids',
            'max_uses',
            'max_uses_per_user',
            'is_one_time_use',
            'is_active',
            'starts_at',
            'expires_at',
        ]);

        if ($request->filled('code')) {
            $data['code'] = strtoupper($request->code);
        }

        $coupon->update($data);

        return response()->json([
            'success' => true,
            'data' => $coupon->fresh(),
            'message' => __('messages.coupons.updated'),
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
            'success' => true,
            'message' => __('messages.coupons.status_updated'),
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
            'message' => __('messages.coupons.deleted'),
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
        // Accept both formats:
        //   items: [{product_id, quantity}]  ← sent by Checkout.jsx
        //   product_ids: [1, 2, 3]           ← legacy flat array
        $request->validate([
            'code' => 'required|string',
            'subtotal' => 'required|numeric|min:0',
            // items format (preferred)
            'items' => 'nullable|array',
            'items.*.product_id' => 'required_with:items|integer|exists:products,id',
            'items.*.variant_id' => 'nullable|integer|exists:product_variants,id',
            'items.*.quantity' => 'required_with:items|integer|min:1',
            // legacy flat format
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'integer|exists:products,id',
        ]);

        // Keep cart lines separate because variants can have different prices.
        $items = [];
        if ($request->filled('items')) {
            $items = $request->items;
        } elseif ($request->filled('product_ids')) {
            foreach ($request->product_ids as $id) {
                $items[] = ['product_id' => (int) $id, 'quantity' => 1];
            }
        } else {
            return response()->json([
                'success' => false,
                'message' => __('messages.coupons.cart_empty'),
            ], 422);
        }

        $coupon = Coupon::where('code', strtoupper($request->code))->first();

        if (!$coupon) {
            return response()->json([
                'success' => false,
                'message' => __('messages.coupons.invalid'),
            ], 404);
        }

        // FIX: use getValidationError() instead of isValid() so the buyer
        // gets a specific message: "not started yet", "expired", "limit reached"
        // rather than the generic "no longer valid" for all cases.
        $validationError = $coupon->getValidationError();
        if ($validationError) {
            return response()->json([
                'success' => false,
                'message' => $validationError,
            ], 422);
        }

        // Per-user limit check
        if (Auth::check() && $coupon->hasUserExhausted((int) Auth::id())) {
            return response()->json([
                'success' => false,
                'message' => __('messages.coupons.already_used'),
            ], 422);
        }

        // Minimum order check
        if ($coupon->min_order_amount && $request->subtotal < $coupon->min_order_amount) {
            return response()->json([
                'success' => false,
                'message' => __('messages.coupons.min_order', ['amount' => number_format($coupon->min_order_amount, 0)]),
            ], 422);
        }

        // Calculate from the same sale and wholesale-resolved prices used by checkout.
        $productIds = array_column($items, 'product_id');
        $products = Product::whereIn('id', $productIds)->get()->keyBy('id');
        $variantIds = array_filter(array_column($items, 'variant_id'));
        $variants = ProductVariant::whereIn('id', $variantIds)->get()->keyBy('id');

        $applicableProducts = collect();
        $applicableSubtotal = 0;

        foreach ($items as $item) {
            $product = $products->get((int) $item['product_id']);
            $qty = (int) $item['quantity'];
            $variant = !empty($item['variant_id'])
                ? $variants->get((int) $item['variant_id'])
                : null;

            if (!empty($item['variant_id']) && (!$variant || (int) $variant->product_id !== (int) $product?->id)) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.coupons.not_applicable'),
                ], 422);
            }

            if ($product && $coupon->appliesToProduct($product)) {
                $applicableProducts->push($product);

                $baseItemPrice = $variant ? (float) $variant->price : (float) $product->price;
                $resolved = $variant
                    ? $variant->resolveWholesalePrice((float) $qty)
                    : $product->resolveWholesalePrice((float) $qty);
                $tierPrice = $resolved['price'] !== $baseItemPrice ? $resolved['price'] : null;
                $itemPrice = $product->resolveBuyerUnitPrice($baseItemPrice, $tierPrice)['selling_price'];
                $applicableSubtotal += $itemPrice * $qty;
            }
        }

        if ($applicableProducts->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => __('messages.coupons.not_applicable'),
            ], 422);
        }

        $discountAmount = $coupon->calculateDiscount((float) $applicableSubtotal);

        return response()->json([
            'success' => true,
            'data' => [
                'coupon' => [
                    'id' => $coupon->id,
                    'code' => $coupon->code,
                    'name' => $coupon->name,
                    'type' => $coupon->type,
                    'value' => (float) $coupon->value,
                ],
                'applicable_product_ids' => $applicableProducts->pluck('id')->unique()->values(),
                'discount_amount' => $discountAmount,
                'final_amount' => max(0, $request->subtotal - $discountAmount),
            ],
            'message' => __('messages.coupons.applied'),
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

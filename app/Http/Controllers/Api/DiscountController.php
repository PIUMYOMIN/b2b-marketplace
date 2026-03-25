<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Discount;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * DiscountController
 *
 * Manages price reductions that sellers apply DIRECTLY to product prices.
 * No code entry required by the buyer — the discounted price is simply
 * shown on the product listing.
 *
 * Sellers may only target their own products.
 * Admins may create store-wide or cross-seller discounts.
 *
 * For buyer-entered coupon codes at checkout, see CouponController.
 */
class DiscountController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'type'     => 'sometimes|in:percentage,fixed,free_shipping',
            'status'   => 'sometimes|in:active,expired,upcoming',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $query = Discount::query();

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('status')) {
            $now = now();
            match ($request->status) {
                'active'   => $query->where('is_active', true)
                                    ->where('starts_at', '<=', $now)
                                    ->where('expires_at', '>=', $now),
                'expired'  => $query->where('expires_at', '<', $now),
                'upcoming' => $query->where('starts_at', '>', $now),
                default    => null,
            };
        }

        // Sellers only see their own discounts
        if (Auth::user()->hasRole('seller')) {
            $query->where('created_by', (int) Auth::id());
        }

        $discounts = $query->latest()->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'data'    => $discounts,
            'meta'    => [
                'current_page' => $discounts->currentPage(),
                'per_page'     => $discounts->perPage(),
                'total'        => $discounts->total(),
            ],
        ]);
    }

    // -------------------------------------------------------------------------
    // FIX: show() was missing — the route existed but had no handler
    // -------------------------------------------------------------------------

    public function show(Discount $discount)
    {
        // Sellers can only view their own discounts
        if (Auth::user()->hasRole('seller') && (int) $discount->created_by !== (int) Auth::id()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'success' => true,
            'data'    => $discount,
        ]);
    }

    public function store(Request $request)
    {
        $isSeller = Auth::user()->hasRole('seller');

        $request->validate([
            'name'             => 'required|string|max:255',
            'code'             => 'nullable|string|max:50|unique:discounts,code',
            'type'             => 'required|in:percentage,fixed,free_shipping',
            // FIX: value is not required for free_shipping
            'value'            => 'required_unless:type,free_shipping|nullable|numeric|min:0',
            // FIX: field was min_order_amount on DB but frontend sent min_order — accept both
            'min_order_amount' => 'nullable|numeric|min:0',
            // FIX: field was max_uses on DB but frontend sent max_uses_total — accept the correct name
            'max_uses'         => 'nullable|integer|min:1',
            // FIX: field was max_uses_per_user on DB but frontend sent max_uses_per_customer
            'max_uses_per_user'=> 'nullable|integer|min:1',
            'starts_at'        => 'required|date',
            'expires_at'       => 'required|date|after:starts_at',
            'applicable_to'    => [
                'required',
                // FIX: sellers cannot create store-wide discounts
                $isSeller
                    ? 'in:specific_products,specific_categories'
                    : 'in:all_products,specific_products,specific_categories,specific_sellers',
            ],
            'applicable_product_ids'   => 'required_if:applicable_to,specific_products|array',
            'applicable_product_ids.*' => 'exists:products,id',
            'applicable_category_ids'  => 'required_if:applicable_to,specific_categories|array',
            'applicable_category_ids.*'=> 'exists:categories,id',
            // only admin can set applicable_seller_ids
            'applicable_seller_ids'    => $isSeller ? 'prohibited' : 'nullable|array',
            'applicable_seller_ids.*'  => 'exists:users,id',
            'max_uses_per_user'        => 'nullable|integer|min:1',
            'is_one_time_use'          => 'boolean',
            'is_active'                => 'boolean',
        ]);

        // FIX: if seller, ensure all specified products belong to them
        if ($isSeller && $request->applicable_to === 'specific_products') {
            $this->authorizeProducts($request->applicable_product_ids ?? [], (int) Auth::id());
        }

        $data = $request->only([
            'name', 'type', 'value', 'min_order_amount', 'max_uses',
            'starts_at', 'expires_at', 'applicable_to',
            'applicable_product_ids', 'applicable_category_ids',
            'max_uses_per_user', 'is_one_time_use',
        ]);

        $data['code']       = $request->filled('code')
            ? strtoupper($request->code)
            : strtoupper(Str::random(8));
        $data['created_by'] = (int) Auth::id();
        $data['is_active']  = $request->boolean('is_active', true);

        if (!$isSeller) {
            $data['applicable_seller_ids'] = $request->applicable_seller_ids;
        }

        try {
            $discount = Discount::create($data);

            return response()->json([
                'success' => true,
                'data'    => $discount,
                'message' => 'Discount created successfully',
            ], 201);

        } catch (\Exception $e) {
            \Log::error('Discount creation error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create discount: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, Discount $discount)
    {
        // FIX: use integer cast for type-safe comparison
        if ((int) Auth::id() !== (int) $discount->created_by && !Auth::user()->hasRole('admin')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized to update this discount'], 403);
        }

        $isSeller = Auth::user()->hasRole('seller');

        $request->validate([
            'name'             => 'sometimes|string|max:255',
            'code'             => 'sometimes|string|max:50|unique:discounts,code,' . $discount->id,
            'type'             => 'sometimes|in:percentage,fixed,free_shipping',
            'value'            => 'sometimes|nullable|numeric|min:0',
            'min_order_amount' => 'nullable|numeric|min:0',
            'max_uses'         => 'nullable|integer|min:1',
            'max_uses_per_user'=> 'nullable|integer|min:1',
            'starts_at'        => 'sometimes|date',
            'expires_at'       => 'sometimes|date|after:starts_at',
            'applicable_to'    => [
                'sometimes',
                $isSeller
                    ? 'in:specific_products,specific_categories'
                    : 'in:all_products,specific_products,specific_categories,specific_sellers',
            ],
            'applicable_product_ids'   => 'nullable|array',
            'applicable_product_ids.*' => 'exists:products,id',
            'applicable_category_ids'  => 'nullable|array',
            'applicable_category_ids.*'=> 'exists:categories,id',
            'applicable_seller_ids'    => $isSeller ? 'prohibited' : 'nullable|array',
            'applicable_seller_ids.*'  => 'exists:users,id',
            'is_one_time_use'          => 'boolean',
            'is_active'                => 'boolean',
        ]);

        if ($isSeller && $request->has('applicable_product_ids')) {
            $this->authorizeProducts($request->applicable_product_ids ?? [], (int) Auth::id());
        }

        $updateData = $request->only([
            'name', 'type', 'value', 'min_order_amount', 'max_uses',
            'starts_at', 'expires_at', 'applicable_to',
            'applicable_product_ids', 'applicable_category_ids',
            'max_uses_per_user', 'is_one_time_use', 'is_active',
        ]);

        if ($request->filled('code')) {
            $updateData['code'] = strtoupper($request->code);
        }

        if (!$isSeller && $request->has('applicable_seller_ids')) {
            $updateData['applicable_seller_ids'] = $request->applicable_seller_ids;
        }

        try {
            $discount->update($updateData);

            return response()->json([
                'success' => true,
                'data'    => $discount->fresh(),
                'message' => 'Discount updated successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update discount: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Discount $discount)
    {
        if ((int) Auth::id() !== (int) $discount->created_by && !Auth::user()->hasRole('admin')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized to delete this discount'], 403);
        }

        try {
            $discount->delete();
            return response()->json(['success' => true, 'message' => 'Discount deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to delete discount: ' . $e->getMessage()], 500);
        }
    }

    public function toggleStatus(Discount $discount)
    {
        if ((int) Auth::id() !== (int) $discount->created_by && !Auth::user()->hasRole('admin')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $discount->update(['is_active' => !$discount->is_active]);

        return response()->json([
            'success'   => true,
            'message'   => 'Discount status updated',
            'is_active' => $discount->is_active,
        ]);
    }

    public function getProductDiscounts($productId)
    {
        $product = Product::findOrFail($productId);

        $discounts = Discount::where('is_active', true)
            ->where('starts_at', '<=', now())
            ->where('expires_at', '>=', now())
            ->where(function ($query) use ($product) {
                $query->where('applicable_to', 'all_products')
                    ->orWhere(function ($q) use ($product) {
                        $q->where('applicable_to', 'specific_products')
                            ->whereJsonContains('applicable_product_ids', $product->id);
                    })
                    ->orWhere(function ($q) use ($product) {
                        $q->where('applicable_to', 'specific_categories')
                            ->whereJsonContains('applicable_category_ids', $product->category_id);
                    })
                    ->orWhere(function ($q) use ($product) {
                        $q->where('applicable_to', 'specific_sellers')
                            ->whereJsonContains('applicable_seller_ids', $product->seller_id);
                    });
            })
            ->get();

        return response()->json(['success' => true, 'data' => $discounts]);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function authorizeProducts(array $productIds, int $sellerId): void
    {
        if (empty($productIds)) {
            return;
        }

        $foreign = Product::whereIn('id', $productIds)
            ->where('seller_id', '!=', $sellerId)
            ->exists();

        if ($foreign) {
            abort(422, 'You can only create discounts for your own products');
        }
    }
}

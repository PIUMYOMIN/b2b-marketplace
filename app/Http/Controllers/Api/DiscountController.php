<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Discount;
use App\Models\Product;
use App\Support\ProductAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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

        $discounts = $query->latest()->paginate($request->input('per_page', 100));

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
        abort_unless(ProductAccess::canManageCatalog($request->user()), 403);

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
            'starts_at'        => 'nullable|date',
            'expires_at'       => 'nullable|date|after_or_equal:starts_at',
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
        $data['starts_at']  = $request->starts_at ?: now();
        $data['expires_at'] = $request->expires_at ?: now()->addYear();

        if (!$isSeller) {
            $data['applicable_seller_ids'] = $request->applicable_seller_ids;
        }

        try {
            $discount = DB::transaction(function () use ($data) {
                $discount = Discount::create($data);
                if ($discount->is_active) {
                    $this->applyDiscountToProducts($discount);
                }

                return $discount;
            });

            return response()->json([
                'success' => true,
                'data'    => $discount,
                'message' => 'Discount created successfully',
            ], 201);

        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
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
        abort_unless(ProductAccess::canManageCatalog($request->user()), 403);

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
            'starts_at'        => 'nullable|date',
            'expires_at'       => 'nullable|date|after_or_equal:starts_at',
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

        if ($request->exists('is_active')) {
            $updateData['is_active'] = $request->boolean('is_active');
        }

        if ($request->filled('code')) {
            $updateData['code'] = strtoupper($request->code);
        }

        if (!$isSeller && $request->has('applicable_seller_ids')) {
            $updateData['applicable_seller_ids'] = $request->applicable_seller_ids;
        }

        $previousDiscount = clone $discount;
        try {
            $fresh = DB::transaction(function () use ($discount, $updateData, $previousDiscount) {
                $previousIds = $this->resolveTargetProductIds($previousDiscount);
                $this->claimLegacyDiscountProducts($previousDiscount, $previousIds);
                $discount->update($updateData);
                $fresh = $discount->fresh();
                $this->clearDiscountFromProductIds($previousDiscount, $previousIds);
                if ($fresh && $fresh->is_active) {
                    $this->applyDiscountToProducts($fresh);
                }

                return $fresh;
            });

            return response()->json([
                'success' => true,
                'data'    => $fresh,
                'message' => 'Discount updated successfully',
            ]);

        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update discount: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Discount $discount)
    {
        abort_unless(ProductAccess::canManageCatalog(Auth::user()), 403);

        if ((int) Auth::id() !== (int) $discount->created_by && !Auth::user()->hasRole('admin')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized to delete this discount'], 403);
        }

        try {
            DB::transaction(function () use ($discount) {
                $targetIds = $this->resolveTargetProductIds($discount);
                $this->claimLegacyDiscountProducts($discount, $targetIds);
                $discount->delete();
                $this->clearDiscountFromProductIds($discount, $targetIds);
            });

            return response()->json(['success' => true, 'message' => 'Discount deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to delete discount: ' . $e->getMessage()], 500);
        }
    }

    public function toggleStatus(Discount $discount)
    {
        abort_unless(ProductAccess::canManageCatalog(Auth::user()), 403);

        if ((int) Auth::id() !== (int) $discount->created_by && !Auth::user()->hasRole('admin')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $previousDiscount = clone $discount;
        $fresh = DB::transaction(function () use ($discount, $previousDiscount) {
            $targetIds = $this->resolveTargetProductIds($previousDiscount);
            $discount->update(['is_active' => !$discount->is_active]);
            $fresh = $discount->fresh();

            if ($fresh && $fresh->is_active) {
                $this->applyDiscountToProducts($fresh);
            } else {
                $this->claimLegacyDiscountProducts($previousDiscount, $targetIds);
                $this->clearDiscountFromProductIds($previousDiscount, $targetIds);
            }

            return $fresh;
        });

        return response()->json([
            'success'   => true,
            'message'   => 'Discount status updated',
            'is_active' => (bool) $fresh?->is_active,
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
            ->with('creator')
            ->get()
            ->filter(fn (Discount $discount) => $discount->isOwnedByProductSeller($product))
            ->values();

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

    private function resolveTargetProductIds(Discount $discount): array
    {
        $creator = $discount->creator;
        if (! $creator) {
            return [];
        }

        $sellerId = $creator->hasRole('admin') ? null : (int) $creator->id;

        if ($discount->applicable_to === 'specific_products') {
            $productIds = array_values(array_unique(array_map('intval', $discount->applicable_product_ids ?? [])));
            if ($sellerId) {
                return Product::whereIn('id', $productIds)
                    ->where('seller_id', $sellerId)
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->all();
            }

            return $productIds;
        }

        if ($discount->applicable_to === 'specific_categories') {
            $categoryIds = array_map('intval', $discount->applicable_category_ids ?? []);
            if (! $categoryIds) {
                return [];
            }

            $query = Product::query()->whereIn('category_id', $categoryIds);
            if ($sellerId) {
                $query->where('seller_id', $sellerId);
            }

            return $query->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        if ($discount->applicable_to === 'all_products' && $sellerId) {
            return Product::where('seller_id', $sellerId)->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        if ($discount->applicable_to === 'specific_sellers') {
            $sellerIds = array_map('intval', $discount->applicable_seller_ids ?? []);
            return Product::whereIn('seller_id', $sellerIds)->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        return [];
    }

    private function applyDiscountToProducts(Discount $discount): void
    {
        if ($discount->type === 'free_shipping') {
            return;
        }

        $products = Product::whereIn('id', $this->resolveTargetProductIds($discount))
            ->lockForUpdate()
            ->get();

        if ($discount->type === 'fixed') {
            $invalidProducts = $products->filter(function (Product $product) use ($discount) {
                $price = (float) $product->price;
                $salePrice = (float) $discount->value;

                return $price <= 0 || $salePrice <= 0 || $salePrice >= $price;
            });

            if ($invalidProducts->isNotEmpty()) {
                throw new \InvalidArgumentException(
                    'Sale price must be lower than each selected product regular price.'
                );
            }
        }

        foreach ($products as $product) {
            if ((int) $product->discount_rule_id === (int) $discount->id) {
                continue;
            }

            if ($product->discount_rule_id !== null) {
                continue;
            }

            if ($product->hasDiscountValue()) {
                $legacyOwner = $this->findLegacyDiscountOwner($product, (int) $discount->id);
                if (! $legacyOwner) {
                    throw new \InvalidArgumentException(
                        'A product with an independently managed sale cannot be overwritten by a discount rule.'
                    );
                }

                $product->update(['discount_rule_id' => $legacyOwner->id]);
            }
        }

        foreach ($products as $product) {
            $this->applyDiscountToProduct($product, $discount);
        }

        $this->flushPublicCatalogCaches();
    }

    private function applyDiscountToProduct(Product $product, Discount $discount): void
    {
        $update = [
            'discount_start' => $discount->starts_at,
            'discount_end' => $discount->expires_at,
            'is_on_sale' => true,
            'discount_type' => $discount->type,
            'discount_rule_id' => $discount->id,
        ];

        if ($discount->type === 'percentage') {
            $update['discount_percentage'] = $discount->value;
            $update['discount_price'] = null;
        } else {
            $update['discount_price'] = (float) $discount->value;
            $update['discount_percentage'] = null;
        }

        $product->update($update);
    }

    private function clearDiscountFromProductIds(Discount $discount, array $productIds): void
    {
        $ids = array_values(array_filter(array_map('intval', $productIds)));
        if (! $ids) {
            return;
        }

        foreach (Product::whereIn('id', $ids)->lockForUpdate()->get() as $product) {
            if ((int) $product->discount_rule_id !== (int) $discount->id) {
                continue;
            }

            $product->update([
                'discount_price' => null,
                'discount_percentage' => null,
                'discount_start' => null,
                'discount_end' => null,
                'discount_type' => 'none',
                'discount_rule_id' => null,
                'is_on_sale' => false,
            ]);

            $replacement = $this->findReplacementDiscount($product);
            if ($replacement) {
                $this->applyDiscountToProduct($product, $replacement);
            }
        }

        $this->flushPublicCatalogCaches();
    }

    private function claimLegacyDiscountProducts(Discount $discount, array $productIds): void
    {
        foreach (Product::whereIn('id', $productIds)
            ->whereNull('discount_rule_id')
            ->lockForUpdate()
            ->get() as $product) {
            if ($this->productMatchesDiscount($product, $discount)) {
                $product->update(['discount_rule_id' => $discount->id]);
            }
        }
    }

    private function findLegacyDiscountOwner(Product $product, int $exceptDiscountId): ?Discount
    {
        return Discount::query()
            ->where('is_active', true)
            ->where('id', '!=', $exceptDiscountId)
            ->orderByDesc('id')
            ->get()
            ->first(fn (Discount $candidate) =>
                $candidate->isApplicableToProduct($product->id, $product->category_id, $product->seller_id)
                && $this->productMatchesDiscount($product, $candidate)
            );
    }

    private function findReplacementDiscount(Product $product): ?Discount
    {
        return Discount::query()
            ->where('is_active', true)
            ->where('type', '!=', 'free_shipping')
            ->where('starts_at', '<=', now())
            ->where('expires_at', '>=', now())
            ->orderByDesc('id')
            ->get()
            ->first(fn (Discount $candidate) =>
                $candidate->isApplicableToProduct($product->id, $product->category_id, $product->seller_id)
                && $this->isValidFixedPrice($candidate, $product)
            );
    }

    private function isValidFixedPrice(Discount $discount, Product $product): bool
    {
        return $discount->type !== 'fixed'
            || ((float) $discount->value > 0 && (float) $discount->value < (float) $product->price);
    }

    private function productMatchesDiscount(Product $product, Discount $discount): bool
    {
        if ($discount->type === 'percentage') {
            return $product->discount_type === 'percentage'
                && (float) $product->discount_percentage === (float) $discount->value
                && $product->discount_start?->toDateString() === $discount->starts_at?->toDateString()
                && $product->discount_end?->toDateString() === $discount->expires_at?->toDateString();
        }

        return $discount->type === 'fixed'
            && $product->discount_type === 'fixed'
            && (float) $product->discount_price === (float) $discount->value
            && $product->discount_start?->toDateString() === $discount->starts_at?->toDateString()
            && $product->discount_end?->toDateString() === $discount->expires_at?->toDateString();
    }

    private function flushPublicCatalogCaches(): void
    {
        Cache::forget('categories_tree');
        Cache::forget('categories_tree_v2');
        Cache::forget('featured_products');
    }
}

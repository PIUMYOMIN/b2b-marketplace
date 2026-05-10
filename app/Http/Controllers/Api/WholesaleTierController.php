<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductWholesaleTier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * WholesaleTierController
 *
 * Manages volume-based pricing tiers for a seller's products/variants.
 *
 * All routes are seller-scoped and require auth:sanctum + role:seller.
 * Ownership is verified on every mutation — sellers cannot touch other sellers' tiers.
 *
 * Routes (registered under /seller/products/{product}/wholesale-tiers):
 *
 *   GET    /                    → index()   List all tiers for a product
 *   POST   /                    → store()   Create one tier
 *   PUT    /{tier}              → update()  Edit one tier
 *   DELETE /{tier}              → destroy() Soft-delete one tier
 *   POST   /sync                → sync()    Replace all tiers atomically (bulk save)
 *
 * Variant-level tiers use the optional `variant_id` body field.
 */
class WholesaleTierController extends Controller
{
    // -------------------------------------------------------------------------
    // LIST
    // -------------------------------------------------------------------------

    /**
     * GET /seller/products/{product}/wholesale-tiers
     *
     * Returns all tiers for the product, grouped so the frontend can render
     * a unified table (product-level tiers first, then per-variant tiers).
     */
    public function index(Product $product): JsonResponse
    {
        $this->authorizeProduct($product);

        $tiers = ProductWholesaleTier::where('product_id', $product->id)
            ->orderByRaw('variant_id IS NOT NULL')  // product-level first
            ->orderBy('min_qty')
            ->get()
            ->map(fn($t) => $this->formatTier($t));

        return response()->json([
            'success' => true,
            'data'    => $tiers,
            'count'   => $tiers->count(),
        ]);
    }

    // -------------------------------------------------------------------------
    // CREATE
    // -------------------------------------------------------------------------

    /**
     * POST /seller/products/{product}/wholesale-tiers
     *
     * Body:
     *   variant_id?        int    – omit for product-level tier
     *   min_qty            int    – must be >= product.moq
     *   price_per_unit     float  – per-unit price at this tier
     *   label?             string – e.g. "Wholesale", "Bulk"
     *   sort_order?        int
     *   is_active?         bool   – default true
     */
    public function store(Request $request, Product $product): JsonResponse
    {
        $this->authorizeProduct($product);

        $data = $request->validate([
            'variant_id'     => ['nullable', 'integer', 'exists:product_variants,id'],
            'min_qty'        => ['required', 'integer', 'min:1'],
            'price_per_unit' => ['required', 'numeric', 'min:0'],
            'label'          => ['nullable', 'string', 'max:60'],
            'sort_order'     => ['nullable', 'integer', 'min:0'],
            'is_active'      => ['nullable', 'boolean'],
        ]);

        // Guard: variant must belong to this product
        if (!empty($data['variant_id'])) {
            $this->authorizeVariant($data['variant_id'], $product);
        }

        // Guard: min_qty must be >= the applicable MOQ.
        // If this tier targets a specific variant, validate against variant.moq
        // (falling back to product.moq when variant.moq is null).
        $moq = $product->moq ?? 1;
        if (!empty($data['variant_id'])) {
            $variantMoq = ProductVariant::where('id', $data['variant_id'])->value('moq');
            $moq = $variantMoq ?? $moq;
        }

        if ($data['min_qty'] < (int) $moq) {
            return response()->json([
                'success' => false,
                'message' => "min_qty ({$data['min_qty']}) must be >= MOQ ({$moq}).",
            ], 422);
        }


        // Guard: no duplicate (product_id + variant_id + min_qty)
        $exists = ProductWholesaleTier::where('product_id', $product->id)
            ->where('variant_id', $data['variant_id'] ?? null)
            ->where('min_qty', $data['min_qty'])
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => "A tier for min_qty {$data['min_qty']} already exists.",
            ], 422);
        }

        $tier = ProductWholesaleTier::create([
            'product_id'     => $product->id,
            'variant_id'     => $data['variant_id'] ?? null,
            'min_qty'        => $data['min_qty'],
            'price_per_unit' => $data['price_per_unit'],
            'discount_pct'   => $this->calcDiscount($product->price, $data['price_per_unit']),
            'label'          => $data['label'] ?? null,
            'sort_order'     => $data['sort_order'] ?? 0,
            'is_active'      => $data['is_active'] ?? true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Wholesale tier created.',
            'data'    => $this->formatTier($tier),
        ], 201);
    }

    // -------------------------------------------------------------------------
    // UPDATE
    // -------------------------------------------------------------------------

    /**
     * PUT /seller/products/{product}/wholesale-tiers/{tier}
     */
    public function update(Request $request, Product $product, ProductWholesaleTier $tier): JsonResponse
    {
        $this->authorizeProduct($product);
        $this->authorizeTier($tier, $product);

        $data = $request->validate([
            'min_qty'        => ['sometimes', 'integer', 'min:1'],
            'price_per_unit' => ['sometimes', 'numeric', 'min:0'],
            'label'          => ['nullable', 'string', 'max:60'],
            'sort_order'     => ['nullable', 'integer', 'min:0'],
            'is_active'      => ['nullable', 'boolean'],
        ]);

        if (isset($data['min_qty'])) {
            // Guard: min_qty must be >= the applicable MOQ.
            // If this tier is variant-scoped, validate against that variant's MOQ.
            $moq = $product->moq ?? 1;
            if ($tier->variant_id) {
                $variantMoq = ProductVariant::where('id', $tier->variant_id)->value('moq');
                $moq = $variantMoq ?? $moq;
            }

            if ($data['min_qty'] < (int) $moq) {
                return response()->json([
                    'success' => false,
                    'message' => "min_qty ({$data['min_qty']}) must be >= MOQ ({$moq}).",
                ], 422);
            }


            // Duplicate check (excluding self)
            $exists = ProductWholesaleTier::where('product_id', $product->id)
                ->where('variant_id', $tier->variant_id)
                ->where('min_qty', $data['min_qty'])
                ->where('id', '!=', $tier->id)
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => "Another tier for min_qty {$data['min_qty']} already exists.",
                ], 422);
            }
        }

        if (isset($data['price_per_unit'])) {
            $data['discount_pct'] = $this->calcDiscount($product->price, $data['price_per_unit']);
        }

        $tier->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Wholesale tier updated.',
            'data'    => $this->formatTier($tier->fresh()),
        ]);
    }

    // -------------------------------------------------------------------------
    // DELETE
    // -------------------------------------------------------------------------

    /**
     * DELETE /seller/products/{product}/wholesale-tiers/{tier}
     */
    public function destroy(Product $product, ProductWholesaleTier $tier): JsonResponse
    {
        $this->authorizeProduct($product);
        $this->authorizeTier($tier, $product);

        $tier->delete();

        return response()->json([
            'success' => true,
            'message' => 'Wholesale tier removed.',
        ]);
    }

    // -------------------------------------------------------------------------
    // SYNC  (atomic bulk replace)
    // -------------------------------------------------------------------------

    /**
     * POST /seller/products/{product}/wholesale-tiers/sync
     *
     * Replaces ALL tiers for the given scope (product-level or one variant) in a
     * single transaction. The frontend sends the complete desired state; this
     * endpoint diffs and upserts.
     *
     * Body:
     *   variant_id?  int|null   – scope: null = product-level, int = one variant
     *   tiers        array      – full desired list (min_qty, price_per_unit, label?, sort_order?)
     *
     * Tiers not in the list are deleted. Existing min_qty matches are updated.
     * New min_qty values are inserted.
     */
    public function sync(Request $request, Product $product): JsonResponse
    {
        $this->authorizeProduct($product);

        $validated = $request->validate([
            'variant_id'             => ['nullable', 'integer', 'exists:product_variants,id'],
            'tiers'                  => ['required', 'array'],
            'tiers.*.min_qty'        => ['required', 'integer', 'min:1'],
            'tiers.*.price_per_unit' => ['required', 'numeric', 'min:0'],
            'tiers.*.label'          => ['nullable', 'string', 'max:60'],
            'tiers.*.sort_order'     => ['nullable', 'integer', 'min:0'],
            'tiers.*.is_active'      => ['nullable', 'boolean'],
        ]);

        $variantId = $validated['variant_id'] ?? null;
        $incoming  = collect($validated['tiers']);

        if ($variantId) {
            $this->authorizeVariant($variantId, $product);
        }

        // MOQ guard
        $moq = $product->moq ?? 1;
        foreach ($incoming as $row) {
            if ($row['min_qty'] < $moq) {
                return response()->json([
                    'success' => false,
                    'message' => "All tier min_qty values must be >= product MOQ ({$moq}). Got: {$row['min_qty']}.",
                ], 422);
            }
        }

        DB::transaction(function () use ($product, $variantId, $incoming) {
            // Delete tiers not in the incoming list
            $incomingQtys = $incoming->pluck('min_qty')->toArray();

            ProductWholesaleTier::where('product_id', $product->id)
                ->where(fn($q) => $variantId
                    ? $q->where('variant_id', $variantId)
                    : $q->whereNull('variant_id')
                )
                ->whereNotIn('min_qty', $incomingQtys)
                ->delete();

            // Upsert each tier
            foreach ($incoming as $idx => $row) {
                ProductWholesaleTier::updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'variant_id' => $variantId,
                        'min_qty'    => $row['min_qty'],
                    ],
                    [
                        'price_per_unit' => $row['price_per_unit'],
                        'discount_pct'   => $this->calcDiscount($product->price, $row['price_per_unit']),
                        'label'          => $row['label'] ?? null,
                        'sort_order'     => $row['sort_order'] ?? $idx,
                        'is_active'      => $row['is_active'] ?? true,
                    ]
                );
            }
        });

        // Return fresh list
        $tiers = ProductWholesaleTier::where('product_id', $product->id)
            ->where(fn($q) => $variantId
                ? $q->where('variant_id', $variantId)
                : $q->whereNull('variant_id')
            )
            ->orderBy('min_qty')
            ->get()
            ->map(fn($t) => $this->formatTier($t));

        return response()->json([
            'success' => true,
            'message' => 'Wholesale tiers synced.',
            'data'    => $tiers,
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function formatTier(ProductWholesaleTier $tier): array
    {
        return [
            'id'             => $tier->id,
            'product_id'     => $tier->product_id,
            'variant_id'     => $tier->variant_id,
            'min_qty'        => $tier->min_qty,
            'price_per_unit' => (float) $tier->price_per_unit,
            'discount_pct'   => (float) $tier->discount_pct,
            'label'          => $tier->label,
            'sort_order'     => $tier->sort_order,
            'is_active'      => (bool) $tier->is_active,
        ];
    }

    private function calcDiscount(float|string|null $basePrice, float|string $tierPrice): float
    {
        $base = (float) $basePrice;
        if ($base <= 0) return 0;
        return round(max(0, (1 - ((float) $tierPrice / $base)) * 100), 2);
    }

    private function authorizeProduct(Product $product): void
    {
        $user = request()->user();
        if ((int) $product->seller_id !== (int) $user->id) {
            abort(403, 'You do not own this product.');
        }
    }

    private function authorizeTier(ProductWholesaleTier $tier, Product $product): void
    {
        if ((int) $tier->product_id !== (int) $product->id) {
            abort(404, 'Tier not found for this product.');
        }
    }

    private function authorizeVariant(int $variantId, Product $product): void
    {
        $exists = $product->variants()->where('id', $variantId)->exists();
        if (!$exists) {
            abort(422, 'variant_id does not belong to this product.');
        }
    }
}
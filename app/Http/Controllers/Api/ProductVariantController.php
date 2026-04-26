<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductVariant\GenerateVariantsRequest;
use App\Http\Requests\ProductVariant\StoreProductVariantRequest;
use App\Http\Requests\ProductVariant\UpdateProductVariantRequest;
use App\Http\Resources\ProductVariantResource;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\ProductVariantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ProductVariantController extends Controller
{
    public function __construct(protected ProductVariantService $variantService) {}

    /**
     * GET /seller/products/{product}/variants
     * List all variants for a product with their option value combinations.
     */
    public function index(Product $product): JsonResponse
    {
        $this->authorizeProduct($product);

        $variants = $product->variants()
            ->with('optionValues.option')
            ->orderBy('position')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => ProductVariantResource::collection($variants),
        ]);
    }

    /**
     * POST /seller/products/{product}/variants/generate
     *
     * Auto-generates all possible variants from the product's existing options.
     * e.g. Color [Red, Blue] × Size [S, M, L] → 6 variants
     *
     * Already-existing combinations are skipped (safe to call multiple times).
     */
    public function generate(GenerateVariantsRequest $request, Product $product): JsonResponse
    {
        $this->authorizeProduct($product);

        if (!$product->options()->exists()) {
            return response()->json([
                'success' => false,
                'message' => __('messages.products.options_required_for_generate'),
            ], 422);
        }

        $created = $this->variantService->generateCombinations($product, $request->validated());

        return response()->json([
            'success' => true,
            'message' => __('messages.products.variants_generated', ['count' => count($created)]),
            'data'    => ProductVariantResource::collection(collect($created)),
        ]);
    }

    /**
     * POST /seller/products/{product}/variants
     * Manually create a single variant with specific option value IDs.
     */
    public function store(StoreProductVariantRequest $request, Product $product): JsonResponse
    {
        $this->authorizeProduct($product);

        $data = $request->validated();
        $optionValueIds = $data['option_value_ids'];
        unset($data['option_value_ids']);

        // Check for duplicate combination
        if ($this->variantService->variantExistsForValues($product->id, $optionValueIds)) {
            return response()->json([
                'success' => false,
                'message' => __('messages.products.variant_combination_exists'),
            ], 422);
        }

        $variant = DB::transaction(function () use ($product, $data, $optionValueIds) {
            $variant = $product->variants()->create(array_merge($data, [
                'position' => ($product->variants()->max('position') ?? 0) + 1,
            ]));
            $variant->optionValues()->attach($optionValueIds);
            return $variant;
        });

        return response()->json([
            'success' => true,
            'message' => __('messages.products.variant_created'),
            'data'    => new ProductVariantResource($variant->load('optionValues.option')),
        ], 201);
    }

    /**
     * PUT /seller/products/{product}/variants/{variant}
     * Update price, quantity, moq, sku, image, active status of a variant.
     */
    public function update(
        UpdateProductVariantRequest $request,
        Product $product,
        ProductVariant $variant
    ): JsonResponse {
        $this->authorizeProduct($product);
        $this->ensureVariantBelongsToProduct($variant, $product);

        $variant->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => __('messages.products.variant_updated'),
            'data'    => new ProductVariantResource($variant->load('optionValues.option')),
        ]);
    }

    /**
     * DELETE /seller/products/{product}/variants/{variant}
     * Soft-delete a specific variant.
     */
    public function destroy(Product $product, ProductVariant $variant): JsonResponse
    {
        $this->authorizeProduct($product);
        $this->ensureVariantBelongsToProduct($variant, $product);

        $variant->delete();

        return response()->json([
            'success' => true,
            'message' => __('messages.products.variant_deleted'),
        ]);
    }

    /**
     * PATCH /seller/products/{product}/variants/{variant}/toggle
     * Toggle is_active for a variant.
     */
    public function toggle(Product $product, ProductVariant $variant): JsonResponse
    {
        $this->authorizeProduct($product);
        $this->ensureVariantBelongsToProduct($variant, $product);

        $variant->update(['is_active' => !$variant->is_active]);

        return response()->json([
            'success'   => true,
            'is_active' => $variant->is_active,
            'message'   => $variant->is_active
                ? __('messages.products.variant_activated')
                : __('messages.products.variant_deactivated'),
        ]);
    }

    // -------------------------------------------------------------------------

    private function authorizeProduct(Product $product): void
    {
        $user = request()->user();
        if (!$user->hasRole('admin') && $product->seller_id !== $user->id) {
            abort(403, __('messages.products.unauthorized_update'));
        }
    }

    private function ensureVariantBelongsToProduct(ProductVariant $variant, Product $product): void
    {
        if ($variant->product_id !== $product->id) {
            abort(404, __('messages.products.variant_not_found'));
        }
    }
}
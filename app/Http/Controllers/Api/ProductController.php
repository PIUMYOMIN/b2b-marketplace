<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Http\Resources\ProductListResource;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    // =========================================================================
    // PUBLIC ENDPOINTS
    // =========================================================================

    /**
     * GET /products
     * Public product listing with filtering and sorting.
     */
    public function indexPublic(Request $request): JsonResponse
    {
        $query = Product::approved()
            ->with(['seller.sellerProfile', 'category'])
            ->withCount('reviews');

        // ── Filters ──────────────────────────────────────────────────────────
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('product_type')) {
            $query->where('product_type', $request->product_type);
        }

        if ($request->filled('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }

        if ($request->filled('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

        if ($request->boolean('in_stock')) {
            // Only products that have at least one active variant with quantity > 0
            $query->whereHas('activeVariants', fn($q) => $q->where('quantity', '>', 0));
        }

        if ($request->boolean('is_featured')) {
            $query->where('is_featured', true);
        }

        if ($request->boolean('on_sale')) {
            $query->where('is_on_sale', true);
        }

        // ── Search ───────────────────────────────────────────────────────────
        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(function ($query) use ($q) {
                $query->where('name_en', 'like', "%{$q}%")
                      ->orWhere('name_mm', 'like', "%{$q}%")
                      ->orWhere('description_en', 'like', "%{$q}%")
                      ->orWhere('brand', 'like', "%{$q}%");
            });
        }

        // ── Sorting ───────────────────────────────────────────────────────────
        match ($request->get('sort', 'newest')) {
            'price_asc'   => $query->orderBy('price', 'asc'),
            'price_desc'  => $query->orderBy('price', 'desc'),
            'popular'     => $query->orderByDesc('sales'),
            'rating'      => $query->orderByDesc('average_rating'),
            default       => $query->orderByDesc('listed_at'),
        };

        $products = $query->paginate($request->get('per_page', 24));

        return response()->json([
            'success' => true,
            'data'    => ProductListResource::collection($products),
            'meta'    => [
                'current_page' => $products->currentPage(),
                'last_page'    => $products->lastPage(),
                'total'        => $products->total(),
                'per_page'     => $products->perPage(),
            ],
        ]);
    }

    /**
     * GET /products/{slugOrId}
     * Public product detail — loads options + active variants for the picker UI.
     */
    public function showPublic(string $slugOrId): JsonResponse
    {
        $product = Product::approved()
            ->with([
                'seller.sellerProfile',
                'category',
                'options.values',
                'activeVariants.optionValues.option',
            ])
            ->where(fn($q) => $q->where('slug_en', $slugOrId)->orWhere('id', $slugOrId))
            ->firstOrFail();

        // Increment view count (fire-and-forget)
        $product->increment('views');

        return response()->json([
            'success' => true,
            'data'    => new ProductResource($product),
        ]);
    }

    /**
     * GET /products/category/{categoryId}
     * Products filtered by category — reuses indexPublic logic.
     */
    public function categoryProducts(Request $request, int $categoryId): JsonResponse
    {
        $request->merge(['category_id' => $categoryId]);
        return $this->indexPublic($request);
    }

    // =========================================================================
    // SELLER ENDPOINTS
    // =========================================================================

    /**
     * GET /seller/products
     * Seller's own products.
     */
    public function myProducts(Request $request): JsonResponse
    {
        $products = Product::forSeller($request->user()->id)
            ->with(['category'])
            ->withCount('variants')
            ->orderByDesc('created_at')
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data'    => ProductListResource::collection($products),
            'meta'    => [
                'current_page' => $products->currentPage(),
                'last_page'    => $products->lastPage(),
                'total'        => $products->total(),
            ],
        ]);
    }

    /**
     * GET /seller/products/{id}/edit
     * Full product data including all options, values, and all variants (not just active).
     */
    public function getProductForEdit(int $id): JsonResponse
    {
        $product = Product::where('id', $id)
            ->where('seller_id', request()->user()->id)
            ->with([
                'category',
                'options.values',
                'variants.optionValues.option',
            ])
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data'    => new ProductResource($product),
        ]);
    }

    /**
     * POST /seller/products
     * Create a new product (without options/variants — those come via separate endpoints).
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        $data = $request->validated();

        $data['seller_id'] = $request->user()->id;
        $data['slug_en']   = $this->generateSlug($data['name_en']);
        $data['slug_mm']   = isset($data['name_mm'])
            ? $this->generateSlug($data['name_mm'], 'products', 'slug_mm')
            : null;
        $data['status']    = 'pending';
        $data['listed_at'] = now();

        $product = Product::create($data);

        return response()->json([
            'success' => true,
            'message' => __('messages.products.created'),
            'data'    => new ProductResource($product),
        ], 201);
    }

    /**
     * PUT /seller/products/{slugOrId}
     * Update product fields.
     */
    public function update(UpdateProductRequest $request, string $slugOrId): JsonResponse
    {
        $product = Product::where(
            fn($q) => $q->where('slug_en', $slugOrId)->orWhere('id', $slugOrId)
        )->firstOrFail();

        if (!$request->user()->hasRole('admin') && $product->seller_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => __('messages.products.unauthorized_update')], 403);
        }

        $data = $request->validated();

        if (isset($data['name_en']) && $data['name_en'] !== $product->name_en) {
            $data['slug_en'] = $this->generateSlug($data['name_en'], 'products', 'slug_en', $product->id);
        }

        // Changing product_type — reset type-specific fields
        if (isset($data['product_type']) && $data['product_type'] !== $product->product_type) {
            if ($data['product_type'] !== 'digital') {
                $data['file_url'] = null;
                $data['file_type'] = null;
                $data['file_size'] = null;
            }
        }

        $product->update($data);

        return response()->json([
            'success' => true,
            'message' => __('messages.products.updated'),
            'data'    => new ProductResource($product->fresh(['options.values', 'activeVariants.optionValues.option'])),
        ]);
    }

    /**
     * DELETE /seller/products/{product}
     * Soft-delete a product.
     */
    public function destroy(Product $product): JsonResponse
    {
        $user = request()->user();

        if (!$user->hasRole('admin') && $product->seller_id !== $user->id) {
            return response()->json(['success' => false, 'message' => __('messages.products.unauthorized_delete')], 403);
        }

        $product->delete();

        return response()->json([
            'success' => true,
            'message' => __('messages.products.deleted'),
        ]);
    }

    // =========================================================================
    // ADMIN ENDPOINTS
    // =========================================================================

    /**
     * GET /admin/products
     * All products with status filter.
     */
    public function adminIndex(Request $request): JsonResponse
    {
        $query = Product::with(['seller.sellerProfile', 'category'])
            ->withCount('variants');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('product_type')) {
            $query->where('product_type', $request->product_type);
        }

        if ($request->filled('seller_id')) {
            $query->where('seller_id', $request->seller_id);
        }

        $products = $query->orderByDesc('created_at')
            ->paginate($request->get('per_page', 30));

        return response()->json([
            'success' => true,
            'data'    => ProductListResource::collection($products),
            'meta'    => [
                'current_page' => $products->currentPage(),
                'last_page'    => $products->lastPage(),
                'total'        => $products->total(),
            ],
        ]);
    }

    /**
     * POST /admin/products/{product}/approve
     */
    public function approve(Product $product): JsonResponse
    {
        $product->update([
            'status'      => 'approved',
            'approved_at' => now(),
            'rejection_reason' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => __('messages.products.approved'),
        ]);
    }

    /**
     * POST /admin/products/{product}/reject
     */
    public function reject(Request $request, Product $product): JsonResponse
    {
        $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $product->update([
            'status'           => 'rejected',
            'rejection_reason' => $request->reason,
        ]);

        return response()->json([
            'success' => true,
            'message' => __('messages.products.rejected'),
        ]);
    }

    /**
     * PATCH /admin/products/{product}/toggle-status
     */
    public function toggleStatus(Product $product): JsonResponse
    {
        $product->update(['is_active' => !$product->is_active]);

        return response()->json([
            'success'   => true,
            'is_active' => $product->is_active,
        ]);
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    private function generateSlug(
        string $text,
        string $table = 'products',
        string $column = 'slug_en',
        ?int $excludeId = null
    ): string {
        $base = Str::slug($text);
        $slug = $base;
        $i    = 1;

        while (
            DB::table($table)
                ->where($column, $slug)
                ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
                ->exists()
        ) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use App\Models\ProductReview;
use App\Models\Discount;
use App\Http\Resources\ProductResource;
use App\Http\Resources\ReviewResource;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Http\Resources\ProductListResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    /**
     * Display a listing of products with optional filters
     */
    public function indexPublic(Request $request): JsonResponse
    {
        $query = Product::approved()
            ->with([
                'seller.sellerProfile',
                'category',
                'wholesaleTiers' => fn($q) => $q->where('is_active', true)->orderBy('min_qty'),
            ])
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
            $query->where(function ($q) {
                $q->whereHas('activeVariants', fn($vq) => $vq->where('quantity', '>', 0))
                  ->orWhereDoesntHave('variants');
            });
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
     * Admin: List all products with filters (for admin panel)
     */
    public function adminIndex(Request $request)
    {
        if (!Auth::user()->hasRole('admin')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $perPage = $request->input('per_page', 15);
        $query = Product::with(['category', 'seller'])
                        ->withSum('activeVariants', 'quantity');

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        if ($request->has('seller_id')) {
            $query->where('seller_id', $request->seller_id);
        }
        if ($request->has('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name_en', 'like', '%' . $request->search . '%')
                    ->orWhere('name_mm', 'like', '%' . $request->search . '%');
            });
        }

        $products = $query->latest()->paginate($perPage);

        $formatted = $products->getCollection()->map(fn ($product) => $this->formatAdminProduct($product));

        return response()->json([
            'success' => true,
            'data' => $formatted,
            'meta' => [
                'current_page' => $products->currentPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ]
        ]);
    }

    /**
     * Approve a product (admin only)
     */
    public function approve(int $id)
    {
        // Resolve by primary key, not slug — bypass getRouteKeyName()
        $product = Product::withoutGlobalScopes()->findOrFail($id);

        if (!Auth::user()?->hasRole('admin')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        if (!in_array($product->status, ['pending', 'rejected'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only pending or rejected products can be approved',
            ], 422);
        }

        $product->update([
            'status' => 'approved',
            'approved_at' => now(),
            'listed_at' => now(),
            'rejection_reason' => null,
        ]);

        $product->load(['category', 'seller'])->loadSum('activeVariants', 'quantity');

        return response()->json([
            'success' => true,
            'message' => 'Product approved',
            'data' => $this->formatAdminProduct($product),
        ]);
    }


    /**
     * Reject a product (admin only)
     */
    public function reject(Request $request, $id)
    {
        $product = Product::withoutGlobalScopes()->findOrFail($id);

        if (!Auth::user()?->hasRole('admin')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $request->validate(['reason' => 'nullable|string|max:500']);

        if ($product->status === 'rejected') {
            return response()->json([
                'success' => false,
                'message' => 'Product is already rejected',
            ], 422);
        }

        $product->update([
            'status' => 'rejected',
            'approved_at' => null,
            'listed_at' => null,
            'rejection_reason' => $request->reason ?: null,
        ]);

        $product->load(['category', 'seller'])->loadSum('activeVariants', 'quantity');

        return response()->json([
            'success' => true,
            'message' => 'Product rejected',
            'data' => $this->formatAdminProduct($product),
        ]);
    }

    /**
     * Display the specified product with reviews
     */
    public function showPublic(string $slugOrId): JsonResponse
    {
        $product = Product::approved()
            ->with([
                'seller.sellerProfile',
                'category',
                'options.values',
                'activeVariants.optionValues.option',
                // Wholesale tiers — product-level (variant_id IS NULL) and active only.
                // Returned via ProductResource::wholesale_tiers using whenLoaded().
                'wholesaleTiers' => fn($q) => $q->where('is_active', true)->orderBy('min_qty'),
                // Include approved reviews for product detail UI
                'reviews' => fn ($q) => $q->where('status', 'approved')
                    ->with('buyer')
                    ->latest()
                    ->take(20),
            ])
            // Compute rating + review count from product_reviews (source of truth)
            // and override any stored/stale columns on the model.
            ->withCount([
                'reviews as review_count' => fn ($q) => $q->where('status', 'approved')
            ])
            ->withAvg([
                'reviews as average_rating' => fn ($q) => $q->where('status', 'approved')
            ], 'rating')
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
     * Store a newly created product (seller only)
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
     * Transform product images to full URLs.
     *
     * @param Product $product
     * @return Product
     */
    protected function transformProductImages($product)
    {
        if (isset($product->images)) {
            $images = is_string($product->images)
                ? json_decode($product->images, true)
                : $product->images;

            if (is_array($images)) {
                $product->images = $this->formatImages($images);
            }
        }
        return $product;
    }

    /**
     * Upload image for a new product (temporary storage)
     */
    public function uploadImage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'angle' => 'sometimes|string|in:front,back,side,top,default'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = Auth::user();
            $angle = $request->angle ?? 'default';

            $path = $request->file('image')->store(
                'products/temp/' . $user->id,
                'public'
            );

            // Return the relative path (no full URL) — frontend uses getImageUrl() for display
            $imageData = [
                'url' => $path,
                'angle' => $angle,
                'is_primary' => false,
                'uploaded_at' => now()->toISOString()
            ];

            return response()->json([
                'success' => true,
                'data' => $imageData,
                'message' => 'Image uploaded successfully'
            ]);
        } catch (\Exception $e) {
            \Log::error('Image upload error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload image: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified product
     */
    public function show(Product $product)
    {
        $product->load(['category', 'seller'])
            ->loadCount([
                'reviews as reviews_count' => function ($query) {
                    $query->where('status', 'approved');
                }
            ])
            ->loadAvg([
                'reviews as average_rating' => function ($query) {
                    $query->where('status', 'approved');
                }
            ], 'rating');

        return response()->json([
            'success' => true,
            'data' => new ProductResource($product)
        ]);
    }

    /**
     * Get product data for editing.
     */
    public function getProductForEdit($id)
    {
        $query = Product::query();

        if (!Auth::user()?->hasRole('admin')) {
            $query->where('seller_id', Auth::id());
        }

        $product = $query->findOrFail($id);

        $data = $product->toArray();

        $rawImages = is_string($product->images)
            ? json_decode($product->images, true) ?? []
            : ($product->images ?? []);

        $data['images'] = array_map(function ($img) {
            $stored = $img['url'] ?? '';

            // Normalise to a clean relative path regardless of whether the DB
            // holds a relative path (products/…) or a legacy absolute URL.
            if ($stored && str_starts_with($stored, 'http')) {
                $relativePath = preg_replace('#^https?://[^/]+/storage/#', '', $stored);
            } else {
                $relativePath = ltrim($stored, '/');
            }

            // Use Storage::url() so the generated URL respects the configured
            // disk driver (local, S3, etc.) rather than hardcoding app origin.
            $absoluteUrl = $relativePath
                ? \Illuminate\Support\Facades\Storage::disk('public')->url($relativePath)
                : '';

            return [
                'url'        => $absoluteUrl,   // absolute URL for <img> preview
                'path'       => $relativePath,  // relative path sent back on update
                'angle'      => $img['angle']      ?? 'default',
                'is_primary' => $img['is_primary'] ?? false,
            ];
        }, $rawImages);

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Update the specified product.
     */
    public function update(UpdateProductRequest $request, string $slugOrId): JsonResponse
    {
        $product = Product::where(
            fn($q) => $q->where('slug_en', $slugOrId)->orWhere('id', $slugOrId)
        )->firstOrFail();
 
        if (! $request->user()?->hasRole('admin')
            && (int) $product->seller_id !== (int) $request->user()->id
        ) {
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
     * Remove the specified product
     */
    public function destroy(Product $product)
    {
        // Seller-only ownership check (no admin bypass on seller endpoints)
        if ((int) Auth::id() !== (int) $product->seller_id) {
            return response()->json([
                'success' => false,
                'message' => __('messages.products.unauthorized_delete')
            ], 403);
        }

        try {
            $product->delete();

            return response()->json([
                'success' => true,
                'message' => __('messages.products.deleted')
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete product: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get categories for product form
     */
    public function getProductCategories()
    {
        try {
            $categories = Category::with('children')
                ->whereNull('parent_id')
                ->get()
                ->toArray();
            return response()->json([
                'success' => true,
                'data' => $categories
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch categories'
            ], 500);
        }
    }

    /**
     * Get products for the authenticated seller
     */
    public function myProducts(Request $request)
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            if (!$user->hasRole('seller')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only sellers can access this endpoint'
                ], 403);
            }

            $perPage = $request->input('per_page', 15);

            $products = Product::where('seller_id', $user->id)
                ->with(['category', 'seller.sellerProfile'])
                ->withCount(['reviews as reviews_count'])
                ->withAvg(['reviews as average_rating'], 'rating')
                ->withSum('activeVariants', 'quantity')
                ->latest()
                ->paginate($perPage);

            // Use ProductListResource for consistent field shape (includes
            // `in_stock` and `total_stock` from the model helpers). Append
            // seller-only fields that the management UI needs.
            $formatted = $products->getCollection()->map(function ($product) {
                $base = (new ProductListResource($product))->toArray(request());

                return array_merge($base, [
                    // Identification & status — seller-facing fields
                    'sku'              => $product->sku,
                    'brand'            => $product->brand,
                    'model'            => $product->model,
                    'condition'        => $product->condition,
                    'status'           => $product->status,
                    'rejection_reason' => $product->rejection_reason,
                    'is_new'           => (bool) $product->is_new,
                    'description_en'   => $product->description_en,
                    'description_mm'   => $product->description_mm,
                    'specifications'   => $product->specifications,
                    // Discount detail
                    'discount_type'        => $product->discount_type,
                    'discount_percentage'  => $product->discount_percentage ? (float) $product->discount_percentage : null,
                    'discount_start'       => $product->discount_start,
                    'discount_end'         => $product->discount_end,
                    'compare_at_price'     => $product->compare_at_price ? (float) $product->compare_at_price : null,
                    // Stock — sum of all active variant quantities (null for non-physical)
                    'total_stock'      => $product->product_type === 'physical'
                        ? (int) ($product->active_variants_sum_quantity ?? 0)
                        : null,
                    // Timestamps
                    'created_at'       => $product->created_at?->toISOString(),
                    'updated_at'       => $product->updated_at?->toISOString(),
                    'listed_at'        => $product->listed_at?->toISOString(),
                ]);
            });

            return response()->json([
                'success' => true,
                'data' => $formatted,
                'meta' => [
                    'current_page' => $products->currentPage(),
                    'per_page'     => $products->perPage(),
                    'total'        => $products->total(),
                    'last_page'    => $products->lastPage(),
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('My Products Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch products: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search products
     */
    public function searchPublic(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'query' => 'required|string|min:2',
            'per_page' => 'sometimes|integer|min:1|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $perPage = $request->input('per_page', 15);

        $query = Product::with(['category', 'seller'])
            ->withCount([
                'reviews as reviews_count' => function ($query) {
                    $query->where('status', 'approved');
                }
            ])
            ->withAvg([
                'reviews as average_rating' => function ($query) {
                    $query->where('status', 'approved');
                }
            ], 'rating')
            // FIX: public search must never return inactive or unapproved products
            ->where('is_active', true)
            ->where('status', 'approved');

        if ($request->has('query')) {
            $searchTerm = $request->input('query');
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name_en', 'like', '%' . $searchTerm . '%')
                    ->orWhere('name_mm', 'like', '%' . $searchTerm . '%')
                    ->orWhere('description_en', 'like', '%' . $searchTerm . '%')
                    ->orWhere('description_mm', 'like', '%' . $searchTerm . '%')
                    ->orWhere('brand', 'like', '%' . $searchTerm . '%');
            });
        }

        $products = $query->latest()->paginate($perPage);

        $formattedProducts = $products->getCollection()->map(function ($product) {
            return [
                'id' => $product->id,
                'name_en' => $product->name_en,
                'name_mm' => $product->name_mm,
                'description_en' => $product->description_en,
                'description_mm' => $product->description_mm,
                'price' => (float) $product->price,
                'quantity' => $product->quantity,
                'category_id' => $product->category_id,
                'seller_id' => $product->seller_id,
                'average_rating' => (float) $product->average_rating,
                'review_count' => $product->reviews_count,
                'specifications' => $product->specifications,
                'images' => $this->formatImages($product->images),
                'is_active' => $product->is_active,
                'created_at' => $product->created_at,
                'updated_at' => $product->updated_at,
                'category' => $product->category,
                'seller' => $product->seller,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $formattedProducts,
            'meta' => [
                'current_page' => $products->currentPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
                'last_page' => $products->lastPage(),
            ]
        ]);
    }

    /**
     * Get products by category
     */
    public function categoryProducts(Request $request, int $categoryId): JsonResponse
    {
        $request->merge(['category_id' => $categoryId]);
        return $this->indexPublic($request);
    }

    /**
     * Get products by seller
     */
    public function sellerProducts($sellerId)
    {
        $products = Product::where('seller_id', $sellerId)
            ->with(['category', 'seller'])
            ->withCount(['reviews as reviews_count'])
            ->withAvg(['reviews as average_rating'], 'rating')
            ->latest()
            ->paginate(10);

        // ✅ Transform images for all products
        if ($products->count() > 0) {
            $products->getCollection()->transform(function ($product) {
                return $this->transformProductImages($product);
            });
        }

        return response()->json([
            'success' => true,
            'data' => ProductResource::collection($products),
            'meta' => [
                'current_page' => $products->currentPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ]
        ]);
    }



    /**
     * Get average rating for a product
     */
    public function averageRating(Product $product)
    {
        $averageRating = $product->reviews()
            ->where('status', 'approved')
            ->avg('rating');

        return response()->json([
            'success' => true,
            'data' => [
                'average_rating' => number_format($averageRating, 2)
            ]
        ]);
    }

    /**
     * Get review count for a product
     */
    public function reviewCount(Product $product)
    {
        $reviewCount = $product->reviews()
            ->where('status', 'approved')
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'review_count' => $reviewCount
            ]
        ]);
    }

    /**
     * Get latest reviews for a product
     */
    public function latestReviews(Product $product)
    {
        $latestReviews = $product->reviews()
            ->where('status', 'approved')
            ->with('buyer')
            ->latest()
            ->take(5)
            ->get();

        return response()->json([
            'success' => true,
            'data' => ReviewResource::collection($latestReviews)
        ]);
    }

    /**
     * Get top reviews for a product
     */
    public function topReviews(Product $product)
    {
        $topReviews = $product->reviews()
            ->where('status', 'approved')
            ->with('buyer')
            ->orderBy('rating', 'desc')
            ->take(5)
            ->get();

        return response()->json([
            'success' => true,
            'data' => ReviewResource::collection($topReviews)
        ]);
    }

    /**
     * Get recent reviews for a product
     */
    public function recentReviews(Product $product)
    {
        $recentReviews = $product->reviews()
            ->where('status', 'approved')
            ->with('buyer')
            ->latest()
            ->take(5)
            ->get();

        return response()->json([
            'success' => true,
            'data' => ReviewResource::collection($recentReviews)
        ]);
    }

    /**
     * Get most helpful reviews for a product
     */
    public function mostHelpfulReviews(Product $product)
    {
        $mostHelpfulReviews = $product->reviews()
            ->where('status', 'approved')
            ->with('buyer')
            ->orderBy('helpful_count', 'desc')
            ->take(5)
            ->get();

        return response()->json([
            'success' => true,
            'data' => ReviewResource::collection($mostHelpfulReviews)
        ]);
    }

    public function toggleStatus($id)
    {
        // FIX: same slug-binding issue as approve() and reject().
        // Product::getRouteKeyName() returns 'slug_en', so implicit binding would
        // look up by slug. Admin sends numeric IDs — resolve by primary key directly.
        $product = Product::withoutGlobalScopes()->findOrFail($id);

        // Seller-only ownership check (no admin bypass on seller endpoints)
        if ((int) Auth::id() !== (int) $product->seller_id) {
            return response()->json([
                'success' => false,
                'message' => __('messages.products.unauthorized_update'),
            ], 403);
        }

        $product->update(['is_active' => !$product->is_active]);

        return response()->json([
            'success' => true,
            'message' => 'Product status updated',
            'is_active' => $product->is_active,
        ]);
    }

    protected function formatAdminProduct(Product $product): array
    {
        return [
            'id' => $product->id,
            'name_en' => $product->name_en,
            'name_mm' => $product->name_mm,
            'sku' => $product->sku,
            'moq' => $product->moq,
            'min_order' => $product->moq,
            'min_order_unit' => $product->min_order_unit,
            'price' => (float) $product->price,
            'total_stock' => $product->product_type === 'physical'
                ? (int) ($product->active_variants_sum_quantity ?? 0)
                : null,
            'product_type' => $product->product_type,
            'status' => $product->status,
            'discount_price' => $product->discount_price ? (float) $product->discount_price : null,
            'discount_percentage' => $product->discount_percentage ? (float) $product->discount_percentage : null,
            'is_new' => (bool) $product->is_new,
            'is_on_sale' => (bool) $product->is_on_sale,
            'discount_start' => $product->discount_start,
            'discount_end' => $product->discount_end,
            'is_active' => (bool) $product->is_active,
            'approved_at' => $product->approved_at,
            'rejection_reason' => $product->rejection_reason,
            'category' => $product->category ? [
                'id' => $product->category->id,
                'name_en' => $product->category->name_en,
            ] : null,
            'seller' => $product->seller ? [
                'id' => $product->seller->id,
                'name' => $product->seller->name,
            ] : null,
            'description_en' => $product->description_en,
            'description_mm' => $product->description_mm,
            'images'     => $this->formatImages($product->images),
            'created_at' => $product->created_at?->toISOString(),
            'updated_at' => $product->updated_at?->toISOString(),
        ];
    }

    /**
     * Show a single product for admin review/preview (bypasses approved() scope)
     */
    public function adminShow(int $id): JsonResponse
    {
        if (!Auth::user()?->hasRole('admin')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $product = Product::withoutGlobalScopes()
            ->with(['category', 'seller', 'options.values', 'activeVariants.optionValues.option'])
            ->withSum('activeVariants', 'quantity')
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $this->formatAdminProduct($product),
        ]);
    }

    protected function formatImages($images)
    {
        if (empty($images)) {
            return [];
        }

        if (is_string($images)) {
            try {
                $images = json_decode($images, true);
            } catch (\Exception $e) {
                // If it's not valid JSON, treat it as a single image URL
                return [
                    [
                        'url' => Storage::disk('public')->exists($images) ?
                            Storage::disk('public')->url($images) : $images,
                        'angle' => 'default',
                        'is_primary' => true
                    ]
                ];
            }
        }

        $formattedImages = [];
        foreach ($images as $index => $image) {
            if (is_string($image)) {
                $formattedImages[] = [
                    'url' => Storage::disk('public')->exists($image) ?
                        Storage::disk('public')->url($image) : $image,
                    'angle' => 'default',
                    'is_primary' => $index === 0
                ];
            } else {
                $url = $image['url'] ?? $image['path'] ?? '';
                $formattedImages[] = [
                    'url' => Storage::disk('public')->exists($url) ?
                        Storage::disk('public')->url($url) : $url,
                    'angle' => $image['angle'] ?? 'default',
                    'is_primary' => $image['is_primary'] ?? ($index === 0)
                ];
            }
        }

        return $formattedImages;
    }

    /**
     * Upload image to an existing product
     */
    public function uploadImageToProduct(Request $request, Product $product)
    {
        // Authorization check
        if ((int) Auth::id() !== (int) $product->seller_id) {
            return response()->json([
                'success' => false,
                'message' => __('messages.products.unauthorized_update')
            ], 403);
        }

        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'angle' => 'sometimes|string|in:front,back,side,top,default'
        ]);

        try {
            $angle = $request->angle ?? 'default';

            // Store image in product-specific directory
            $path = $request->file('image')->store(
                'products/' . $product->id,
                'public'
            );

            // Get current images
            $images = $product->images ?? [];
            if (!is_array($images)) {
                $images = json_decode($images, true) ?? [];
            }

            // Add new image (not primary by default)
            $newImage = [
                'url' => $path,
                'angle' => $angle,
                'is_primary' => empty($images), // Set as primary if no images exist
                'uploaded_at' => now()->toISOString()
            ];

            $images[] = $newImage;

            // Update product with new images array
            $product->update(['images' => $images]);

            return response()->json([
                'success' => true,
                'data' => $newImage,
                'message' => 'Image uploaded successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload image: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete an image from a product
     */
    public function deleteImage(Product $product, $imageIndex)
    {
        // Authorization check
        if ((int) Auth::id() !== (int) $product->seller_id) {
            return response()->json([
                'success' => false,
                'message' => __('messages.products.unauthorized_update')
            ], 403);
        }

        try {
            $images = $product->images ?? [];
            if (!is_array($images)) {
                $images = json_decode($images, true) ?? [];
            }

            // Check if index exists
            if (!isset($images[$imageIndex])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Image not found'
                ], 404);
            }

            $imageToDelete = $images[$imageIndex];

            // Delete the physical file
            if (Storage::disk('public')->exists($imageToDelete['url'])) {
                Storage::disk('public')->delete($imageToDelete['url']);
            }

            // Remove from array
            array_splice($images, $imageIndex, 1);

            // If we deleted the primary image and there are other images, set a new primary
            if ($imageToDelete['is_primary'] && count($images) > 0) {
                $images[0]['is_primary'] = true;
            }

            // Update product
            $product->update(['images' => $images]);

            return response()->json([
                'success' => true,
                'message' => 'Image deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete image: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Set an image as primary
     */
    public function setPrimaryImage(Product $product, $imageIndex)
    {
        // Authorization check
        if ((int) Auth::id() !== (int) $product->seller_id) {
            return response()->json([
                'success' => false,
                'message' => __('messages.products.unauthorized_update')
            ], 403);
        }

        try {
            $images = $product->images ?? [];
            if (!is_array($images)) {
                $images = json_decode($images, true) ?? [];
            }

            // Check if index exists
            if (!isset($images[$imageIndex])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Image not found'
                ], 404);
            }

            // Update all images - set the specified one as primary, others as not primary
            foreach ($images as $index => &$image) {
                $image['is_primary'] = ($index == $imageIndex);
            }

            // Update product
            $product->update(['images' => $images]);

            return response()->json([
                'success' => true,
                'message' => 'Primary image updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to set primary image: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Apply or update product discount
     */
    public function applyProductDiscount(Request $request, $id)
    {
        // Find the product by ID
        $product = Product::find($id);
        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => __('messages.products.not_found')
            ], 404);
        }

        // FIX: was checking hasRole('seller') as the fallback, which allowed any
        // seller to apply discounts to other sellers' products. Should check
        // ownership first, then allow admins as the override.
        if ((int) Auth::id() !== (int) $product->seller_id && !Auth::user()->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to apply discount to this product'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'discount_type' => 'required|in:percentage,fixed,none',
            'discount_value' => 'required_if:discount_type,percentage,fixed|numeric|min:0',
            'discount_start' => 'nullable|date',
            'discount_end' => 'nullable|date|after_or_equal:discount_start',
            'compare_at_price' => 'nullable|numeric|min:0',
            'sale_badge' => 'nullable|string|max:50',
            'sale_quantity' => 'nullable|integer|min:1',
            'is_on_sale' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $updateData = [
                'is_on_sale' => $request->get('is_on_sale', true),
                'discount_start' => $request->discount_start,
                'discount_end' => $request->discount_end,
                'compare_at_price' => $request->compare_at_price,
                'sale_badge' => $request->sale_badge,
                'sale_quantity' => $request->sale_quantity,
                'sale_sold' => 0 // Reset sold count when updating sale
            ];

            if ($request->discount_type === 'percentage') {
                $updateData['discount_percentage'] = $request->discount_value;
                $updateData['discount_price'] = null;
            } elseif ($request->discount_type === 'fixed') {
                $updateData['discount_price'] = $request->discount_value;
                $updateData['discount_percentage'] = null;
            } else {
                $updateData['discount_price'] = null;
                $updateData['discount_percentage'] = null;
                $updateData['is_on_sale'] = false;
            }

            $product->update($updateData);

            return response()->json([
                'success' => true,
                'data' => new ProductResource($product->fresh()),
                'message' => 'Product discount updated successfully'
            ]);
        } catch (\Exception $e) {
            \Log::error('Product discount error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to apply discount: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove discount from product
     */
    public function removeDiscount($id)
    {
        $product = Product::find($id);
        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => __('messages.products.not_found')
            ], 404);
        }
        // Authorization check - only seller or admin can update
        if ((int) Auth::id() !== (int) $product->seller_id && !Auth::user()->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to remove discount from this product'
            ], 403);
        }

        try {
            $product->update([
                'discount_price' => null,
                'discount_percentage' => null,
                'discount_start' => null,
                'discount_end' => null,
                'compare_at_price' => null,
                'sale_badge' => null,
                'sale_quantity' => null,
                'sale_sold' => 0,
                'is_on_sale' => false
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Discount removed from product successfully'
            ]);
        } catch (\Exception $e) {
            \Log::error('Remove discount error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove discount: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get active discounts for a product
     */
    public function productDiscounts($id)
    {
        $product = Product::find($id);
        $discounts = Discount::where('is_active', true)
            ->where('starts_at', '<=', now())
            ->where('expires_at', '>=', now())
            ->where(function ($query) use ($product) {
                $query->where('applicable_to', 'all_products')
                    ->orWhereJsonContains('applicable_product_ids', $product->id)
                    ->orWhereJsonContains('applicable_category_ids', $product->category_id)
                    ->orWhereJsonContains('applicable_seller_ids', $product->seller_id);
            })
            ->get();

        return response()->json([
            'success' => true,
            'data' => $discounts
        ]);
    }

    /**
     * Generate a unique slug for the given text
     */
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
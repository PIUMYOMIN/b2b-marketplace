<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use App\Models\ProductReview;
use App\Models\Discount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\ProductResource;
use App\Http\Resources\ReviewResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    /**
     * Display a listing of products with optional filters
     */
    public function indexPublic(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'per_page' => 'sometimes|integer|min:1|max:100',
            'category' => 'sometimes|exists:categories,id',
            'category_id' => 'sometimes|exists:categories,id',
            'seller_id' => 'sometimes|exists:users,id',
            'min_price' => 'sometimes|numeric|min:0',
            'max_price' => 'sometimes|numeric|min:0',
            'min_rating' => 'sometimes|numeric|min:0|max:5',
            'search' => 'sometimes|string|max:255',
            'sort' => 'sometimes|in:newest,price_asc,price_desc,rating,popular',
            'sort_by' => 'sometimes|in:created_at,price,average_rating,reviews_count,name_en,sales',
            'sort_order' => 'sometimes|in:asc,desc',
            'is_featured' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $perPage = $request->input('per_page', 15);

        // Base query — only approved, active products visible to the public
        $query = Product::with(['category', 'seller.sellerProfile'])
            ->where('is_active', true)
            ->where('status', 'approved')
            ->withCount([
                'reviews as reviews_count' => function ($q) {
                    $q->where('status', 'approved');
                },
            ])
            ->withAvg([
                'reviews as average_rating' => function ($q) {
                    $q->where('status', 'approved');
                },
            ], 'rating');

        // Featured filter
        if ($request->boolean('featured')) {
            $query->where('is_featured', true);
        }

        // Category filter with descendant support
        if ($request->has('category') || $request->has('category_id')) {
            $categoryId = $request->has('category') ? $request->category : $request->category_id;
            try {
                $category = Category::find($categoryId);
                if ($category) {
                    $allIds = array_merge([$categoryId], $category->descendants()->pluck('id')->toArray());
                    $query->whereIn('category_id', $allIds);
                } else {
                    $query->where('category_id', $categoryId);
                }
            } catch (\Exception $e) {
                $query->where('category_id', $categoryId);
            }
        }

        if ($request->has('seller_id')) {
            $query->where('seller_id', $request->seller_id);
        }
        if ($request->filled('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }
        if ($request->filled('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }
        if ($request->has('min_rating')) {
            $query->having('average_rating', '>=', $request->min_rating);
        }

        if ($request->has('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name_en', 'like', '%' . $request->search . '%')
                    ->orWhere('name_mm', 'like', '%' . $request->search . '%')
                    ->orWhere('description_en', 'like', '%' . $request->search . '%')
                    ->orWhere('description_mm', 'like', '%' . $request->search . '%');
            });
        }

        // Sorting — allowlisted to prevent column injection
        $allowedFields = ['created_at', 'price', 'average_rating', 'reviews_count', 'name_en', 'sales'];
        $sortBy = in_array($request->input('sort_by', 'created_at'), $allowedFields)
            ? $request->input('sort_by', 'created_at') : 'created_at';
        $sortOrder = in_array(strtolower($request->input('sort_order', 'desc')), ['asc', 'desc'])
            ? strtolower($request->input('sort_order', 'desc')) : 'desc';

        // Legacy ?sort= shorthand overrides sort_by/sort_order
        if ($request->has('sort')) {
            switch ($request->input('sort')) {
                case 'price_asc':
                    $sortBy = 'price';
                    $sortOrder = 'asc';
                    break;
                case 'price_desc':
                    $sortBy = 'price';
                    $sortOrder = 'desc';
                    break;
                case 'rating':
                    $sortBy = 'average_rating';
                    $sortOrder = 'desc';
                    break;
                case 'popular':
                    $sortBy = 'reviews_count';
                    $sortOrder = 'desc';
                    break;
                default:
                    $sortBy = 'created_at';
                    $sortOrder = 'desc';
            }
        }

        if (in_array($sortBy, ['average_rating', 'reviews_count'])) {
            $query->orderByRaw($sortBy . ' ' . $sortOrder);
        } else {
            $query->orderBy($sortBy, $sortOrder);
        }

        $products = $query->paginate($perPage);

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
                'last_page' => $products->lastPage(),
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
        $query = Product::with(['category', 'seller']);

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

        $formatted = $products->getCollection()->map(function ($product) {
            return [
                'id' => $product->id,
                'name_en' => $product->name_en,
                'name_mm' => $product->name_mm,
                'sku' => $product->sku,
                'moq' => $product->moq,
                'min_order' => $product->moq,
                'min_order_unit' => $product->min_order_unit,
                'price' => (float) $product->price,
                'quantity' => $product->quantity,
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
                'images' => $this->formatImages($product->images),
            ];
        });

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
    public function approve($id)
    {
        // Resolve by primary key, not slug — bypass getRouteKeyName()
        $product = Product::withoutGlobalScopes()->findOrFail($id);

        if (!Auth::user()->hasRole('admin')) {
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

        return response()->json([
            'success' => true,
            'message' => 'Product approved',
            'data' => new ProductResource($product),
        ]);
    }


    /**
     * Reject a product (admin only)
     */
    public function reject(Request $request, $id)
    {
        $product = Product::withoutGlobalScopes()->findOrFail($id);

        if (!Auth::user()->hasRole('admin')) {
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

        return response()->json([
            'success' => true,
            'message' => 'Product rejected',
            'data' => ['rejection_reason' => $product->rejection_reason],
        ]);
    }

    /**
     * Display the specified product with reviews
     */
    public function showPublic($slugOrId)
    {
        $product = Product::where('slug_en', $slugOrId)->first();

        if (!$product) {
            $product = Product::find($slugOrId);
        }

        if (!$product) {
            return response()->json(['success' => false, 'message' => 'Product not found'], 404);
        }

        $product->load(['category', 'seller.sellerProfile'])
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

        // Format images
        $product->images = $this->formatImages($product->images);

        // Get reviews
        $reviews = $product->reviews()
            ->where('status', 'approved')
            ->with('buyer')
            ->latest()
            ->take(5)
            ->get();

        // Calculate rating distribution
        $ratingDistribution = ProductReview::where('product_id', $product->id)
            ->where('status', 'approved')
            ->selectRaw('rating, count(*) as count')
            ->groupBy('rating')
            ->orderBy('rating', 'desc')
            ->get()
            ->pluck('count', 'rating');

        return response()->json([
            'success' => true,
            'data' => [
                'product' => new ProductResource($product),
                'reviews' => ReviewResource::collection($reviews),
                'rating_summary' => [
                    'average' => (float) number_format($product->average_rating, 2),
                    'count' => $product->reviews_count,
                    'distribution' => $ratingDistribution
                ]
            ]
        ]);
    }

    /**
     * Store a newly created product (seller only)
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name_en' => 'required|string|max:255',
            'name_mm' => 'nullable|string|max:255',
            'description_en' => 'required|string',
            'description_mm' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'quantity' => 'required|integer|min:0',
            'category_id' => 'required|exists:categories,id',
            'specifications' => 'nullable|array',
            'images' => 'nullable|array',
            'images.*.url' => 'required|string',
            'images.*.angle' => 'sometimes|string|in:front,back,side,top,default',
            'images.*.is_primary' => 'sometimes|boolean',
            'moq' => 'required|integer|min:1',
            'min_order_unit' => 'required|string|in:piece,kg,gram,meter,set,pack,box,pallet',
            'lead_time' => 'nullable|string|max:255',
            'is_active' => 'sometimes|boolean',
            'brand' => 'nullable|string|max:255',
            'model' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:255',
            'material' => 'nullable|string|max:255',
            'origin' => 'nullable|string|max:255',
            'weight_kg' => 'nullable|numeric|min:0',
            'warranty' => 'nullable|string|max:255',
            'warranty_type' => 'nullable|string|in:manufacturer,seller,international,no_warranty',
            'warranty_period' => 'nullable|string|max:255',
            'return_policy' => 'nullable|string|max:255',
            'shipping_cost' => 'nullable|numeric|min:0',
            'shipping_time' => 'nullable|string|max:255',
            'packaging_details' => 'nullable|string',
            'additional_info' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $recentProduct = Product::where('seller_id', Auth::id())
                ->where('name_en', $request->name_en)
                ->where('created_at', '>=', now()->subSeconds(30))
                ->first();

            if ($recentProduct) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product was recently created. Please wait a moment.'
                ], 422);
            }

            $productData = $request->only([
                'price',
                'quantity',
                'category_id',
                'specifications',
                'moq',
                'min_order_unit',
                'lead_time',
                'is_active',
                'brand',
                'model',
                'color',
                'material',
                'origin',
                'weight_kg',
                'warranty',
                'warranty_type',
                'warranty_period',
                'return_policy',
                'shipping_cost',
                'shipping_time',
                'packaging_details',
                'additional_info'
            ]);

            // Add language-specific fields
            $productData['name_en'] = $request->name_en;
            $productData['name_mm'] = $request->name_mm;
            $productData['description_en'] = $request->description_en;
            $productData['description_mm'] = $request->description_mm;

            $productData['slug_en'] = $this->generateUniqueSlug(Str::slug($request->name_en));
            $productData['slug_mm'] = $request->name_mm
                ? $this->generateUniqueSlug(Str::slug($request->name_mm), 'slug_mm')
                : null;

            $productData['seller_id'] = Auth::id();
            $productData['is_active'] = $request->get('is_active', true);

            $productData['status'] = 'pending';

            // Process images: move from temp to permanent storage
            $permanentImages = [];
            if ($request->has('images') && is_array($request->images)) {
                foreach ($request->images as $image) {
                    $tempPath = $image['url'];

                    // FIX: temp images are now stored at products/temp/{uid}/ so the
                    // prefix check is reliable even when permanent images also live under
                    // products/{uid}/.
                    $tempPrefix = 'products/temp/' . Auth::id() . '/';
                    if (Str::startsWith($tempPath, $tempPrefix)) {
                        $filename = basename($tempPath);
                        $newPath = 'products/' . Auth::id() . '/' . uniqid() . '_' . $filename;

                        if (Storage::disk('public')->exists($tempPath)) {
                            Storage::disk('public')->move($tempPath, $newPath);
                            $permanentImages[] = [
                                'url' => $newPath,
                                'angle' => $image['angle'] ?? 'default',
                                'is_primary' => $image['is_primary'] ?? false
                            ];
                        }
                    } else {
                        // External URL or path we don't control — store as-is
                        $permanentImages[] = [
                            'url' => $tempPath,
                            'angle' => $image['angle'] ?? 'default',
                            'is_primary' => $image['is_primary'] ?? false
                        ];
                    }
                }
                $productData['images'] = $permanentImages;
            }

            $product = Product::create($productData);

            return response()->json([
                'success' => true,
                'data' => new ProductResource($product),
                'message' => 'Product created successfully'
            ], 201);

        } catch (\Exception $e) {
            \Log::error('Product creation error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create product: ' . $e->getMessage()
            ], 500);
        }
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
     * Get product data for editing (seller only).
     *
     */
    public function getProductForEdit($id)
    {
        $product = Product::where('seller_id', Auth::id())->findOrFail($id);

        $data = $product->toArray();

        // Build images with both the display URL (absolute) and the submission
        // path (relative) so the frontend round-trips the correct value.
        $rawImages = is_string($product->images)
            ? json_decode($product->images, true) ?? []
            : ($product->images ?? []);

        $data['images'] = array_map(function ($img) {
            $relativePath = $img['url'] ?? '';
            // Build absolute URL for the <img> src preview
            $absoluteUrl = $relativePath && !str_starts_with($relativePath, 'http')
                ? url('storage/' . ltrim($relativePath, '/'))
                : $relativePath;

            return [
                'url' => $absoluteUrl,    // display only
                'path' => $relativePath,   // send back on update
                'angle' => $img['angle'] ?? 'default',
                'is_primary' => $img['is_primary'] ?? false,
            ];
        }, $rawImages);

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Update the specified product (seller only)
     */
    public function update($id, Request $request)
    {
        $product = Product::where('seller_id', Auth::id())->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name_en' => 'sometimes|string|max:255',
            'name_mm' => 'nullable|string|max:255',
            'description_en' => 'sometimes|string',
            'description_mm' => 'nullable|string',
            'price' => 'sometimes|numeric|min:0',
            'quantity' => 'sometimes|integer|min:0',
            'category_id' => 'sometimes|exists:categories,id',
            'specifications' => 'nullable|array',
            'images' => 'nullable|array',
            'images.*.url' => 'required|string',
            'images.*.angle' => 'sometimes|string|in:front,back,side,top,default',
            'images.*.is_primary' => 'sometimes|boolean',
            'moq' => 'sometimes|integer|min:1',
            'min_order_unit' => 'sometimes|string|in:piece,kg,gram,meter,set,pack,box,pallet',
            'lead_time' => 'nullable|string|max:255',
            'is_active' => 'sometimes|boolean',
            'brand' => 'nullable|string|max:255',
            'model' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:255',
            'material' => 'nullable|string|max:255',
            'origin' => 'nullable|string|max:255',
            'weight_kg' => 'nullable|numeric|min:0',
            'warranty' => 'nullable|string|max:255',
            'warranty_type' => 'nullable|string|in:manufacturer,seller,international,no_warranty',
            'warranty_period' => 'nullable|string|max:255',
            'return_policy' => 'nullable|string|max:255',
            'shipping_cost' => 'nullable|numeric|min:0',
            'shipping_time' => 'nullable|string|max:255',
            'packaging_details' => 'nullable|string',
            'additional_info' => 'nullable|string',
            'is_featured' => 'sometimes|boolean',
            'is_new' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $updateData = $request->only([
                'price',
                'quantity',
                'category_id',
                'specifications',
                'moq',
                'min_order_unit',
                'lead_time',
                'is_active',
                'brand',
                'model',
                'color',
                'material',
                'origin',
                'weight_kg',
                'warranty',
                'warranty_type',
                'warranty_period',
                'return_policy',
                'shipping_cost',
                'shipping_time',
                'packaging_details',
                'additional_info',
                'is_featured',  // FIX: was missing — checkbox value never saved
                'is_new',       // FIX: was missing — checkbox value never saved
            ]);

            // Handle name/description updates and slugs
            if ($request->has('name_en') && $request->name_en !== null) {
                $updateData['name_en'] = $request->name_en;
                if ($product->name_en !== $request->name_en) {
                    $updateData['slug_en'] = $this->generateUniqueSlug(
                        Str::slug($request->name_en),
                        'slug_en',
                        $product->id
                    );
                }
            }

            if ($request->has('name_mm') && $request->name_mm !== null) {
                $updateData['name_mm'] = $request->name_mm;
                if ($product->name_mm !== $request->name_mm) {
                    $updateData['slug_mm'] = $this->generateUniqueSlug(
                        Str::slug($request->name_mm),
                        'slug_mm',
                        $product->id
                    );
                }
            }

            if ($request->has('description_en')) {
                $updateData['description_en'] = $request->description_en;
            }
            if ($request->has('description_mm')) {
                $updateData['description_mm'] = $request->description_mm;
            }

            // ── Image processing ──────────────────────────────────────────────
            if ($request->has('images')) {
                $oldImages = $product->images ?? [];
                $newImages = $request->images;
                $finalImages = [];

                $tempPrefix = 'products/temp/' . Auth::id() . '/';
                $permPrefix = 'products/' . Auth::id() . '/';

                foreach ($newImages as $image) {

                    $submittedPath = $image['url'];

                    if (Str::startsWith($submittedPath, $tempPrefix)) {
                        // ── New temp image: move to permanent storage ─────────
                        $filename = basename($submittedPath);
                        $newPath = $permPrefix . uniqid() . '_' . $filename;

                        if (Storage::disk('public')->exists($submittedPath)) {
                            Storage::disk('public')->move($submittedPath, $newPath);
                            $finalImages[] = [
                                'url' => $newPath,
                                'angle' => $image['angle'] ?? 'default',
                                'is_primary' => $image['is_primary'] ?? false,
                            ];
                        }
                    } elseif (Str::startsWith($submittedPath, $permPrefix)) {
                        // ── Existing permanent image: keep as-is ──────────────
                        // Validate it actually belongs to this product (security)
                        $existingPaths = collect($oldImages)->pluck('url');
                        if ($existingPaths->contains($submittedPath)) {
                            $finalImages[] = [
                                'url' => $submittedPath,
                                'angle' => $image['angle'] ?? 'default',
                                'is_primary' => $image['is_primary'] ?? false,
                            ];
                        }
                    } else {
                        // External URL (http/https) — store as-is
                        $finalImages[] = [
                            'url' => $submittedPath,
                            'angle' => $image['angle'] ?? 'default',
                            'is_primary' => $image['is_primary'] ?? false,
                        ];
                    }
                }

                // Delete storage files that were explicitly removed by the seller
                $oldPaths = collect($oldImages)->pluck('url')->toArray();
                $newPaths = collect($finalImages)->pluck('url')->toArray();
                foreach (array_diff($oldPaths, $newPaths) as $removed) {
                    // Only delete files we own (not external URLs)
                    if (
                        !str_starts_with($removed, 'http') &&
                        Storage::disk('public')->exists($removed)
                    ) {
                        Storage::disk('public')->delete($removed);
                    }
                }

                $updateData['images'] = $finalImages;
            }
            // If 'images' key absent: $updateData has no 'images' key →
            // $product->update($updateData) leaves images column unchanged.

            if ($product->status === 'approved') {
                $updateData['status'] = 'pending';
                $updateData['approved_at'] = null;
                $updateData['listed_at'] = null;
            }

            $product->update($updateData);

            return response()->json([
                'success' => true,
                'data' => new ProductResource($product->fresh()),
                'message' => 'Product updated successfully'
            ]);

        } catch (\Exception $e) {
            \Log::error('Product update error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update product: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified product
     */
    public function destroy(Product $product)
    {
        if ((int) Auth::id() !== (int) $product->seller_id && !Auth::user()->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to delete this product'
            ], 403);
        }

        try {
            $product->delete();

            return response()->json([
                'success' => true,
                'message' => 'Product deleted successfully'
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
                ->with(['category'])
                ->withCount(['reviews as reviews_count'])
                ->withAvg(['reviews as average_rating'], 'rating')
                ->latest()
                ->paginate($perPage);

            // Format the response
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
                    'discount_price' => $product->discount_price ? (float) $product->discount_price : null,
                    'discount_percentage' => $product->discount_percentage ? (float) $product->discount_percentage : null,
                    'is_on_sale' => (bool) $product->is_on_sale,
                    'discount_start' => $product->discount_start,
                    'discount_end' => $product->discount_end,
                    'is_active' => (bool) $product->is_active,
                    'created_at' => $product->created_at->toISOString(),
                    'updated_at' => $product->updated_at->toISOString(),
                    'category' => $product->category ? [
                        'id' => $product->category->id,
                        'name_en' => $product->category->name_en,
                        'slug_en' => $product->category->slug_en
                    ] : null
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
    public function categoryProducts($categoryId)
    {
        $products = Product::where('category_id', $categoryId)
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
     * Get reviews for a product
     */
    public function productReviews(Product $product)
    {
        $reviews = $product->reviews()
            ->where('status', 'approved')
            ->with('buyer')
            ->latest()
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => ReviewResource::collection($reviews),
            'meta' => [
                'current_page' => $reviews->currentPage(),
                'per_page' => $reviews->perPage(),
                'total' => $reviews->total(),
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

        // Authorization: seller can toggle their own products; admin can toggle any
        if ((int) Auth::id() !== (int) $product->seller_id && !Auth::user()->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to update this product',
            ], 403);
        }

        $product->update(['is_active' => !$product->is_active]);

        return response()->json([
            'success' => true,
            'message' => 'Product status updated',
            'is_active' => $product->is_active,
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
        if ((int) Auth::id() !== (int) $product->seller_id && !Auth::user()->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to update this product'
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
        if ((int) Auth::id() !== (int) $product->seller_id && !Auth::user()->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to update this product'
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
        if ((int) Auth::id() !== (int) $product->seller_id && !Auth::user()->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to update this product'
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
                'message' => 'Product not found'
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
                'message' => 'Product not found'
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
     * Generate a unique slug using a retry loop.
     *
     *
     * @param  string   $base       Already Str::slug()-processed base string
     * @param  string   $column     'slug_en' or 'slug_mm'
     * @param  int|null $excludeId  Exclude this product ID (for updates)
     */
    private function generateUniqueSlug(string $base, string $column = 'slug_en', ?int $excludeId = null): string
    {
        $slug = $base;
        $suffix = 1;

        while (true) {
            $query = Product::withTrashed()->where($column, $slug);
            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }
            if (!$query->exists()) {
                return $slug;
            }
            $slug = $base . '-' . (++$suffix);
        }
    }
}
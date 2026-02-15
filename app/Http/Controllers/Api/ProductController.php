<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use App\Models\Review;
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
            'category' => 'sometimes|exists:categories,id', // Changed from category_id
            'category_id' => 'sometimes|exists:categories,id', // Keep for backward compatibility
            'seller_id' => 'sometimes|exists:users,id',
            'min_price' => 'sometimes|numeric|min:0',
            'max_price' => 'sometimes|numeric|min:0',
            'min_rating' => 'sometimes|numeric|min:0|max:5',
            'search' => 'sometimes|string|max:255',
            'sort' => 'sometimes|in:newest,price_asc,price_desc,rating,popular',
            'status' => 'sometimes|in:active,inactive'
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
            ], 'rating');

        // Apply category filter with descendants
        if ($request->has('category') || $request->has('category_id')) {
            $categoryId = $request->has('category') ? $request->category : $request->category_id;

            try {
                // Get the category and its descendants
                $category = Category::find($categoryId);
                if ($category) {
                    // Get all descendant category IDs including the parent
                    $descendantIds = $category->descendants()->pluck('id')->toArray();
                    $allCategoryIds = array_merge([$categoryId], $descendantIds);

                    // Filter products by any of these category IDs
                    $query->whereIn('category_id', $allCategoryIds);
                } else {
                    // Fallback to direct category ID if category not found
                    $query->where('category_id', $categoryId);
                }
            } catch (\Exception $e) {
                // Fallback to direct filtering
                $query->where('category_id', $categoryId);
            }
        }

        // Apply other filters (keep existing code)
        if ($request->has('seller_id')) {
            $query->where('seller_id', $request->seller_id);
        }

        if ($request->has('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }

        if ($request->has('max_price')) {
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

        if ($request->has('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        // Apply sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');

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

        // Apply sorting based on field
        if ($sortBy === 'average_rating' || $sortBy === 'reviews_count') {
            // These are computed columns, need special handling
            if ($sortBy === 'average_rating') {
                $query->orderByRaw('average_rating ' . $sortOrder);
            } else {
                $query->orderByRaw('reviews_count ' . $sortOrder);
            }
        } else {
            $query->orderBy($sortBy, $sortOrder);
        }

        $products = $query->paginate($perPage);

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
                'average_rating' => (float) ($product->average_rating ?? 0),
                'review_count' => $product->reviews_count,
                'specifications' => $product->specifications,
                'images' => $this->formatImages($product->images),
                'is_active' => $product->is_active,
                'status' => $product->status,
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
                'price' => (float) $product->price,
                'quantity' => $product->quantity,
                'status' => $product->status,
                'is_active' => $product->is_active,
                'approved_at' => $product->approved_at,
                'created_at' => $product->created_at,
                'category' => $product->category ? ['id' => $product->category->id, 'name_en' => $product->category->name_en] : null,
                'seller' => $product->seller ? ['id' => $product->seller->id, 'name' => $product->seller->name] : null,
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
    public function approve(Product $product)
    {
        if (!Auth::user()->hasRole('admin')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        if ($product->status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'Product is not pending'], 422);
        }

        $product->update([
            'status' => 'approved',
            'approved_at' => now(),
            'listed_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Product approved',
            'data' => new ProductResource($product)
        ]);
    }


    /**
     * Reject a product (admin only)
     */
    public function reject(Request $request, Product $product)
    {
        if (!Auth::user()->hasRole('admin')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $request->validate(['reason' => 'nullable|string|max:500']);

        if ($product->status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'Product is not pending'], 422);
        }

        $product->update([
            'status' => 'rejected',
            'approved_at' => null,
            'listed_at' => null,
            // optionally store reason in a new column
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Product rejected',
        ]);
    }

    /**
     * Display the specified product with reviews
     */
    public function showPublic($id)
    {
        $product = Product::with(['category', 'seller'])
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
            ->findOrFail($id);

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
        $ratingDistribution = Review::where('product_id', $product->id)
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
     * Store a newly created product
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255', // English name
            'name_mm' => 'nullable|string|max:255', // Myanmar name
            'description' => 'required|string', // English description
            'description_mm' => 'nullable|string', // Myanmar description
            'price' => 'required|numeric|min:0',
            'quantity' => 'required|integer|min:0',
            'category_id' => 'required|exists:categories,id',
            'specifications' => 'nullable|array',
            'images' => 'nullable|array',
            'images.*.url' => 'required|string',
            'images.*.angle' => 'sometimes|string',
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
            // Check for duplicate submission within last 30 seconds
            $recentProduct = Product::where('seller_id', Auth::id())
                ->where('name_en', $request->name)
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
            $productData['name_en'] = $request->name;
            $productData['name_mm'] = $request->name_mm;
            $productData['description_en'] = $request->description;
            $productData['description_mm'] = $request->description_mm;

            // Generate slugs
            $productData['slug_en'] = Str::slug($request->name);
            $productData['slug_mm'] = $request->name_mm ? Str::slug($request->name_mm) : null;

            // Ensure unique slugs
            $count = Product::where('slug_en', 'LIKE', $productData['slug_en'] . '%')->count();
            if ($count > 0) {
                $productData['slug_en'] = $productData['slug_en'] . '-' . ($count + 1);
            }

            if ($productData['slug_mm']) {
                $countMm = Product::where('slug_mm', 'LIKE', $productData['slug_mm'] . '%')->count();
                if ($countMm > 0) {
                    $productData['slug_mm'] = $productData['slug_mm'] . '-' . ($countMm + 1);
                }
            }

            $productData['seller_id'] = Auth::id();
            $productData['is_active'] = $request->get('is_active', true);

            if ($request->has('images') && is_array($request->images)) {
                $permanentImages = [];

                foreach ($request->images as $image) {
                    $tempPath = $image['url'];

                    $filename = basename($tempPath);
                    $newPath = 'products/' . Auth::id() . '/' . uniqid() . '_' . $filename;

                    if (Storage::disk('public')->exists($tempPath)) {
                        Storage::disk('public')->move($tempPath, $newPath);

                        $permanentImages[] = [
                            'url' => $newPath,
                            'angle' => $image['angle'] ?? 'default',
                            'is_primary' => $image['is_primary'] ?? false
                        ];
                    } else {
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

            // Store image in user-specific temp directory
            $path = $request->file('image')->store(
                'products/' . $user->id,
                'public'
            );

            // Generate full URL for the image
            $fullUrl = Storage::disk('public')->url($path);

            $imageData = [
                'url' => $path, // Store the path for later use when moving to permanent location
                'full_url' => $fullUrl, // Provide full URL for immediate frontend use
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
    public function show($id)
    {
        // Fixed: Remove infinite recursion
        $product = Product::with(['category', 'seller'])
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
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new ProductResource($product)
        ]);
    }

    /**
     * Update the specified product
     */
    public function update(Request $request, Product $product)
    {
        // Authorization check - only seller or admin can update
        if (Auth::id() !== $product->seller_id && !Auth::user()->hasRole('seller')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to update this product'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'name_mm' => 'nullable|string|max:255',
            'description' => 'sometimes|string',
            'description_mm' => 'nullable|string',
            'price' => 'sometimes|numeric|min:0',
            'quantity' => 'sometimes|integer|min:0',
            'category_id' => 'sometimes|exists:categories,id',
            'specifications' => 'nullable|array',
            'images' => 'nullable|array',
            'moq' => 'sometimes|integer|min:1',
            'min_order_unit' => 'sometimes|string|in:piece,kg,gram,meter,set,pack,box,pallet',
            'lead_time' => 'nullable|string|max:255',
            'is_active' => 'sometimes|boolean',
            // New fields
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
                'additional_info'
            ]);

            // Handle language-specific fields
            if ($request->has('name')) {
                $updateData['name_en'] = $request->name;

                // Generate new slug if name changed
                if ($product->name_en !== $request->name) {
                    $newSlug = Str::slug($request->name);
                    $count = Product::where('slug_en', 'LIKE', $newSlug . '%')
                        ->where('id', '!=', $product->id)
                        ->count();
                    if ($count > 0) {
                        $newSlug = $newSlug . '-' . ($count + 1);
                    }
                    $updateData['slug_en'] = $newSlug;
                }
            }

            if ($request->has('name_mm') && $request->name_mm !== null) {
                $updateData['name_mm'] = $request->name_mm;

                // Generate new slug if Myanmar name changed
                if ($product->name_mm !== $request->name_mm) {
                    $newSlugMm = Str::slug($request->name_mm);
                    $count = Product::where('slug_mm', 'LIKE', $newSlugMm . '%')
                        ->where('id', '!=', $product->id)
                        ->count();
                    if ($count > 0) {
                        $newSlugMm = $newSlugMm . '-' . ($count + 1);
                    }
                    $updateData['slug_mm'] = $newSlugMm;
                }
            }

            if ($request->has('description')) {
                $updateData['description_en'] = $request->description;
            }

            if ($request->has('description_mm') && $request->description_mm !== null) {
                $updateData['description_mm'] = $request->description_mm;
            }

            // If images are provided in the update, handle them
            if ($request->has('images')) {
                $updateData['images'] = $request->images;
            }

            $product->update($updateData);

            return response()->json([
                'success' => true,
                'data' => new ProductResource($product->fresh()),
                'message' => 'Product updated successfully'
            ]);

        } catch (\Exception $e) {
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
        // Authorization check - only seller or admin can delete
        if (Auth::id() !== $product->seller_id && !Auth::user()->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to delete this product'
            ], 403);
        }

        try {
            // Delete associated images
            if (!empty($product->images)) {
                foreach ($product->images as $image) {
                    if (Storage::disk('public')->exists($image['url'])) {
                        Storage::disk('public')->delete($image['url']);
                    }
                }
            }

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
                    'is_active' => (bool) $product->is_active,
                    'created_at' => $product->created_at->toISOString(),
                    'updated_at' => $product->updated_at->toISOString(),
                    'category' => $product->category ? [
                        'id' => $product->category->id,
                        'name' => $product->category->name,
                        'slug' => $product->category->slug
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
            ->where('is_active', true);

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

    public function toggleStatus(Product $product)
    {
        // Authorization check
        if (Auth::id() !== $product->seller_id && !Auth::user()->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to update this product'
            ], 403);
        }

        $product->update(['is_active' => !$product->is_active]);

        return response()->json([
            'success' => true,
            'message' => 'Product status updated',
            'is_active' => $product->is_active
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
        if (Auth::id() !== $product->seller_id && !Auth::user()->hasRole('admin')) {
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
        if (Auth::id() !== $product->seller_id && !Auth::user()->hasRole('admin')) {
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
        if (Auth::id() !== $product->seller_id && !Auth::user()->hasRole('admin')) {
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
    public function applyProductDiscount(Request $request, Product $product)
    {
        // Authorization check - only seller or admin can update
        if (Auth::id() !== $product->seller_id && !Auth::user()->hasRole('admin')) {
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
    public function removeDiscount(Product $product)
    {
        // Authorization check - only seller or admin can update
        if (Auth::id() !== $product->seller_id && !Auth::user()->hasRole('admin')) {
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
    public function productDiscounts(Product $product)
    {
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
}
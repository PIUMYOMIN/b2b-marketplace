<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\ProductResource;
use App\Http\Resources\ReviewResource;
use App\Http\Resources\CategoryResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;


class ProductController extends Controller
{
    /**
     * Display a listing of products with optional filters
     */
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'per_page' => 'sometimes|integer|min:1|max:100',
            'category_id' => 'sometimes|exists:categories,id',
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
            ->withCount(['reviews as reviews_count' => function($query) {
                $query->where('status', 'approved');
            }])
            ->withAvg(['reviews as average_rating' => function($query) {
                $query->where('status', 'approved');
            }], 'rating');

        // Apply filters
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

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
            $query->where(function($q) use ($request) {
                $q->where('name', 'like', '%'.$request->search.'%')
                  ->orWhere('description', 'like', '%'.$request->search.'%')
                  ->orWhere('name_mm', 'like', '%'.$request->search.'%');
            });
        }

        if ($request->has('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        // Apply sorting
        switch ($request->input('sort', 'newest')) {
            case 'price_asc':
                $query->orderBy('price', 'asc');
                break;
            case 'price_desc':
                $query->orderBy('price', 'desc');
                break;
            case 'rating':
                $query->orderBy('average_rating', 'desc');
                break;
            case 'popular':
                $query->orderBy('reviews_count', 'desc');
                break;
            default: // newest
                $query->latest();
        }

        $products = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => ProductResource::collection($products),
            'meta' => [
                'current_page' => $products->currentPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
                'last_page' => $products->lastPage(),
            ]
        ]);
    }

    /**
     * Display a listing of public products
     */
    public function indexPublic(Request $request)
    {
        $query = Product::query()
            ->with(['category', 'seller'])
            ->withCount(['reviews as reviews_count' => function($query) {
                $query->where('status', 'approved');
            }])
            ->withAvg(['reviews as average_rating' => function($query) {
                $query->where('status', 'approved');
            }], 'rating')
            ->where('is_public', true);

        // Apply filters and sorting as in the index method...

        $products = $query->paginate($request->input('per_page', 15));

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
     * Display the specified product with reviews
     */
    public function show($id)
    {
        $product = Product::with(['category', 'seller'])
            ->withCount(['reviews as reviews_count' => function($query) {
                $query->where('status', 'approved');
            }])
            ->withAvg(['reviews as average_rating' => function($query) {
                $query->where('status', 'approved');
            }], 'rating')
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
        'name' => 'required|string|max:255',
        'name_mm' => 'nullable|string|max:255',
        'description' => 'required|string',
        'price' => 'required|numeric|min:0',
        'quantity' => 'required|integer|min:0',
        'category_id' => 'required|exists:categories,id',
        'specifications' => 'nullable|array',
        'images' => 'nullable|array',
        'images.*.url' => 'required|string',
        'images.*.angle' => 'sometimes|string',
        'images.*.is_primary' => 'sometimes|boolean',
        'min_order' => 'required|integer|min:1',
        'lead_time' => 'nullable|string|max:255',
        'is_active' => 'sometimes|boolean'
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'errors' => $validator->errors()
        ], 422);
    }

    try {
        $productData = $request->only([
            'name', 'name_mm', 'description', 'price', 'quantity',
            'category_id', 'specifications', 'min_order', 'lead_time', 'is_active'
        ]);
        
        $productData['seller_id'] = Auth::id();
        $productData['is_active'] = $request->get('is_active', true);
        
        // Handle images - move from temp to permanent location
        if ($request->has('images') && is_array($request->images)) {
            $permanentImages = [];
            
            foreach ($request->images as $image) {
                $tempPath = $image['url'];
                $newPath = 'products/' . Auth::id() . '/' . basename($tempPath);
                
                // Move from temp to permanent location
                if (Storage::disk('public')->exists($tempPath)) {
                    Storage::disk('public')->move($tempPath, $newPath);
                    
                    $permanentImages[] = [
                        'url' => $newPath,
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
        return response()->json([
            'success' => false,
            'message' => 'Failed to create product: ' . $e->getMessage()
        ], 500);
    }
}


    public function showPublic($id)
    {
        return $this->show($id);
    }

    /**
     * Update the specified product
     */
    public function update(Request $request, Product $product)
{
    // Authorization check - only seller or admin can update
    if (Auth::id() !== $product->seller_id && !Auth::user()->hasRole('admin')) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized to update this product'
        ], 403);
    }

    $validator = Validator::make($request->all(), [
        'name' => 'sometimes|string|max:255',
        'name_mm' => 'nullable|string|max:255',
        'description' => 'sometimes|string',
        'price' => 'sometimes|numeric|min:0',
        'quantity' => 'sometimes|integer|min:0',
        'category_id' => 'sometimes|exists:categories,id',
        'specifications' => 'nullable|array',
        'images' => 'nullable|array',
        'min_order' => 'sometimes|integer|min:1',
        'lead_time' => 'nullable|string|max:255',
        'is_active' => 'sometimes|boolean'
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'errors' => $validator->errors()
        ], 422);
    }

    try {
        $updateData = $request->only([
            'name', 'name_mm', 'description', 'price', 'quantity',
            'category_id', 'specifications', 'min_order', 'lead_time', 'is_active'
        ]);

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
     * Get products for the authenticated seller
     */
    public function myProducts(Request $request)
    {
        $perPage = $request->input('per_page', 10);
        
        $products = Product::where('seller_id', Auth::id())
            ->with(['category'])
            ->withCount(['reviews as reviews_count'])
            ->withAvg(['reviews as average_rating'], 'rating')
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => ProductResource::collection($products),
            'meta' => [
                'current_page' => $products->currentPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
                'last_page' => $products->lastPage(),
            ]
        ]);
    }


    /**
     * Search products
     */
    public function search(Request $request)
    {
        $request->validate([
            'query' => 'required|string|min:2'
        ]);

        $products = Product::search($request->query)
            ->query(function($builder) {
                $builder->with(['category', 'seller'])
                    ->withCount(['reviews as reviews_count'])
                    ->withAvg(['reviews as average_rating'], 'rating');
            })
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => ProductResource::collection($products)
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
    public function averageRating(Product $product){
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
    public function latestReviews(Product $product){
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
    public function topReviews(Product $product){
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
    public function recentReviews(Product $product){
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
    public function mostHelpfulReviews(Product $product){
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
            return [[
                'url' => Storage::disk('public')->exists($images) ? 
                        Storage::disk('public')->url($images) : $images,
                'angle' => 'default',
                'is_primary' => true
            ]];
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
 * Upload image for a new product (before product creation)
 */
public function uploadImage(Request $request)
{
    $request->validate([
        'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
        'angle' => 'sometimes|string|in:front,back,side,top,default'
    ]);

    try {
        $user = Auth::user();
        $angle = $request->angle ?? 'default';
        
        // Store image in user-specific directory
        $path = $request->file('image')->store(
            'products/temp/' . $user->id, 
            'public'
        );
        
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
        return response()->json([
            'success' => false,
            'message' => 'Failed to upload image: ' . $e->getMessage()
        ], 500);
    }
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
 * Updated store method to handle image URLs from frontend
 */


/**
 * Updated update method to handle image updates
 */


}
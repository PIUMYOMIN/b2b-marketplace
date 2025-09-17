<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
class ReviewController extends Controller
{
    /**
     * Get reviews for a specific product
     */
    public function productReviews($productId)
    {
        try {
            \Log::info('Fetching reviews for product:', ['product_id' => $productId]);
            
            // First, check if product exists
            $product = Product::find($productId);
            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found'
                ], 404);
            }

            $reviews = Review::where('product_id', $productId)
                ->with('user') // Eager load user relationship
                ->where('status', 'approved')
                ->orderBy('created_at', 'desc')
                ->get();

            \Log::info('Reviews found:', ['count' => $reviews->count()]);

            return response()->json([
                'success' => true,
                'data' => $reviews
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to fetch reviews:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch reviews',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get all reviews (admin only)
     */
    public function index(Request $request)
    {
        try {
            $query = Review::with(['user', 'product']);

            // Apply filters
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }
            if ($request->has('product_id')) {
                $query->where('product_id', $request->product_id);
            }
            if ($request->has('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            $reviews = $query->orderBy('created_at', 'desc')
                ->take($request->get('limit', 50))
                ->get();

            return response()->json([
                'success' => true,
                'data' => $reviews
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to fetch reviews:', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch reviews'
            ], 500);
        }
    }

    /**
     * Get authenticated user's reviews
     */
    public function myReviews()
    {
        try {
            $reviews = Review::where('user_id', auth()->id())
                ->with('product')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $reviews
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to fetch user reviews:', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch your reviews'
            ], 500);
        }
    }

    /**
     * Submit a new review
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Check if user already reviewed this product
            $existingReview = Review::where('user_id', auth()->id())
                ->where('product_id', $request->product_id)
                ->first();

            if ($existingReview) {
                return response()->json([
                    'success' => false,
                    'message' => 'You have already reviewed this product'
                ], 400);
            }

            $review = Review::create([
                'user_id' => auth()->id(),
                'product_id' => $request->product_id,
                'rating' => $request->rating,
                'comment' => $request->comment,
                'status' => 'pending' // Requires admin approval
            ]);

            // Update product rating statistics
            $this->updateProductRating($request->product_id);

            return response()->json([
                'success' => true,
                'message' => 'Review submitted successfully. It will be visible after approval.',
                'data' => $review->load('user')
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to submit review:', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to submit review'
            ], 500);
        }
    }

    /**
     * Approve a review (admin only)
     */
    public function approve($id)
    {
        try {
            $review = Review::findOrFail($id);
            $review->update(['status' => 'approved']);

            // Update product rating
            $this->updateProductRating($review->product_id);

            return response()->json([
                'success' => true,
                'message' => 'Review approved successfully'
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to approve review:', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to approve review'
            ], 500);
        }
    }

    /**
     * Delete a review (admin only)
     */
    public function destroy($id)
    {
        try {
            $review = Review::findOrFail($id);
            $productId = $review->product_id;
            
            $review->delete();

            // Update product rating
            $this->updateProductRating($productId);

            return response()->json([
                'success' => true,
                'message' => 'Review deleted successfully'
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to delete review:', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete review'
            ], 500);
        }
    }

    /**
     * Update product rating statistics
     */
    private function updateProductRating($productId)
    {
        try {
            $approvedReviews = Review::where('product_id', $productId)
                ->where('status', 'approved')
                ->get();

            $product = Product::find($productId);

            if ($approvedReviews->count() > 0) {
                $averageRating = $approvedReviews->avg('rating');
                $reviewCount = $approvedReviews->count();

                $product->update([
                    'average_rating' => number_format((float) $averageRating, 2, '.', ''),
                    'review_count' => $reviewCount
                ]);
            } else {
                // If no approved reviews, reset the ratings
                $product->update([
                    'average_rating' => 0.00,
                    'review_count' => 0
                ]);
            }

        } catch (\Exception $e) {
            \Log::error('Failed to update product rating:', [
                'product_id' => $productId,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function sellerReviews(Request $request)
    {
        try {
            $sellerId = Auth::id();
            $reviews = Review::whereHas('product', function ($query) use ($sellerId) {
                    $query->where('seller_id', $sellerId);
                })
                ->with(['user:id,name', 'product']) // Include only 'id' and 'name' from the User table
                ->orderBy('created_at', 'desc')
                ->paginate($request->per_page ?? 20);

            return response()->json([
                'success' => true,
                'data' => $reviews
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to fetch seller reviews:', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch your product reviews'
            ], 500);
        }
    }

        public function seller(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'required|string|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Check if user already reviewed this product
            $existingReview = Review::where('user_id', auth()->id())
                ->where('product_id', $request->product_id)
                ->first();

            if ($existingReview) {
                return response()->json([
                    'success' => false,
                    'message' => 'You have already reviewed this product'
                ], 400);
            }

            $review = Review::create([
                'user_id' => auth()->id(),
                'product_id' => $request->product_id,
                'rating' => $request->rating,
                'comment' => $request->comment,
                'status' => 'pending' // Requires admin approval
            ]);

            // Update product rating statistics
            $this->updateProductRating($request->product_id);

            return response()->json([
                'success' => true,
                'message' => 'Review submitted successfully. It will be visible after approval.',
                'data' => $review->load('user')
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to submit review:', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to submit review'
            ], 500);
        }
    }
}
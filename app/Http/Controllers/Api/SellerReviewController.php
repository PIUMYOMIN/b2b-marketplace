<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SellerReview;
use App\Models\SellerProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SellerReviewController extends Controller
{
    public function index()
    {
        $reviews = SellerReview::with(['user', 'seller'])
            ->where('status', 'approved')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $reviews
        ]);
    }

    public function store(Request $request, $seller)
    {
        $validator = Validator::make(array_merge($request->all(), ['seller_id' => $seller]), [
            'seller_id' => 'required|exists:seller_profiles,id',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'required|string|min:10|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = auth()->user();

            // Check if user already reviewed this seller
            $existingReview = SellerReview::where('user_id', $user->id)
                ->where('seller_id', $seller)
                ->first();

            if ($existingReview) {
                return response()->json([
                    'success' => false,
                    'message' => 'You have already reviewed this seller'
                ], 409);
            }

            $review = SellerReview::create([
                'user_id' => $user->id,
                'seller_id' => $seller,
                'rating' => $request->rating,
                'comment' => $request->comment,
                'status' => 'approved'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Review submitted successfully',
                'data' => $review->load('user')
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit review: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get approved reviews for a specific seller.
     *
     * @param string $identifier Seller ID or store slug
     * @return \Illuminate\Http\JsonResponse
     */
    public function sellerReviews($slug)
    {
        // Find the seller profile by ID or store slug
        $seller = SellerProfile::where('id', $slug)
            ->orWhere('store_slug', $slug)
            ->first();

        if (!$seller) {
            return response()->json([
                'success' => false,
                'message' => 'Seller not found'
            ], 404);
        }

        // Retrieve approved reviews with user details
        $reviews = SellerReview::with('user')
            ->where('seller_id', $seller->id)
            ->where('status', 'approved')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $reviews
        ]);
    }

    public function myReviews()
    {
        $user = auth()->user();
        $reviews = SellerReview::with('seller')
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $reviews
        ]);
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'rating' => 'sometimes|integer|min:1|max:5',
            'comment' => 'sometimes|string|min:10|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $review = SellerReview::findOrFail($id);
            $user = auth()->user();

            // Check if user owns the review
            if ($review->user_id !== $user->id && !$user->hasRole('admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to update this review'
                ], 403);
            }

            $review->update($request->only(['rating', 'comment']));

            return response()->json([
                'success' => true,
                'message' => 'Review updated successfully',
                'data' => $review
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update review: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $review = SellerReview::findOrFail($id);
            $user = auth()->user();

            // Check if user owns the review or is admin
            if ($review->user_id !== $user->id && !$user->hasRole('admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to delete this review'
                ], 403);
            }

            $review->delete();

            return response()->json([
                'success' => true,
                'message' => 'Review deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete review: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all seller reviews for admin (with pagination and filtering)
     */
    public function adminIndex(Request $request)
    {
        $query = SellerReview::with(['user', 'seller'])
            ->orderBy('created_at', 'desc');

        if ($request->has('status') && in_array($request->status, ['pending', 'approved', 'rejected'])) {
            $query->where('status', $request->status);
        }

        $reviews = $query->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $reviews
        ]);
    }

    /**
     * Get all pending seller reviews
     */
    public function pendingReviews()
    {
        $reviews = SellerReview::with(['user', 'seller'])
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $reviews
        ]);
    }

    /**
     * Approve a seller review
     */
    public function approve($id)
    {
        try {
            $review = SellerReview::findOrFail($id);
            $review->update(['status' => 'approved']);

            return response()->json([
                'success' => true,
                'message' => 'Review approved successfully',
                'data' => $review
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve review: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject a seller review
     */
    public function reject($id)
    {
        try {
            $review = SellerReview::findOrFail($id);
            $review->update(['status' => 'rejected']);

            return response()->json([
                'success' => true,
                'message' => 'Review rejected successfully',
                'data' => $review
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject review: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update review status (pending/approved/rejected)
     */
    public function updateStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,approved,rejected'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $review = SellerReview::findOrFail($id);
            $review->update(['status' => $request->status]);

            return response()->json([
                'success' => true,
                'message' => 'Review status updated successfully',
                'data' => $review
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update status: ' . $e->getMessage()
            ], 500);
        }
    }
}
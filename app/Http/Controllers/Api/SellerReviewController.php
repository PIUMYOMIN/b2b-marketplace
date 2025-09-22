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
}
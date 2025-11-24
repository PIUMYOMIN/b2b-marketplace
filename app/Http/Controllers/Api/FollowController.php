<?php

namespace App\Http\Controllers\Api;

use App\Models\Follow;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class FollowController extends Controller
{
    /**
     * Follow a seller
     */
    public function followSeller(Request $request, $sellerId)
    {
        try {
            $user = $request->user();
            $seller = User::findOrFail($sellerId);

            // Check if seller is actually a seller
            if ($seller->type !== 'seller') {
                return response()->json([
                    'success' => false,
                    'message' => 'User is not a seller'
                ], 422);
            }

            // Prevent following yourself
            if ($user->id === $seller->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot follow yourself'
                ], 422);
            }

            // Check if already following
            if ($user->isFollowing($sellerId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Already following this seller'
                ], 422);
            }

            // Create follow relationship
            $follow = $user->follow($sellerId);

            return response()->json([
                'success' => true,
                'message' => 'Successfully followed seller',
                'data' => [
                    'follow' => $follow,
                    'is_following' => true,
                    'followers_count' => $seller->followers()->count()
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error following seller: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to follow seller'
            ], 500);
        }
    }

    /**
     * Unfollow a seller
     */
    public function unfollowSeller(Request $request, $sellerId)
    {
        try {
            $user = $request->user();
            $seller = User::findOrFail($sellerId);

            // Check if actually following
            if (!$user->isFollowing($sellerId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Not following this seller'
                ], 422);
            }

            // Remove follow relationship
            $user->unfollow($sellerId);

            return response()->json([
                'success' => true,
                'message' => 'Successfully unfollowed seller',
                'data' => [
                    'is_following' => false,
                    'followers_count' => $seller->followers()->count()
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error unfollowing seller: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to unfollow seller'
            ], 500);
        }
    }

    /**
     * Toggle follow status
     */
    public function toggleFollow(Request $request, $sellerId)
    {
        try {
            $user = $request->user();
            $seller = User::findOrFail($sellerId);

            // Check if seller is actually a seller
            if ($seller->type !== 'seller') {
                return response()->json([
                    'success' => false,
                    'message' => 'User is not a seller'
                ], 422);
            }

            // Prevent following yourself
            if ($user->id === $seller->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot follow yourself'
                ], 422);
            }

            $isFollowing = $user->isFollowing($sellerId);

            if ($isFollowing) {
                $user->unfollow($sellerId);
                $message = 'Successfully unfollowed seller';
            } else {
                $user->follow($sellerId);
                $message = 'Successfully followed seller';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'is_following' => !$isFollowing,
                    'followers_count' => $seller->followers()->count()
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error toggling follow: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update follow status'
            ], 500);
        }
    }

    /**
     * Check if user is following a seller
     */
    public function checkFollowStatus(Request $request, $sellerId)
    {
        try {
            $user = $request->user();
            $seller = User::findOrFail($sellerId);

            $isFollowing = $user->isFollowing($sellerId);

            return response()->json([
                'success' => true,
                'data' => [
                    'is_following' => $isFollowing,
                    'followers_count' => $seller->followers()->count()
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error checking follow status: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to check follow status'
            ], 500);
        }
    }

    /**
     * Get user's followed sellers
     */
    public function getFollowedSellers(Request $request)
    {
        try {
            $user = $request->user();

            $followedSellers = $user->followingSellers()
                ->with('sellerProfile')
                ->withCount(['products' => function($query) {
                    $query->where('is_active', true);
                }])
                ->paginate($request->input('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $followedSellers
            ]);

        } catch (\Exception $e) {
            \Log::error('Error getting followed sellers: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get followed sellers'
            ], 500);
        }
    }

    /**
     * Get seller's followers
     */
    public function getSellerFollowers(Request $request, $sellerId)
    {
        try {
            $seller = User::findOrFail($sellerId);

            // Check if user is the seller or admin
            $user = $request->user();
            if ($user->id !== $seller->id && $user->type !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to view followers'
                ], 403);
            }

            $followers = $seller->followerUsers()
                ->with('sellerProfile')
                ->paginate($request->input('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $followers
            ]);

        } catch (\Exception $e) {
            \Log::error('Error getting seller followers: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get followers'
            ], 500);
        }
    }
}
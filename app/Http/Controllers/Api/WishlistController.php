<?php

namespace App\Http\Controllers\Api;

use App\Models\Product;
use App\Models\Wishlist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class WishlistController extends Controller
{
    /**
     * Get user's wishlist
     */
    public function index(Request $request)
{
    try {
        $user = auth()->user();
        
        $wishlist = Wishlist::with([
            'product.sellerProfile', // Adjust based on your actual relationship name
            'product.sellerProfile.user'
        ])
        ->where('user_id', $user->id)
        ->latest()
        ->get();
        
        // Transform the data
        $wishlistItems = $wishlist->map(function($item) {
            $product = $item->product;
            
            if (!$product) {
                return null;
            }
            
            // Handle product images (same as above)
            $images = [];
            if ($product->images) {
                if (is_array($product->images)) {
                    $images = $product->images;
                } elseif (is_string($product->images)) {
                    try {
                        $images = json_decode($product->images, true) ?: [];
                    } catch (\Exception $e) {
                        $images = [$product->images];
                    }
                }
            }
            
            $wishlistItem = [
                'id' => $product->id,
                'name' => $product->name,
                'name_mm' => $product->name_mm,
                'price' => $product->price,
                'images' => $images,
                'quantity' => $product->quantity,
                'min_order' => $product->min_order,
                'average_rating' => $product->average_rating,
                'review_count' => $product->review_count,
                'created_at' => $item->created_at,
                'wishlist_id' => $item->id
            ];
            
            // Add seller information based on your actual relationship structure
            if ($product->sellerProfile) {
                $wishlistItem['seller'] = [
                    'id' => $product->sellerProfile->id,
                    'store_name' => $product->sellerProfile->store_name,
                    'business_type' => $product->sellerProfile->business_type
                ];
            } elseif ($product->seller) {
                // Fallback to seller relationship if it exists
                $wishlistItem['seller'] = [
                    'id' => $product->seller->id,
                    'store_name' => $product->seller->store_name ?? 'Unknown Store',
                    'business_type' => $product->seller->business_type ?? 'General'
                ];
            }
            
            return $wishlistItem;
        })->filter();
        
        return response()->json([
            'success' => true,
            'data' => $wishlistItems->values(),
            'message' => 'Wishlist retrieved successfully'
        ]);
        
    } catch (\Exception $e) {
        \Log::error('Wishlist retrieval error:', ['error' => $e->getMessage()]);
        return response()->json([
            'success' => false,
            'message' => 'Failed to retrieve wishlist',
            'error' => $e->getMessage()
        ], 500);
    }
}

    /**
     * Add product to wishlist
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = auth()->user();
            
            // Check if already in wishlist using model method
            if (Wishlist::isInWishlist($user->id, $request->product_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product already in wishlist'
                ], 409);
            }
            
            $wishlist = Wishlist::create([
                'user_id' => $user->id,
                'product_id' => $request->product_id
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Product added to wishlist successfully',
                'data' => $wishlist
            ], 201);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add to wishlist',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove product from wishlist
     */
    public function destroy($productId)
    {
        try {
            $user = auth()->user();
            
            $wishlistItem = Wishlist::where('user_id', $user->id)
                ->where('product_id', $productId)
                ->first();
                
            if (!$wishlistItem) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found in your wishlist'
                ], 404);
            }
            
            $wishlistItem->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Product removed from wishlist successfully'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove from wishlist',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check if a product is in user's wishlist
     */
    public function check($productId)
    {
        try {
            $user = auth()->user();
            
            $isInWishlist = Wishlist::isInWishlist($user->id, $productId);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'is_in_wishlist' => $isInWishlist
                ],
                'message' => 'Wishlist status retrieved successfully'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to check wishlist status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get wishlist count
     */
    public function count()
    {
        try {
            $user = auth()->user();
            
            $count = Wishlist::where('user_id', $user->id)->count();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'count' => $count
                ],
                'message' => 'Wishlist count retrieved successfully'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get wishlist count',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
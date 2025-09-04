<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Wishlist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WishlistController extends Controller
{
    public function viewWishlist(Request $request)
    {
        try {
            $user = auth()->user();
            
            $wishlist = Wishlist::with('product')
                ->where('user_id', $user->id)
                ->get()
                ->pluck('product');
            
            return response()->json([
                'success' => true,
                'data' => $wishlist,
                'message' => 'Wishlist retrieved successfully'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve wishlist',
                'error' => $e->getMessage()
            ], 500);
        }
    }

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
            
            // Check if already in wishlist
            $existing = Wishlist::where('user_id', $user->id)
                ->where('product_id', $request->product_id)
                ->first();
                
            if ($existing) {
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
                'message' => 'Product added to wishlist',
                'data' => $wishlist->load('product')
            ], 201);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add to wishlist',
                'error' => $e->getMessage()
            ], 500);
        }
    }



    public function destroy($productId)
    {
        try {
            $user = auth()->user();
            
            $wishlist = Wishlist::where('user_id', $user->id)
                ->where('product_id', $productId)
                ->first();
                
            if (!$wishlist) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found in wishlist'
                ], 404);
            }
            
            $wishlist->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Product removed from wishlist'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove from wishlist',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
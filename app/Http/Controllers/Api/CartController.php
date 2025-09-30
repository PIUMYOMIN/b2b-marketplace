<?php

namespace App\Http\Controllers\Api;

use App\Models\Cart;
use App\Models\Product;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CartController extends Controller
{
    /**
     * Get user's cart items
     */
    public function index()
    {
        try {
            Log::info('Cart index called for user: ' . Auth::id());
            
            // Check if user has buyer role
            $user = Auth::user();
            if (!$user->hasRole('buyer')) {
                Log::warning('User does not have buyer role', ['user_id' => $user->id, 'roles' => $user->getRoleNames()]);
                return response()->json([
                    'success' => false,
                    'message' => 'Only buyers can access cart'
                ], 403);
            }

            $cartItems = Cart::with(['product.category'])
                ->where('user_id', Auth::id())
                ->get()
                ->map(function ($item) {
                    $product = $item->product;
                    
                    // Handle product image
                    $image = '/placeholder-product.jpg';
                    if ($product->images) {
                        if (is_array($product->images)) {
                            $image = $product->images[0]['url'] ?? $product->images[0] ?? $image;
                        } else if (is_string($product->images)) {
                            $imagesArray = json_decode($product->images, true);
                            if (is_array($imagesArray)) {
                                $image = $imagesArray[0]['url'] ?? $imagesArray[0] ?? $image;
                            }
                        }
                    }

                    return [
                        'id' => $item->id,
                        'product_id' => $product->id,
                        'name' => $product->name,
                        'price' => $item->price,
                        'quantity' => $item->quantity,
                        'image' => $image,
                        'category' => $product->category->name ?? 'Uncategorized',
                        'stock' => $product->quantity,
                        'min_order' => $product->min_order ?? 1,
                        'is_available' => $item->is_available,
                        'is_quantity_valid' => $item->is_quantity_valid,
                        'subtotal' => $item->subtotal
                    ];
                });

            $subtotal = $cartItems->sum('subtotal');
            $totalItems = $cartItems->sum('quantity');

            return response()->json([
                'success' => true,
                'data' => [
                    'cart_items' => $cartItems,
                    'subtotal' => $subtotal,
                    'total_items' => $totalItems,
                    'summary' => [
                        'subtotal' => $subtotal,
                        'shipping_fee' => 5000,
                        'tax_rate' => 0.05,
                        'tax' => $subtotal * 0.05,
                        'total' => $subtotal + 5000 + ($subtotal * 0.05)
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Cart index error: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch cart items',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Add item to cart
     */
    public function store(Request $request)
    {
        try {
            $user = Auth::user();
            
            // Check if user has buyer role
            if (!$user->hasRole('buyer')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only buyers can add items to cart'
                ], 403);
            }

            $request->validate([
                'product_id' => 'required|exists:products,id',
                'quantity' => 'required|integer|min:1'
            ]);

            $product = Product::findOrFail($request->product_id);

            // Check product availability
            if (!$product->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product is not available'
                ], 400);
            }

            if ($product->quantity < $request->quantity) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient stock. Only ' . $product->quantity . ' items available'
                ], 400);
            }

            // Check minimum order quantity
            $minOrder = $product->min_order ?? 1;
            if ($request->quantity < $minOrder) {
                return response()->json([
                    'success' => false,
                    'message' => 'Minimum order quantity is ' . $minOrder
                ], 400);
            }

            // Check if item already in cart
            $existingCartItem = Cart::where('user_id', Auth::id())
                ->where('product_id', $request->product_id)
                ->first();

            if ($existingCartItem) {
                // Update quantity if exists
                $newQuantity = $existingCartItem->quantity + $request->quantity;
                
                if ($product->quantity < $newQuantity) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Cannot add more items. Only ' . $product->quantity . ' items available in stock'
                    ], 400);
                }

                $existingCartItem->update([
                    'quantity' => $newQuantity
                ]);

                $cartItem = $existingCartItem;
            } else {
                // Handle product image for product_data
                $image = '/placeholder-product.jpg';
                if ($product->images) {
                    if (is_array($product->images)) {
                        $image = $product->images[0]['url'] ?? $product->images[0] ?? $image;
                    } else if (is_string($product->images)) {
                        $imagesArray = json_decode($product->images, true);
                        if (is_array($imagesArray)) {
                            $image = $imagesArray[0]['url'] ?? $imagesArray[0] ?? $image;
                        }
                    }
                }

                // Create new cart item
                $cartItem = Cart::create([
                    'user_id' => Auth::id(),
                    'product_id' => $request->product_id,
                    'quantity' => $request->quantity,
                    'price' => $product->price,
                    'product_data' => [
                        'name' => $product->name,
                        'image' => $image,
                        'category' => $product->category->name ?? 'Uncategorized'
                    ]
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Product added to cart successfully',
                'data' => $cartItem
            ]);

        } catch (\Exception $e) {
            Log::error('Cart store error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to add product to cart',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Update cart item quantity
     */
    public function update(Request $request, Cart $cart)
    {
        // Check ownership
        if ($cart->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $request->validate([
            'quantity' => 'required|integer|min:1'
        ]);

        // Check product availability
        if (!$cart->product->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Product is no longer available'
            ], 400);
        }

        if ($cart->product->quantity < $request->quantity) {
            return response()->json([
                'success' => false,
                'message' => 'Only ' . $cart->product->quantity . ' items available in stock'
            ], 400);
        }

        // Check minimum order
        $minOrder = $cart->product->min_order ?? 1;
        if ($request->quantity < $minOrder) {
            return response()->json([
                'success' => false,
                'message' => 'Minimum order quantity is ' . $minOrder
            ], 400);
        }

        $cart->update([
            'quantity' => $request->quantity
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Cart updated successfully',
            'data' => $cart
        ]);
    }

    /**
     * Remove item from cart
     */
    public function destroy(Cart $cart)
    {
        // Check ownership
        if ($cart->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $cart->delete();

        return response()->json([
            'success' => true,
            'message' => 'Item removed from cart'
        ]);
    }

    /**
     * Clear entire cart
     */
    public function clear()
    {
        // Only buyers can clear cart
        if (Auth::user()->roles->pluck('name')->contains('admin') || 
            Auth::user()->roles->pluck('name')->contains('seller')) {
            return response()->json([
                'success' => false,
                'message' => 'Only buyers can clear cart'
            ], 403);
        }

        Cart::where('user_id', Auth::id())->delete();

        return response()->json([
            'success' => true,
            'message' => 'Cart cleared successfully'
        ]);
    }

    /**
     * Get cart count (for header)
     */
    public function count()
    {
        // Only buyers can access cart count
        if (Auth::user()->roles->pluck('name')->contains('admin') || 
            Auth::user()->roles->pluck('name')->contains('seller')) {
            return response()->json([
                'success' => false,
                'message' => 'Only buyers can access cart'
            ], 403);
        }

        $count = Cart::where('user_id', Auth::id())->sum('quantity');

        return response()->json([
            'success' => true,
            'data' => [
                'count' => $count
            ]
        ]);
    }
}
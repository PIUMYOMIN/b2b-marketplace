<?php

namespace App\Http\Controllers\Api;

use App\Models\Cart;
use App\Models\Product;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class CartController extends Controller
{
    /**
     * Get user's cart items with current product data
     */
    public function index()
    {
        try {
            $user = Auth::user();

            if (!$user || !$user->hasRole('buyer')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only buyers can access cart'
                ], 403);
            }

            // Load product with category, exclude soft‑deleted products
            $cartItems = Cart::with([
                'product' => function ($q) {
                    $q->whereNull('deleted_at');
                },
                'product.category'
            ])
                ->where('user_id', $user->id)
                ->get()
                ->filter(function ($item) {
                    return $item->product !== null;
                })
                ->map(function ($item) {
                    $product = $item->product;

                    // Update stored price if product price changed
                    if ((float) $item->price != (float) $product->price) {
                        $item->price = $product->price;
                        $item->save();
                    }

                    $isAvailable = $product->is_active && $product->quantity > 0;
                    $isQuantityValid = $item->quantity <= $product->quantity;

                    // Get the primary image URL
                    $image = $this->getProductImageUrl($product);

                    return [
                        'id' => $item->id,
                        'product_id' => $product->id,
                        'name' => $product->name,
                        'price' => (float) $product->price,
                        'quantity' => (int) $item->quantity,
                        'image' => $image,
                        'category' => $product->category?->name ?? 'Uncategorized',
                        'stock' => (int) $product->quantity,
                        'min_order' => $product->min_order ?? 1,
                        'is_available' => $isAvailable,
                        'is_quantity_valid' => $isQuantityValid,
                        'subtotal' => (float) ($product->price * $item->quantity)
                    ];
                });

            $subtotal = $cartItems->sum('subtotal');
            $totalItems = $cartItems->sum('quantity');

            return response()->json([
                'success' => true,
                'data' => [
                    'cart_items' => $cartItems->values(),
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
            Log::error('Cart index error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch cart items'
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

            if (!$user || !$user->hasRole('buyer')) {
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

            $minOrder = $product->min_order ?? 1;
            if ($request->quantity < $minOrder) {
                return response()->json([
                    'success' => false,
                    'message' => 'Minimum order quantity is ' . $minOrder
                ], 400);
            }

            // Check if item already in cart
            $existingCartItem = Cart::where('user_id', $user->id)
                ->where('product_id', $request->product_id)
                ->first();

            if ($existingCartItem) {
                $newQuantity = $existingCartItem->quantity + $request->quantity;
                if ($product->quantity < $newQuantity) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Cannot add more items. Only ' . $product->quantity . ' items available'
                    ], 400);
                }

                $existingCartItem->update([
                    'quantity' => $newQuantity,
                    'price' => $product->price
                ]);

                $cartItem = $existingCartItem;
                $message = 'Cart updated successfully';
            } else {
                $categoryName = $product->category?->name ?? 'Uncategorized';
                $image = $this->getProductImageUrl($product);

                $cartItem = Cart::create([
                    'user_id' => $user->id,
                    'product_id' => $request->product_id,
                    'quantity' => $request->quantity,
                    'price' => $product->price,
                    'product_data' => [
                        'name' => $product->name,
                        'image' => $image,
                        'category' => $categoryName,
                    ]
                ]);

                $message = 'Product added to cart successfully';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $cartItem
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Cart store error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to add product to cart'
            ], 500);
        }
    }

    /**
     * Update cart item quantity
     */
    public function update(Request $request, $id)
    {
        try {
            $user = Auth::user();

            if (!$user || !$user->hasRole('buyer')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only buyers can update cart'
                ], 403);
            }

            $cartItem = Cart::findOrFail($id);

            // Check ownership
            if ($cartItem->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $request->validate([
                'quantity' => 'required|integer|min:1'
            ]);

            $product = $cartItem->product;

            if (!$product || !$product->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product is no longer available'
                ], 400);
            }

            if ($product->quantity < $request->quantity) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only ' . $product->quantity . ' items available in stock'
                ], 400);
            }

            $minOrder = $product->min_order ?? 1;
            if ($request->quantity < $minOrder) {
                return response()->json([
                    'success' => false,
                    'message' => 'Minimum order quantity is ' . $minOrder
                ], 400);
            }

            $cartItem->update([
                'quantity' => $request->quantity,
                'price' => $product->price
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cart updated successfully',
                'data' => $cartItem
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'errors' => $e->errors()
            ], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Cart item not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Cart update error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update cart'
            ], 500);
        }
    }

    /**
     * Remove item from cart
     */
    public function destroy($id)
    {
        try {
            $user = Auth::user();

            if (!$user || !$user->hasRole('buyer')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only buyers can remove items from cart'
                ], 403);
            }

            $cartItem = Cart::findOrFail($id);

            // Check ownership
            if ($cartItem->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $cartItem->delete();

            return response()->json([
                'success' => true,
                'message' => 'Item removed from cart successfully'
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Cart item not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Cart destroy error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove item'
            ], 500);
        }
    }

    /**
     * Clear entire cart
     */
    public function clear()
    {
        try {
            $user = Auth::user();

            if (!$user || !$user->hasRole('buyer')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only buyers can clear cart'
                ], 403);
            }

            Cart::where('user_id', $user->id)->delete();

            return response()->json([
                'success' => true,
                'message' => 'Cart cleared successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Cart clear error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear cart'
            ], 500);
        }
    }

    /**
     * Get cart count (for header)
     */
    public function count()
    {
        try {
            $user = Auth::user();

            if (!$user || !$user->hasRole('buyer')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only buyers can access cart count'
                ], 403);
            }

            $count = Cart::where('user_id', $user->id)->sum('quantity');

            return response()->json([
                'success' => true,
                'data' => [
                    'count' => $count
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Cart count error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get cart count'
            ], 500);
        }
    }

    /**
     * Helper to get safe product image URL
     */
    private function getProductImageUrl($product)
    {
        $default = '/placeholder-product.jpg';
        if (!$product->images) {
            return $default;
        }

        $images = $product->images;
        if (is_string($images)) {
            $images = json_decode($images, true);
        }
        if (!is_array($images) || empty($images)) {
            return $default;
        }

        $firstImage = $images[0];
        $url = null;

        if (is_array($firstImage)) {
            $url = $firstImage['full_url'] ?? $firstImage['url'] ?? $firstImage['path'] ?? null;
        } elseif (is_string($firstImage)) {
            $url = $firstImage;
        }

        if (!$url) {
            return $default;
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $url = url('storage/' . ltrim($url, '/'));
        }

        return $url;
    }
}

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
     * Get user's cart items
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

            $cartItems = Cart::with([
                'product' => function ($query) {
                    $query->withTrashed(); // Include soft-deleted products to avoid null
                },
                'product.category'
            ])
                ->where('user_id', $user->id)
                ->get()
                ->map(function ($item) {
                    $product = $item->product;

                    // If product is soft-deleted, mark as unavailable
                    $isAvailable = $product && !$product->trashed() && $product->is_active && $product->quantity > 0;

                    // Update price if product exists and price changed
                    if ($product && !$product->trashed() && $item->price != $product->price) {
                        $item->price = $product->price;
                        $item->save();
                    }

                    // Fall back to cached product_data when product is deleted
                    $cachedData = $item->product_data ?? [];

                    // Get product name safely — use cached name if product is gone
                    $productName = ($product && !$product->trashed())
                        ? ($product->name ?? 'Product')
                        : ($cachedData['name'] ?? 'Product Unavailable');

                    $productPrice = ($product && !$product->trashed())
                        ? (float) $product->price
                        : (float) $item->price;

                    $stock = ($product && !$product->trashed()) ? (int) $product->quantity : 0;

                    $categoryName = ($product && !$product->trashed() && $product->category)
                        ? $product->category->name_en
                        : ($cachedData['category'] ?? 'Uncategorized');

                    // Use cached image if product is gone
                    $image = ($product && !$product->trashed())
                        ? $this->getProductImageUrl($product)
                        : ($cachedData['image'] ?? '/placeholder-product.jpg');

                    return [
                        'id' => $item->id,
                        'product_id' => $item->product_id,
                        'name' => $productName,
                        'price' => $productPrice,
                        'quantity' => (int) $item->quantity,
                        'image' => $image,
                        'category' => $categoryName,
                        'stock' => $stock,
                        'min_order' => $product && !$product->trashed() ? ($product->min_order ?? 1) : 1,
                        'is_available' => $isAvailable,
                        'is_quantity_valid' => $isAvailable && $item->quantity <= $stock,
                        'subtotal' => $productPrice * $item->quantity
                    ];
                })
                ->filter(function ($item) {
                    // Keep items even if product is deleted (but mark them as unavailable)
                    return true;
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
            Log::error('Cart index error: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);

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

            // Check if product is soft-deleted
            if ($product->trashed()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product is no longer available'
                ], 400);
            }

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
                $cartItem = Cart::create([
                    'user_id' => $user->id,
                    'product_id' => $request->product_id,
                    'quantity' => $request->quantity,
                    'price' => $product->price,
                    'product_data' => [
                        'name' => $product->name,
                        'image' => $this->getProductImageUrl($product),
                        'category' => $product->category?->name ?? 'Uncategorized',
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

            // Check ownership — cast both sides to int to avoid strict type mismatch
            // (PDO can return user_id as string depending on DB driver)
            if ((int) $cartItem->user_id !== (int) $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized - This item does not belong to you'
                ], 403);
            }

            $request->validate([
                'quantity' => 'required|integer|min:1'
            ]);

            $product = $cartItem->product;

            if (!$product || $product->trashed()) {
                // Product is deleted, remove from cart
                $cartItem->delete();
                return response()->json([
                    'success' => false,
                    'message' => 'Product is no longer available and has been removed from your cart'
                ], 400);
            }

            if (!$product->is_active) {
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

            // Check ownership — cast both sides to int to avoid strict type mismatch
            // (PDO can return user_id as string depending on DB driver)
            if ((int) $cartItem->user_id !== (int) $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized - This item does not belong to you'
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
            Log::error('Cart destroy error: ' . $e->getMessage(), [
                'cart_id' => $id,
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to remove item from cart'
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
     * Get cart count
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
        if (!$product || $product->trashed()) {
            return '/placeholder-product.jpg';
        }

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

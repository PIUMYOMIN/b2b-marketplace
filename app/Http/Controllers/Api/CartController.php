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
                    $query->withTrashed();
                },
                'product.category',
                'product.seller.sellerProfile',
                'variant',
            ])
                ->where('user_id', $user->id)
                ->get()
                ->map(function ($item) {
                    $product = $item->product;
                    $variant = $item->variant;   // null for simple products

                    $productGone = !$product || $product->trashed();
                    $cachedData  = $item->product_data ?? [];

                    // ── Effective price (variant > product, cached if gone) ────
                    $livePrice = $variant
                        ? (float) $variant->price
                        : (($productGone) ? (float) $item->price : (float) $product->price);

                    // Keep the stored price fresh
                    if (!$productGone && $item->price != $livePrice) {
                        $item->update(['price' => $livePrice]);
                    }

                    // ── Stock ─────────────────────────────────────────────────
                    // Variant products: stock = that specific variant's quantity.
                    // Simple products (no variants): use totalStock() which sums all active variants.
                    // Digital/service: stock = null (unlimited).
                    $stock = null;
                    if (!$productGone && $product->product_type === 'physical') {
                        $stock = $variant
                            ? (float) $variant->quantity
                            : $product->totalStock();
                    }

                    // ── Availability ──────────────────────────────────────────
                    $isAvailable = !$productGone
                        && $product->is_active
                        && ($product->product_type !== 'physical' || ($stock !== null && $stock > 0));

                    // ── Names / images ────────────────────────────────────────
                    $productName = $productGone
                        ? ($cachedData['name'] ?? 'Product Unavailable')
                        : $product->name_en;

                    $categoryName = (!$productGone && $product->category)
                        ? $product->category->name_en
                        : ($cachedData['category'] ?? 'Uncategorized');

                    $image = $productGone
                        ? ($cachedData['image'] ?? null)
                        : $this->getProductImageUrl($product);

                    // ── MOQ / unit ────────────────────────────────────────────
                    $moq  = $item->effectiveMoq();
                    $unit = $productGone
                        ? ($cachedData['unit'] ?? 'piece')
                        : ($variant ? $variant->effectiveUnit() : $product->effectiveUnit());

                    // ── Discount / selling price ──────────────────────────────
                    // Selling price = discounted price when an active sale applies.
                    // We do NOT discount per-variant prices — discount is a product-level concept.
                    $sellingPrice = $livePrice;
                    $isOnSale     = false;
                    $discountPct  = 0.0;
                    $discountSaved = 0.0;
                    if (!$productGone && $product->is_on_sale) {
                        $today = now()->toDateString();
                        $inWindow = (!$product->discount_start || $product->discount_start <= $today)
                                 && (!$product->discount_end   || $product->discount_end   >= $today);
                        if ($inWindow && $product->discount_price) {
                            $isOnSale     = true;
                            $sellingPrice = (float) $product->discount_price;
                            $discountPct  = (float) ($product->discount_percentage ?? 0);
                            $discountSaved = round($livePrice - $sellingPrice, 2);
                        }
                    }

                    $subtotal = $sellingPrice * $item->quantity;

                    return [
                        'id'                   => $item->id,
                        'product_id'           => $item->product_id,
                        'variant_id'           => $item->variant_id,
                        'slug'                 => $productGone ? null : $product->slug_en,
                        'name'                 => $productName,
                        'price'                => $livePrice,
                        'selling_price'        => $sellingPrice,
                        'is_currently_on_sale' => $isOnSale,
                        'discount_percentage'  => $discountPct,
                        'discount_saved'       => $discountSaved,
                        'quantity'             => (int) $item->quantity,
                        'quantity_unit'        => $unit,
                        'image'                => $image,
                        'category'             => $categoryName,
                        'stock'                => $stock,          // null = unlimited
                        'min_order'            => $moq,
                        'selected_options'     => $item->selected_options,
                        'is_available'         => $isAvailable,
                        'is_quantity_valid'    => $isAvailable
                            && ($stock === null || $item->quantity <= $stock)
                            && $item->isQuantityValid(),
                        'subtotal'             => $subtotal,
                        'seller_id'            => $product?->seller?->sellerProfile?->user_id,
                        'seller_name'          => $product?->seller?->sellerProfile?->store_name,
                        'seller_slug'          => $product?->seller?->sellerProfile?->store_slug,
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
                        'subtotal'     => $subtotal,
                        'shipping_fee' => 8000,       // estimated; finalised at checkout by delivery zone
                        'tax_rate'     => 0.00,     // tax is currently 0% --- IGNORE --- set to 5% in OrderController when enabled
                        'tax'          => round($subtotal * 0.00, 2),
                        'total'        => round($subtotal + 8000 + ($subtotal * 0.00), 2),
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Cart index error: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch cart items',
                'debug'   => config('app.debug') ? $e->getMessage() : null,
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
                'product_id'       => 'required|exists:products,id',
                'quantity'         => 'required|numeric|min:0.001',
                'variant_id'       => 'nullable|exists:product_variants,id',
                'selected_options' => 'nullable|array',
            ]);

            $product = Product::findOrFail($request->product_id);

            if ($product->trashed()) {
                return response()->json(['success' => false, 'message' => 'Product is no longer available'], 400);
            }

            if (!$product->is_active) {
                return response()->json(['success' => false, 'message' => 'Product is not available'], 400);
            }

            // ── Variant-aware stock check ─────────────────────────────────
            $variant = null;
            if ($request->filled('variant_id')) {
                $variant = $product->variants()
                    ->where('id', $request->variant_id)
                    ->where('is_active', true)
                    ->first();

                if (!$variant) {
                    return response()->json(['success' => false, 'message' => 'Selected variant is not available'], 400);
                }

                if ($product->product_type === 'physical' && $variant->quantity < $request->quantity) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Insufficient stock. Only ' . (int) $variant->quantity . ' items available'
                    ], 400);
                }
            } elseif ($product->hasVariants()) {
                return response()->json(['success' => false, 'message' => 'Please select a variant before adding to cart'], 400);
            } else {
                if ($product->product_type === 'physical' && !$product->isInStock()) {
                    return response()->json(['success' => false, 'message' => 'This product is out of stock'], 400);
                }
            }

            $effectiveMoq = $variant ? $variant->effectiveMoq() : ($product->moq ?? 1);
            if ($request->quantity < $effectiveMoq) {
                return response()->json([
                    'success' => false,
                    'message' => 'Minimum order quantity is ' . $effectiveMoq
                ], 400);
            }

            // Step validation — quantity must follow: moq, moq+step, moq+2*step, …
            $effectiveStep = $variant ? $variant->effectiveStep() : $product->effectiveStep();
            if ($effectiveStep > 1) {
                $remainder = fmod($request->quantity - $effectiveMoq, $effectiveStep);
                if (abs($remainder) > 0.0001) {
                    $nextValid = $effectiveMoq + (ceil(($request->quantity - $effectiveMoq) / $effectiveStep) * $effectiveStep);
                    return response()->json([
                        'success' => false,
                        'message' => "Quantity must be in steps of {$effectiveStep}. Next valid quantity: {$nextValid}.",
                    ], 422);
                }
            }

            $cartPrice = $variant ? (float) $variant->price : (float) $product->price;
            $unitLabel  = $variant ? $variant->effectiveUnit() : $product->effectiveUnit();

            // ── Upsert — same product + same variant = merge quantities ───
            $existingCartItem = Cart::where('user_id', $user->id)
                ->where('product_id', $request->product_id)
                ->where('variant_id', $variant?->id)
                ->first();

            if ($existingCartItem) {
                $newQuantity = $existingCartItem->quantity + $request->quantity;

                if ($variant && $product->product_type === 'physical' && $variant->quantity < $newQuantity) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Cannot add more. Only ' . (int) $variant->quantity . ' items available'
                    ], 400);
                }

                $existingCartItem->update(['quantity' => $newQuantity, 'price' => $cartPrice]);
                $cartItem = $existingCartItem;
                $message  = 'Cart updated successfully';
            } else {
                $cartItem = Cart::create([
                    'user_id'          => $user->id,
                    'product_id'       => $product->id,
                    'variant_id'       => $variant?->id,
                    'selected_options' => $request->selected_options,
                    'quantity'         => $request->quantity,
                    'quantity_unit'    => $unitLabel,
                    'price'            => $cartPrice,
                    'product_data'     => [
                        'name'     => $product->name_en,
                        'image'    => $this->getProductImageUrl($product),
                        'category' => $product->category?->name_en ?? 'Uncategorized',
                        'sku'      => $variant?->sku ?? $product->sku,
                        'unit'     => $unitLabel,
                    ],
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
                'quantity' => 'required|numeric|min:0.001'
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
                return response()->json(['success' => false, 'message' => 'Product is no longer available'], 400);
            }

            // Re-resolve the variant from the cart item for stock checking
            $variant = $cartItem->variant_id
                ? $product->variants()->where('id', $cartItem->variant_id)->where('is_active', true)->first()
                : null;

            if ($variant) {
                if ($product->product_type === 'physical' && $variant->quantity < $request->quantity) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Only ' . (int) $variant->quantity . ' items available in stock'
                    ], 400);
                }
            } elseif ($product->product_type === 'physical' && !$product->isInStock()) {
                return response()->json(['success' => false, 'message' => 'This product is out of stock'], 400);
            }

            $effectiveMoq = $variant ? $variant->effectiveMoq() : ($product->moq ?? 1);
            if ($request->quantity < $effectiveMoq) {
                return response()->json([
                    'success' => false,
                    'message' => 'Minimum order quantity is ' . $effectiveMoq
                ], 400);
            }

            // Step validation
            $effectiveStep = $variant ? $variant->effectiveStep() : $product->effectiveStep();
            if ($effectiveStep > 1) {
                $remainder = fmod($request->quantity - $effectiveMoq, $effectiveStep);
                if (abs($remainder) > 0.0001) {
                    $nextValid = $effectiveMoq + (ceil(($request->quantity - $effectiveMoq) / $effectiveStep) * $effectiveStep);
                    return response()->json([
                        'success' => false,
                        'message' => "Quantity must be in steps of {$effectiveStep}. Next valid quantity: {$nextValid}.",
                    ], 422);
                }
            }

            $livePrice = $variant ? (float) $variant->price : (float) $product->price;

            $cartItem->update([
                'quantity' => $request->quantity,
                'price'    => $livePrice,
            ]);

            return response()->json([
                'success' => true,
                'message' => __('messages.cart.updated'),
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
                'message' => __('messages.cart.item_not_found')
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
                'message' => __('messages.cart.item_not_found')
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
                'message' => __('messages.cart.cleared')
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
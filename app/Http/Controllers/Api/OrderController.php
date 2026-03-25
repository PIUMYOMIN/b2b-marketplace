<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\OrderItem;
use App\Models\Delivery; // Add this import
use App\Models\DeliveryUpdate; // FIX: was used in confirm() but never imported → fatal error
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    const STATUS_PENDING = 'pending';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_PROCESSING = 'processing';
    const STATUS_SHIPPED = 'shipped';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_CANCELLED = 'cancelled';

    const PAYMENT_STATUS_PENDING = 'pending';
    const PAYMENT_STATUS_PAID = 'paid';
    const PAYMENT_STATUS_FAILED = 'failed';
    const PAYMENT_STATUS_REFUNDED = 'refunded';

    public function index()
    {
        $user = Auth::user();
        $baseUrl = config('app.url') . '/storage/';

        // Check user role to determine which orders to show
        if ($user->hasRole('seller')) {
            // For sellers, show orders where they are the seller
            $orders = Order::with(['items', 'buyer'])
                ->where('seller_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->get();
        } else if ($user->hasRole('buyer')) {
            // For buyers, show their own orders
            $orders = Order::with(['items', 'seller'])
                ->where('buyer_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->get();
        } else {
            // For admins, show all orders
            $orders = Order::with(['items', 'buyer', 'seller'])
                ->orderBy('created_at', 'desc')
                ->get();
        }

        // Map items to prepend full URL for images
        $orders->transform(function ($order) use ($baseUrl) {
            $order->items->transform(function ($item) use ($baseUrl) {
                $productData = $item->product_data;
                if (isset($productData['images']) && is_array($productData['images'])) {
                    foreach ($productData['images'] as &$image) {
                        if (!empty($image['url']) && !str_starts_with($image['url'], 'http')) {
                            $image['url'] = config('app.url') . '/storage/' . ltrim($image['url'], '/');
                        }
                    }
                }

                // handle fallback
                if (!empty($productData['image']) && !str_starts_with($productData['image'], 'http')) {
                    $productData['image'] = config('app.url') . '/storage/' . ltrim($productData['image'], '/');
                }

                // assign back
                $item->product_data = $productData;

                return $item;
            });

            return $order;
        });

        return response()->json([
            'success' => true,
            'data' => $orders
        ]);
    }

    public function store(Request $request)
    {
        DB::beginTransaction();

        try {
            $user = Auth::user();

            // Validate request - UPDATED PAYMENT METHODS
            $request->validate([
                'items' => 'required|array|min:1',
                'items.*.product_id' => 'required|exists:products,id',
                'items.*.quantity' => 'required|integer|min:1',
                'shipping_address' => 'required|array',
                'shipping_address.full_name' => 'required|string',
                'shipping_address.phone' => 'required|string',
                'shipping_address.address' => 'required|string',
                'payment_method' => 'required|in:kbz_pay,wave_pay,cb_pay,aya_pay,mmqr,cash_on_delivery',
                // Coupon fields (all optional — coupon is not required at checkout)
                'coupon_code' => 'nullable|string|max:50',
                'coupon_id' => 'nullable|integer|exists:coupons,id',
                'coupon_discount_amount' => 'nullable|numeric|min:0',
            ]);

            // Get cart items or use provided items
            $cartItems = $request->items;

            // Resolve and re-validate coupon server-side so the discount
            // cannot be spoofed by sending an inflated coupon_discount_amount.
            $coupon = null;
            $totalCouponDiscount = 0;

            if ($request->filled('coupon_code')) {
                $coupon = Coupon::where('code', strtoupper($request->coupon_code))->first();

                if (!$coupon || !$coupon->isValid()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Coupon code is invalid or has expired',
                    ], 422);
                }

                if ($coupon->hasUserExhausted($user->id)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'You have already used this coupon',
                    ], 422);
                }

                // Calculate the real server-side discount (ignore client value)
                $applicableSubtotal = 0;
                foreach ($cartItems as $item) {
                    $product = \App\Models\Product::find($item['product_id']);
                    if ($product && $coupon->appliesToProduct($product)) {
                        $applicableSubtotal += $product->price * $item['quantity'];
                    }
                }

                if ($applicableSubtotal <= 0) {
                    return response()->json([
                        'success' => false,
                        'message' => 'This coupon does not apply to any of your selected products',
                    ], 422);
                }

                $totalCouponDiscount = $coupon->calculateDiscount($applicableSubtotal);
            }

            // Group items by seller to create separate orders
            $itemsBySeller = [];
            $subtotal = 0;

            foreach ($cartItems as $item) {
                $product = Product::find($item['product_id']);

                if (!$product) {
                    throw new \Exception("Product not found: " . $item['product_id']);
                }

                if (!$product->is_active) {
                    throw new \Exception("Product is not available: " . $product->name);
                }

                if ($product->quantity < $item['quantity']) {
                    throw new \Exception("Insufficient stock for: " . $product->name);
                }

                $sellerId = $product->seller_id;
                $itemTotal = $product->price * $item['quantity'];
                $subtotal += $itemTotal;

                if (!isset($itemsBySeller[$sellerId])) {
                    $itemsBySeller[$sellerId] = [];
                }

                $itemsBySeller[$sellerId][] = [
                    'product' => $product,
                    'quantity' => $item['quantity'],
                    'price' => $product->price,
                    'subtotal' => $itemTotal
                ];
            }

            // Create orders for each seller
            $orders = [];
            $grandSubtotal = collect($itemsBySeller)->flatten(1)->sum('subtotal');

            foreach ($itemsBySeller as $sellerId => $sellerItems) {
                // Calculate seller-specific totals
                $sellerSubtotal = collect($sellerItems)->sum('subtotal');
                $sellerShippingFee = 5000;
                $sellerTax = $sellerSubtotal * 0.05;

                // Distribute coupon discount proportionally across seller orders.
                // e.g. if this seller's products are 60% of the cart, they absorb 60% of the discount.
                $sellerCouponDiscount = $grandSubtotal > 0
                    ? round($totalCouponDiscount * ($sellerSubtotal / $grandSubtotal), 2)
                    : 0;

                $sellerTotal = max(0, $sellerSubtotal + $sellerShippingFee + $sellerTax - $sellerCouponDiscount);

                // Generate order number
                $orderNumber = 'ORD-' . date('Ymd') . '-' . str_pad(Order::count() + 1, 5, '0', STR_PAD_LEFT);

                // Create order
                $order = Order::create([
                    'order_number' => $orderNumber,
                    'buyer_id' => $user->id,
                    'seller_id' => $sellerId,
                    'total_amount' => $sellerTotal,
                    'subtotal_amount' => $sellerSubtotal,
                    'shipping_fee' => $sellerShippingFee,
                    'tax_amount' => $sellerTax,
                    'tax_rate' => 0.05,
                    'status' => self::STATUS_PENDING,
                    'payment_method' => $request->payment_method,
                    'payment_status' => self::PAYMENT_STATUS_PENDING,
                    'shipping_address' => $request->shipping_address,
                    'order_notes' => $request->notes,
                    'commission_rate' => 0.10,
                    // Coupon columns (populated when a coupon was applied)
                    'coupon_id' => $coupon?->id,
                    'coupon_code' => $coupon?->code,
                    'coupon_discount_amount' => $sellerCouponDiscount,
                ]);

                // Calculate commission
                $order->commission_amount = $sellerSubtotal * $order->commission_rate;
                $order->save();

                // Create order items
                foreach ($sellerItems as $item) {
                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $item['product']->id,
                        'product_name' => $item['product']->name,
                        'product_sku' => $item['product']->sku,
                        'price' => $item['price'],
                        'quantity' => $item['quantity'],
                        'subtotal' => $item['subtotal'],
                        'product_data' => [
                            'name' => $item['product']->name,
                            'description' => $item['product']->description,
                            'images' => $item['product']->images,
                            'specifications' => $item['product']->specifications,
                            'category' => $item['product']->category->name ?? 'Uncategorized',
                            'seller_name' => $item['product']->seller->name ?? 'Unknown Seller'
                        ]
                    ]);

                    // Update product stock
                    $item['product']->decrement('quantity', $item['quantity']);
                }

                // Create delivery record for each order
                Delivery::create([
                    'order_id' => $order->id,
                    'supplier_id' => $sellerId,
                    'delivery_method' => 'supplier', // Default to supplier delivery
                    'pickup_address' => $this->getSupplierAddress($sellerId),
                    'delivery_address' => $request->shipping_address['address'],
                    'status' => 'pending',
                    'package_weight' => $this->calculateOrderWeight($sellerItems),
                    'estimated_delivery_date' => now()->addDays(5),
                ]);

                $orders[] = $order;
            }

            // Record coupon usage once, against the first order (usage tracks the buyer, not per-order)
            if ($coupon && !empty($orders)) {
                $coupon->recordUsage($user->id, $orders[0]->id, $totalCouponDiscount);
            }

            // Clear user's cart
            Cart::where('user_id', $user->id)->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Order created successfully',
                'data' => [
                    'orders' => $orders,
                    'total_orders' => count($orders)
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Order creation failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to create order: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show(Order $order)
    {
        $user = Auth::user();

        // Authorization check
        if ($user->hasRole('seller') && $order->seller_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to view this order'
            ], 403);
        }

        if ($user->hasRole('buyer') && $order->buyer_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to view this order'
            ], 403);
        }

        // Load relations with delivery
        $order->load(['items.product', 'buyer', 'seller', 'delivery']);

        // ✅ Transform images in order items
        foreach ($order->items as $item) {
            // Update product_data images
            $productData = $item->product_data;
            if (!empty($productData['images']) && is_array($productData['images'])) {
                $productData['images'] = $this->formatImages($productData['images']);
                $item->product_data = $productData;
            }

            // Update product images
            if ($item->product && !empty($item->product->images)) {
                $item->product->images = $this->formatImages($item->product->images);
            }
        }

        // ✅ Transform delivery proof image if exists
        if ($order->delivery && $order->delivery->delivery_proof_image) {
            if (!str_starts_with($order->delivery->delivery_proof_image, 'http')) {
                $order->delivery->delivery_proof_image = url('storage/' . ltrim($order->delivery->delivery_proof_image, '/'));
            }
        }

        return response()->json([
            'success' => true,
            'data' => $order
        ]);
    }

    /**
     * Format images to full URLs (add this helper method to OrderController)
     */
    protected function formatImages($images)
    {
        if (empty($images)) {
            return [];
        }

        $formattedImages = [];

        foreach ($images as $index => $image) {
            if (is_string($image)) {
                // If it's a string URL
                if (!str_starts_with($image, 'http')) {
                    $image = url('storage/' . ltrim($image, '/'));
                }
                $formattedImages[] = [
                    'url' => $image,
                    'angle' => 'default',
                    'is_primary' => $index === 0
                ];
            } else {
                // If it's an object with url/path property
                $url = $image['url'] ?? $image['path'] ?? '';
                if (!str_starts_with($url, 'http')) {
                    $url = url('storage/' . ltrim($url, '/'));
                }
                $formattedImages[] = [
                    'url' => $url,
                    'angle' => $image['angle'] ?? 'default',
                    'is_primary' => $image['is_primary'] ?? ($index === 0)
                ];
            }
        }

        return $formattedImages;
    }

    // Add payment update method
    public function updatePayment(Request $request, Order $order)
    {
        $request->validate([
            'payment_status' => 'required|in:paid,failed,refunded',
            'payment_data' => 'nullable|array'
        ]);

        DB::beginTransaction();
        try {
            $order->update([
                'payment_status' => $request->payment_status,
                'payment_data' => $request->payment_data
            ]);

            // If payment is successful, update order status
            if ($request->payment_status === 'paid') {
                $order->update(['status' => 'confirmed']);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payment status updated successfully',
                'data' => $order
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update payment status: ' . $e->getMessage()
            ], 500);
        }
    }


    public function cancel(Order $order, Request $request)
    {
        $user = Auth::user();

        // Authorization
        if ($user->hasRole('buyer') && (int) $order->buyer_id !== (int) $user->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized to cancel this order'], 403);
        }

        if ($user->hasRole('seller') && (int) $order->seller_id !== (int) $user->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized to cancel this order'], 403);
        }

        if (!in_array($order->status, [self::STATUS_PENDING, self::STATUS_CONFIRMED])) {
            return response()->json(['success' => false, 'message' => 'Order cannot be cancelled at this stage'], 400);
        }

        DB::beginTransaction();
        try {
            $order->update([
                'status' => self::STATUS_CANCELLED,
                'cancelled_at' => now(),
            ]);

            // FIX: eager-load items with their products before iterating.
            // Without this, $item->product is null if the product has been
            // soft-deleted, causing a fatal "Call to member function on null".
            $order->load('items.product');

            foreach ($order->items as $item) {
                if ($item->product) {
                    $item->product->increment('quantity', $item->quantity);
                }
            }

            // FIX: reverse coupon usage so the buyer can use the code again
            // and the used_count goes back down.
            if ($order->coupon_id) {
                $usage = \App\Models\CouponUsage::where('coupon_id', $order->coupon_id)
                    ->where('user_id', $order->buyer_id)
                    ->where('order_id', $order->id)
                    ->first();

                if ($usage) {
                    $usage->delete();
                    \App\Models\Coupon::where('id', $order->coupon_id)
                        ->where('used_count', '>', 0)
                        ->decrement('used_count');
                }
            }

            // FIX: cancel the associated delivery record so it doesn't remain
            // in-progress while the order itself is cancelled.
            $delivery = \App\Models\Delivery::where('order_id', $order->id)->first();
            if ($delivery && !in_array($delivery->status, ['delivered', 'failed'])) {
                $delivery->update(['status' => 'cancelled']);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Order cancelled successfully',
                'data' => $order->fresh(),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Order cancellation failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel order: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Confirm order (for sellers)
     */
    public function confirm($id)
    {
        $order = Order::findOrFail($id);

        $user = Auth::user();
        if ($user->hasRole('seller') && (int) $order->seller_id !== (int) $user->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        if ($order->status !== self::STATUS_PENDING) {
            return response()->json(['success' => false, 'message' => 'Only pending orders can be confirmed'], 400);
        }

        $order->status = 'confirmed';
        $order->save();

        // FIX: store() already creates a Delivery record when the order is placed.
        // Creating a second one here caused duplicate delivery rows and confusing
        // tracking state. We just update the existing one to 'awaiting_pickup'.
        $delivery = Delivery::where('order_id', $order->id)->first();
        if ($delivery) {
            $delivery->update(['status' => 'awaiting_pickup']);

            // FIX: DeliveryUpdate was used here without being imported — fatal error.
            // Import added at the top of this file.
            DeliveryUpdate::create([
                'delivery_id' => $delivery->id,
                'user_id' => Auth::id(),
                'status' => 'awaiting_pickup',
                'notes' => 'Order confirmed by seller. Awaiting pickup.',
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Order confirmed successfully',
            'data' => $order->load(['items', 'delivery']),
        ]);
    }

    /**
     * Mark order as processing (for sellers)
     */
    public function process(Order $order)
    {
        $user = Auth::user();

        if ($user->hasRole('seller') && $order->seller_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to process this order'
            ], 403);
        }

        if ($order->status !== self::STATUS_CONFIRMED) {
            return response()->json([
                'success' => false,
                'message' => 'Order must be confirmed before processing'
            ], 400);
        }

        $order->update([
            'status' => self::STATUS_PROCESSING
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Order marked as processing'
        ]);
    }

    /**
     * Mark order as shipped (for sellers)
     */
    public function ship(Request $request, Order $order)
    {
        $user = Auth::user();

        if ($user->hasRole('seller') && $order->seller_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to ship this order'
            ], 403);
        }

        if (!in_array($order->status, [self::STATUS_CONFIRMED, self::STATUS_PROCESSING])) {
            return response()->json([
                'success' => false,
                'message' => 'Order cannot be shipped in current status'
            ], 400);
        }

        $request->validate([
            'tracking_number' => 'nullable|string',
            'shipping_carrier' => 'nullable|string'
        ]);

        $order->update([
            'status' => self::STATUS_SHIPPED,
            'tracking_number' => $request->tracking_number,
            'shipping_carrier' => $request->shipping_carrier
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Order marked as shipped'
        ]);
    }

    /**
     * Mark order as delivered (for buyers)
     */
    public function confirmDelivery(Order $order)
    {
        $user = Auth::user();

        if ($user->hasRole('buyer') && $order->buyer_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to confirm delivery for this order'
            ], 403);
        }

        if ($order->status !== self::STATUS_SHIPPED) {
            return response()->json([
                'success' => false,
                'message' => 'Order must be shipped before confirming delivery'
            ], 400);
        }

        $order->update([
            'status' => self::STATUS_DELIVERED,
            'delivered_at' => now()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Delivery confirmed successfully'
        ]);
    }

    /**
     * Get seller's recent orders for dashboard
     */
    public function sellerRecentOrders()
    {
        $user = Auth::user();

        if (!$user->hasRole('seller')) {
            return response()->json([
                'success' => false,
                'message' => 'Only sellers can access this endpoint'
            ], 403);
        }

        $orders = Order::with(['items', 'buyer'])
            ->where('seller_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $orders
        ]);
    }

    /**
     * Get order statistics for seller dashboard
     */
    public function sellerOrderStats()
    {
        $user = Auth::user();

        if (!$user->hasRole('seller')) {
            return response()->json([
                'success' => false,
                'message' => 'Only sellers can access this endpoint'
            ], 403);
        }

        $totalOrders = Order::where('seller_id', $user->id)->count();
        $pendingOrders = Order::where('seller_id', $user->id)->where('status', self::STATUS_PENDING)->count();
        $confirmedOrders = Order::where('seller_id', $user->id)->where('status', self::STATUS_CONFIRMED)->count();
        $shippedOrders = Order::where('seller_id', $user->id)->where('status', self::STATUS_SHIPPED)->count();
        $deliveredOrders = Order::where('seller_id', $user->id)->where('status', self::STATUS_DELIVERED)->count();

        $totalRevenue = Order::where('seller_id', $user->id)
            ->where('status', self::STATUS_DELIVERED)
            ->sum('total_amount');

        return response()->json([
            'success' => true,
            'data' => [
                'total_orders' => $totalOrders,
                'pending_orders' => $pendingOrders,
                'confirmed_orders' => $confirmedOrders,
                'shipped_orders' => $shippedOrders,
                'delivered_orders' => $deliveredOrders,
                'total_revenue' => $totalRevenue,
                'pending_revenue' => Order::where('seller_id', $user->id)
                    ->whereIn('status', [self::STATUS_PENDING, self::STATUS_CONFIRMED, self::STATUS_SHIPPED])
                    ->sum('total_amount')
            ]
        ]);
    }

    // Helper methods for delivery
    private function getSupplierAddress($sellerId)
    {
        // This should fetch the supplier's address from their profile
        // For now, return a default address
        return "Supplier Warehouse Address";
    }

    private function calculateOrderWeight($items)
    {
        // Calculate total weight from items
        $totalWeight = 0;
        foreach ($items as $item) {
            $totalWeight += ($item['product']->weight_kg ?? 1) * $item['quantity'];
        }
        return $totalWeight;
    }
}

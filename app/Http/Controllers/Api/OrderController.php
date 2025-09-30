<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\OrderItem;
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
            
            // Validate request
            $request->validate([
                'items' => 'required|array|min:1',
                'items.*.product_id' => 'required|exists:products,id',
                'items.*.quantity' => 'required|integer|min:1',
                'shipping_address' => 'required|array',
                'shipping_address.full_name' => 'required|string',
                'shipping_address.phone' => 'required|string',
                'shipping_address.address' => 'required|string',
                'payment_method' => 'required|in:kbz_pay,wave_pay,cb_pay,cash_on_delivery',
            ]);

            // Get cart items or use provided items
            $cartItems = $request->items;
            
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
            
            foreach ($itemsBySeller as $sellerId => $sellerItems) {
                // Calculate seller-specific totals
                $sellerSubtotal = collect($sellerItems)->sum('subtotal');
                $sellerShippingFee = 5000; // You can calculate this per seller
                $sellerTax = $sellerSubtotal * 0.05;
                $sellerTotal = $sellerSubtotal + $sellerShippingFee + $sellerTax;

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
                    'commission_rate' => 0.10, // 10% commission
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
                            'category' => $item['product']->category->name ?? 'Uncategorized'
                        ]
                    ]);

                    // Update product stock
                    $item['product']->decrement('quantity', $item['quantity']);
                }

                $orders[] = $order;
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

        return response()->json([
            'success' => true,
            'data' => $order->load(['items.product', 'buyer', 'seller'])
        ]);
    }

    public function cancel(Order $order)
    {
        $user = Auth::user();
        
        // Authorization check
        if ($user->hasRole('buyer') && $order->buyer_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to cancel this order'
            ], 403);
        }
        
        if ($user->hasRole('seller') && $order->seller_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to cancel this order'
            ], 403);
        }

        if (!in_array($order->status, [self::STATUS_PENDING, self::STATUS_CONFIRMED])) {
            return response()->json([
                'success' => false,
                'message' => 'Order cannot be canceled at this stage'
            ], 400);
        }

        $order->update([
            'status' => self::STATUS_CANCELLED,
            'cancelled_at' => now()
        ]);

        // Restore product quantities
        foreach ($order->items as $item) {
            $item->product->increment('quantity', $item->quantity);
        }

        return response()->json([
            'success' => true,
            'message' => 'Order cancelled successfully'
        ]);
    }

    /**
     * Confirm order (for sellers)
     */
    public function confirm(Order $order)
    {
        $user = Auth::user();
        
        // Only seller can confirm their own orders
        if ($user->hasRole('seller') && $order->seller_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to confirm this order'
            ], 403);
        }

        if ($order->status !== self::STATUS_PENDING) {
            return response()->json([
                'success' => false,
                'message' => 'Order cannot be confirmed in current status'
            ], 400);
        }

        $order->update([
            'status' => self::STATUS_CONFIRMED
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Order confirmed successfully'
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
}
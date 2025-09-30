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

class OrderController extends Controller
{
    public function index()
{
    $user = Auth::user();
    
    $orders = Order::with(['items', 'seller'])
        ->where('buyer_id', $user->id)
        ->orderBy('created_at', 'desc')
        ->get();

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

                // Create order
                $order = Order::create([
                    'buyer_id' => $user->id,
                    'seller_id' => $sellerId,
                    'total_amount' => $sellerTotal,
                    'subtotal_amount' => $sellerSubtotal,
                    'shipping_fee' => $sellerShippingFee,
                    'tax_amount' => $sellerTax,
                    'tax_rate' => 0.05,
                    'status' => Order::STATUS_PENDING,
                    'payment_method' => $request->payment_method,
                    'payment_status' => Order::PAYMENT_STATUS_PENDING,
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
        $this->authorize('view', $order);

        return response()->json([
            'success' => true,
            'data' => $order->load(['items.product', 'buyer', 'seller'])
        ]);
    }

    public function cancel(Order $order)
    {
        $this->authorize('cancel', $order);

        if (!in_array($order->status, ['pending', 'confirmed'])) {
            return response()->json([
                'success' => false,
                'message' => 'Order cannot be canceled at this stage'
            ], 400);
        }

        $order->update(['status' => 'cancelled']);

        // Restore product quantities
        foreach ($order->items as $item) {
            $item->product->increment('quantity', $item->quantity);
        }

        return response()->json([
            'success' => true,
            'message' => 'Order cancelled successfully'
        ]);
    }
}
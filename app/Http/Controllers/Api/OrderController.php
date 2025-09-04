<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $query = Order::with(['items.product', 'buyer', 'seller']);

        if ($user->hasRole('seller')) {
            $query->where('seller_id', $user->id);
        } elseif ($user->hasRole('buyer')) {
            $query->where('buyer_id', $user->id);
        }

        $orders = $query->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $orders
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'payment_method' => 'required|in:mmqr,kbz_pay,wave_pay,bank_transfer,cod'
        ]);

        return DB::transaction(function () use ($request) {
            $order = Order::create([
                'buyer_id' => Auth::id(),
                'payment_method' => $request->payment_method,
                'status' => 'pending'
            ]);

            $totalAmount = 0;
            $commissionAmount = 0;

            foreach ($request->items as $item) {
                $product = Product::find($item['product_id']);
                
                $order->items()->create([
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'unit_price' => $product->price,
                    'commission_rate' => $product->category->commission_rate
                ]);

                $itemTotal = $product->price * $item['quantity'];
                $totalAmount += $itemTotal;
                $commissionAmount += $itemTotal * $product->category->commission_rate;

                // Update product quantity
                $product->decrement('quantity', $item['quantity']);
            }

            $order->update([
                'total_amount' => $totalAmount,
                'commission_amount' => $commissionAmount,
                'seller_id' => $order->items()->first()->product->seller_id
            ]);

            return response()->json([
                'success' => true,
                'data' => $order->load('items')
            ], 201);
        });
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
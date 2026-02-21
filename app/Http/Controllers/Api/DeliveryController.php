<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;

use App\Models\Delivery;
use App\Models\Order;
use App\Models\DeliveryUpdate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeliveryController extends Controller
{
    // Get deliveries for a supplier
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Delivery::with([
            'order' => function ($q) {
                $q->with('items.product');
            },
            'platformCourier',
            'deliveryUpdates'
        ]);

        if ($user->type === 'supplier') {
            $query->where('supplier_id', $user->id);
        } elseif ($user->type === 'courier') {
            $query->where('platform_courier_id', $user->id);
        } elseif ($user->type === 'buyer') {
            // Allow buyers to see deliveries for their orders
            $query->whereHas('order', function ($q) use ($user) {
                $q->where('buyer_id', $user->id);
            });
        }

        // Filter by order_id if provided
        if ($request->has('order_id')) {
            $query->where('order_id', $request->order_id);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by delivery method
        if ($request->has('delivery_method')) {
            $query->where('delivery_method', $request->delivery_method);
        }

        $deliveries = $query->latest()->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $deliveries
        ]);
    }

    // Choose delivery method for an order
    public function chooseDeliveryMethod(Request $request, $orderId)
    {
        $order = Order::find($orderId);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }

        $order->load('delivery');

        $request->validate([
            'delivery_method' => 'required|in:supplier,platform',
            'platform_delivery_fee' => 'required_if:delivery_method,platform|numeric|min:0',
            'pickup_address' => 'required|string',
        ]);

        // Check if user is the order supplier
        if ($request->user()->id !== $order->seller_id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to set delivery method for this order'
            ], 403);
        }

        DB::beginTransaction();
        try {
            // Create or update delivery record
            $delivery = Delivery::updateOrCreate(
                ['order_id' => $order->id],
                [
                    'supplier_id' => $order->seller_id,
                    'delivery_method' => $request->delivery_method,
                    'platform_delivery_fee' => $request->platform_delivery_fee ?? 0,
                    'pickup_address' => $request->pickup_address,
                    'delivery_address' => $order->shipping_address,
                    'status' => $request->delivery_method === 'platform' ? 'awaiting_pickup' : 'pending',
                    'package_weight' => $this->calculateOrderWeight($order),
                    'estimated_delivery_date' => now()->addDays($request->delivery_method === 'platform' ? 3 : 5),
                ]
            );

            // Generate tracking number
            $delivery->generateTrackingNumber();
            $delivery->save();

            // Create initial status update
            DeliveryUpdate::create([
                'delivery_id' => $delivery->id,
                'user_id' => $request->user()->id,
                'status' => $delivery->status,
                'notes' => "Delivery method set to: " . ucfirst($request->delivery_method),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Delivery method set successfully',
                'data' => $delivery->load(['order', 'deliveryUpdates'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to set delivery method: ' . $e->getMessage()
            ], 500);
        }
    }

    // Update delivery status
    public function updateStatus(Request $request, Delivery $delivery)
    {
        $request->validate([
            'status' => 'required|in:awaiting_pickup,picked_up,in_transit,out_for_delivery,delivered,failed,cancelled,returned',
            'notes' => 'nullable|string',
            'location' => 'nullable|string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        if (!$delivery->canBeUpdatedBy($request->user())) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to update this delivery'
            ], 403);
        }

        DB::beginTransaction();
        try {
            $oldStatus = $delivery->status;
            $delivery->status = $request->status;

            if ($request->status === 'delivered') {
                $delivery->delivered_at = now();
                // Update the associated order status
                $delivery->order->update(['status' => 'delivered']);
            }

            // Set timestamps based on status
            switch ($request->status) {
                case 'picked_up':
                    $delivery->picked_up_at = now();
                    break;
            }

            $delivery->save();

            // Create status update record
            DeliveryUpdate::create([
                'delivery_id' => $delivery->id,
                'user_id' => $request->user()->id,
                'status' => $request->status,
                'location' => $request->location,
                'notes' => $request->notes,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Delivery status updated successfully',
                'data' => $delivery->load(['deliveryUpdates'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update delivery status: ' . $e->getMessage()
            ], 500);
        }
    }

    // Upload delivery proof
    public function uploadDeliveryProof(Request $request, Delivery $delivery)
    {
        $request->validate([
            'delivery_proof' => 'required|image|mimes:jpeg,png,jpg|max:5120',
            'recipient_name' => 'required|string',
            'recipient_phone' => 'required|string',
        ]);

        if (!$delivery->canBeUpdatedBy($request->user())) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to update this delivery'
            ], 403);
        }

        DB::beginTransaction();
        try {
            // Upload proof image
            if ($request->hasFile('delivery_proof')) {
                $path = $request->file('delivery_proof')->store('delivery-proofs', 'public');
                $delivery->delivery_proof_image = $path;
            }

            $delivery->recipient_name = $request->recipient_name;
            $delivery->recipient_phone = $request->recipient_phone;
            $delivery->status = 'delivered';
            $delivery->delivered_at = now();

            // Update the associated order status
            $delivery->order->update(['status' => 'delivered']);

            $delivery->save();

            // Create status update
            DeliveryUpdate::create([
                'delivery_id' => $delivery->id,
                'user_id' => $request->user()->id,
                'status' => 'delivered',
                'notes' => 'Delivery completed with proof uploaded',
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Delivery proof uploaded successfully',
                'data' => $delivery
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload delivery proof: ' . $e->getMessage()
            ], 500);
        }
    }

    // Assign platform courier
    public function assignCourier(Request $request, Delivery $delivery)
    {
        $request->validate([
            'platform_courier_id' => 'required|exists:users,id',
            'driver_name' => 'nullable|string',
            'driver_phone' => 'nullable|string',
            'vehicle_type' => 'nullable|string',
            'vehicle_number' => 'nullable|string',
        ]);

        if ($request->user()->type !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Only admins can assign couriers'
            ], 403);
        }

        $delivery->update([
            'platform_courier_id' => $request->platform_courier_id,
            'assigned_driver_name' => $request->driver_name,
            'assigned_driver_phone' => $request->driver_phone,
            'assigned_vehicle_type' => $request->vehicle_type,
            'assigned_vehicle_number' => $request->vehicle_number,
            'status' => 'awaiting_pickup',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Courier assigned successfully',
            'data' => $delivery->load('platformCourier')
        ]);
    }

    // Get delivery tracking updates
    public function getTrackingUpdates(Delivery $delivery)
    {
        $updates = $delivery->deliveryUpdates()
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'delivery' => $delivery,
                'updates' => $updates
            ]
        ]);
    }

    // Calculate order weight (helper method)
    private function calculateOrderWeight(Order $order)
    {
        // This would typically calculate based on order items
        // For now, return a default or calculate from product weights
        return 5.0; // Default 5kg
    }
}
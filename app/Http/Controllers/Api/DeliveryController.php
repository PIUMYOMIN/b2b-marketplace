<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\DeliveryUpdate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DeliveryController extends Controller
{
    // Get deliveries for the authenticated user
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Delivery::with([
            'order' => function ($q) {
                $q->with('items.product');
            },
            'platformCourier',
            'deliveryUpdates',
        ]);

        if ($user->hasRole('seller') || $user->type === 'seller') {
            $query->whereHas('order', function ($q) use ($user) {
                $q->where('seller_id', $user->id);
            });
        } elseif ($user->hasRole('courier') || $user->type === 'courier') {
            $query->where('platform_courier_id', $user->id);
        } elseif ($user->hasRole('buyer') || $user->type === 'buyer') {
            $query->whereHas('order', function ($q) use ($user) {
                $q->where('buyer_id', $user->id);
            });
        }

        if ($request->has('order_id')) {
            $query->where('order_id', $request->order_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('delivery_method')) {
            $query->where('delivery_method', $request->delivery_method);
        }

        $deliveries = $query->latest()->paginate(20);

        $deliveries->getCollection()->transform(function ($delivery) {
            return $this->formatDeliveryImages($delivery);
        });

        return response()->json([
            'success' => true,
            'data' => $deliveries,
        ]);
    }

    // Choose delivery method for an order
    public function chooseDeliveryMethod(Request $request, $orderId)
    {
        $order = Order::find($orderId);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found',
            ], 404);
        }

        $order->load('delivery');

        $request->validate([
            'delivery_method' => 'required|in:supplier,platform',
            'platform_delivery_fee' => 'required_if:delivery_method,platform|numeric|min:0',
            'pickup_address' => 'required|string',
        ]);

        $userId = (int) $request->user()->id;
        $orderSellerId = (int) $order->seller_id;
        $deliverySupplierId = $order->delivery ? (int) $order->delivery->supplier_id : null;

        // Allow if user is the order's seller, or (for legacy data) the delivery's supplier
        if ($userId !== $orderSellerId) {
            if (!$order->delivery || $userId !== $deliverySupplierId) {
                Log::warning('Unauthorized delivery method attempt', [
                    'user_id' => $userId,
                    'order_seller_id' => $orderSellerId,
                    'delivery_supplier_id' => $deliverySupplierId,
                    'order_id' => $order->id,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to set delivery method for this order',
                ], 403);
            }
        }

        DB::beginTransaction();
        try {
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

            $delivery->generateTrackingNumber();
            $delivery->save();

            DeliveryUpdate::create([
                'delivery_id' => $delivery->id,
                'user_id' => $request->user()->id,
                'status' => $delivery->status,
                'notes' => 'Delivery method set to: ' . ucfirst($request->delivery_method),
            ]);

            DB::commit();

            $delivery->load(['order.items.product', 'deliveryUpdates']);
            $delivery = $this->formatDeliveryImages($delivery);

            return response()->json([
                'success' => true,
                'message' => 'Delivery method set successfully',
                'data' => $delivery,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to set delivery method: ' . $e->getMessage(),
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
                'message' => 'Unauthorized to update this delivery',
            ], 403);
        }

        DB::beginTransaction();
        try {
            $delivery->status = $request->status;

            // FIX: set timestamps for all relevant status transitions, not just picked_up
            switch ($request->status) {
                case 'picked_up':
                    $delivery->picked_up_at = now();
                    break;
                case 'in_transit':
                    $delivery->in_transit_at = now();
                    break;
                case 'out_for_delivery':
                    $delivery->out_for_delivery_at = now();
                    break;
                case 'delivered':
                    $delivery->delivered_at = now();
                    $delivery->order->update(['status' => 'delivered']);
                    break;
                case 'failed':
                    $delivery->failed_at = now();
                    break;
            }

            $delivery->save();

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
                'data' => $delivery->load(['deliveryUpdates']),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update delivery status: ' . $e->getMessage(),
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
                'message' => 'Unauthorized to update this delivery',
            ], 403);
        }

        DB::beginTransaction();
        try {
            if ($request->hasFile('delivery_proof')) {
                $path = $request->file('delivery_proof')->store('delivery-proofs', 'public');
                $delivery->delivery_proof_image = $path;
            }

            $delivery->recipient_name = $request->recipient_name;
            $delivery->recipient_phone = $request->recipient_phone;
            $delivery->status = 'delivered';
            $delivery->delivered_at = now();

            $delivery->order->update(['status' => 'delivered']);
            $delivery->save();

            DeliveryUpdate::create([
                'delivery_id' => $delivery->id,
                'user_id' => $request->user()->id,
                'status' => 'delivered',
                'notes' => 'Delivery completed with proof uploaded',
            ]);

            DB::commit();

            if ($delivery->delivery_proof_image) {
                $delivery->delivery_proof_image = url('storage/' . ltrim($delivery->delivery_proof_image, '/'));
            }

            return response()->json([
                'success' => true,
                'message' => 'Delivery proof uploaded successfully',
                'data' => $delivery,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload delivery proof: ' . $e->getMessage(),
            ], 500);
        }
    }

    // Assign platform courier (admin only)
    public function assignCourier(Request $request, Delivery $delivery)
    {
        $request->validate([
            'platform_courier_id' => 'required|exists:users,id',
            'driver_name' => 'nullable|string',
            'driver_phone' => 'nullable|string',
            'vehicle_type' => 'nullable|string',
            'vehicle_number' => 'nullable|string',
        ]);

        // FIX: use hasRole() consistent with the rest of the codebase, not type check
        if (!$request->user()->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Only admins can assign couriers',
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
            'data' => $delivery->load('platformCourier'),
        ]);
    }

    // Get delivery tracking updates
    public function getTrackingUpdates(Request $request, Delivery $delivery)
    {
        // FIX: added authorisation check — previously any authenticated user could
        // fetch tracking data for any delivery by guessing the ID
        $user = $request->user();
        $order = $delivery->order;

        $canView = $user->hasRole('admin')
            || $user->type === 'admin'
            || ($order && $order->seller_id === $user->id)
            || ($order && $order->buyer_id === $user->id)
            || $delivery->platform_courier_id === $user->id;

        if (!$canView) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to view this delivery',
            ], 403);
        }

        $updates = $delivery->deliveryUpdates()
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'delivery' => $delivery,
                'updates' => $updates,
            ],
        ]);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * FIX: extracted the duplicated image-formatting loop into one private method
     * shared by index() and chooseDeliveryMethod().
     */
    private function formatDeliveryImages(Delivery $delivery): Delivery
    {
        if (!$delivery->order || !$delivery->order->items) {
            return $delivery;
        }

        foreach ($delivery->order->items as $item) {
            $productData = $item->product_data;
            if (isset($productData['images']) && is_array($productData['images'])) {
                $productData['images'] = $this->formatImages($productData['images']);
                $item->product_data = $productData;
            }

            if ($item->product && $item->product->images) {
                $item->product->images = $this->formatImages($item->product->images);
            }
        }

        return $delivery;
    }

    /**
     * Format an array of image values to full URLs.
     */
    protected function formatImages(array $images): array
    {
        if (empty($images)) {
            return [];
        }

        return array_values(array_map(function ($image, $index) {
            if (is_string($image)) {
                $url = str_starts_with($image, 'http')
                    ? $image
                    : url('storage/' . ltrim($image, '/'));

                return ['url' => $url, 'angle' => 'default', 'is_primary' => $index === 0];
            }

            $url = $image['url'] ?? $image['path'] ?? '';
            if (!str_starts_with($url, 'http')) {
                $url = url('storage/' . ltrim($url, '/'));
            }

            return [
                'url' => $url,
                'angle' => $image['angle'] ?? 'default',
                'is_primary' => $image['is_primary'] ?? ($index === 0),
            ];
        }, $images, array_keys($images)));
    }

    /**
     * Calculate total package weight from order items.
     */
    private function calculateOrderWeight(Order $order): float
    {
        $total = 0;

        if ($order->items) {
            foreach ($order->items as $item) {
                $weight = $item->product?->weight_kg ?? 1;
                $total += $weight * $item->quantity;
            }
        }

        return $total > 0 ? $total : 5.0;
    }
}
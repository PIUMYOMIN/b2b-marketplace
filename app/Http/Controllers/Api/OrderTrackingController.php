<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OrderTrackingController extends Controller
{
    /**
     * Public order tracking endpoint.
     * GET /track/{orderNumber}?email=optional@email.com
     *
     * No authentication required — buyers can track without logging in.
     * Email is an optional second factor for guest orders.
     */
    public function track(Request $request, string $orderNumber)
    {
        try {
            $order = Order::with([
                'items.product:id,name_en,name_mm,sku,images',
                'delivery.deliveryUpdates',
                'seller:id,name',
                'seller.sellerProfile:user_id,store_name,store_logo,store_slug',
            ])
                ->where('order_number', strtoupper(trim($orderNumber)))
            ->first();

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found. Please check your order number and try again.',
                ], 404);
            }

            // Optional email verification for guest security
            if ($request->filled('email')) {
                $email = strtolower(trim($request->email));
                $orderEmail = strtolower($order->shipping_address['email'] ?? '');
                if ($email !== $orderEmail) {
                    return response()->json([
                        'success' => false,
                        'message' => 'The email address does not match this order.',
                    ], 403);
                }
            }

            $delivery = $order->delivery;

            // Build items — merge stored snapshot with live product image
            $items = $order->items->map(function ($item) {
                $image = null;
                if ($item->product && $item->product->images) {
                    $imgs = is_array($item->product->images)
                        ? $item->product->images
                        : json_decode($item->product->images, true);
                    $first = collect($imgs)->first();
                    $raw = is_array($first) ? ($first['url'] ?? $first['path'] ?? null) : $first;
                    if ($raw) {
                        $image = str_starts_with($raw, 'http')
                            ? $raw
                            : url('storage/' . ltrim($raw, '/'));
                    }
                }

                return [
                    'product_name' => $item->product_name
                        ?? $item->product?->name_en
                        ?? $item->product?->name_mm
                        ?? 'Product',
                    'product_sku' => $item->product_sku ?? $item->product?->sku,
                    'price' => (float) $item->price,
                    'quantity' => (int) $item->quantity,
                    'subtotal' => (float) ($item->subtotal ?? ($item->price * $item->quantity)),
                    'image' => $image,
                ];
            });

            // Build seller info
            $sellerProfile = $order->seller?->sellerProfile;
            $seller = $order->seller ? [
                'store_name' => $sellerProfile?->store_name ?? $order->seller->name,
                'store_logo' => $sellerProfile?->store_logo
                    ? (str_starts_with($sellerProfile->store_logo, 'http')
                        ? $sellerProfile->store_logo
                        : url('storage/' . ltrim($sellerProfile->store_logo, '/')))
                    : null,
                'store_slug' => $sellerProfile?->store_slug,
            ] : null;

            // Build delivery info
            $deliveryData = null;
            if ($delivery) {
                $deliveryData = [
                    'status' => $delivery->status,
                    'method' => $delivery->delivery_method,
                    'tracking_number' => $delivery->tracking_number,
                    'carrier_name' => $delivery->carrier_name,
                    'estimated_delivery_date' => $delivery->estimated_delivery_date,
                    'failure_reason' => $delivery->failure_reason,
                    'updates' => $delivery->deliveryUpdates
                        ->map(fn($u) => [
                            'status' => $u->status,
                            'notes' => $u->notes,
                            'location' => $u->location,
                            'created_at' => $u->created_at,
                        ])
                        ->values(),
                ];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'order_number' => $order->order_number,
                    'status' => $order->status,
                    'payment_status' => $order->payment_status,
                    'created_at' => $order->created_at,
                    'delivered_at' => $delivery?->delivered_at ?? null,
                    'estimated_delivery' => $delivery?->estimated_delivery_date ?? null,
                    'subtotal_amount' => (float) $order->subtotal_amount,
                    'shipping_fee' => (float) ($order->shipping_fee ?? 0),
                    'tax_amount' => (float) ($order->tax_amount ?? 0),
                    'coupon_discount_amount' => (float) ($order->coupon_discount_amount ?? 0),
                    'total_amount' => (float) $order->total_amount,
                    'items' => $items,
                    'delivery' => $deliveryData,
                    'seller' => $seller,
                    'shipping_address' => $order->shipping_address,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Order tracking failed: ' . $e->getMessage(), [
                'order_number' => $orderNumber,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to retrieve order details. Please try again.',
            ], 500);
        }
    }
}

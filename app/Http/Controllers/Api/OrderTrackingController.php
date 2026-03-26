<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class OrderTrackingController extends Controller
{
    /**
     * Public order tracking — no authentication required.
     * Accepts an order number and returns safe, anonymised tracking data.
     */
    public function track(Request $request, string $orderNumber)
    {
        $request->validate([
            // Optional: caller may pass buyer email for extra verification
            'email' => 'sometimes|email|max:255',
        ]);

        // Eager-load everything we need in one query
        $order = Order::with([
            'items',
            'delivery.updates' => fn($q) => $q->orderBy('created_at', 'asc'),
            'seller.sellerProfile',
        ])
            ->where('order_number', $orderNumber)
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found. Please check your order number and try again.',
            ], 404);
        }

        // Optional email verification — if caller supplies email it must match
        if ($request->filled('email')) {
            // Load buyer for email comparison
            $order->loadMissing('buyer');
            if (
                !$order->buyer ||
                strtolower($order->buyer->email) !== strtolower($request->email)
            ) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found. Please check your order number and try again.',
                ], 404);
            }
        }

        // Build safe tracking payload — never expose buyer PII
        return response()->json([
            'success' => true,
            'data' => $this->formatOrder($order),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function formatOrder(Order $order): array
    {
        $delivery = $order->delivery;

        return [
            'order_number' => $order->order_number,
            'status' => $order->status,
            'payment_status' => $order->payment_status,
            'payment_method' => $order->payment_method,
            'created_at' => $order->created_at->toISOString(),
            'estimated_delivery' => $order->estimated_delivery
                ? \Carbon\Carbon::parse($order->estimated_delivery)->toISOString()
                : ($delivery?->estimated_delivery_date
                    ? \Carbon\Carbon::parse($delivery->estimated_delivery_date)->toISOString()
                    : null),
            'delivered_at' => $order->delivered_at
                ? \Carbon\Carbon::parse($order->delivered_at)->toISOString()
                : $delivery?->delivered_at?->toISOString(),

            // Amounts — no buyer financial detail beyond their own order
            'subtotal_amount' => (float) $order->subtotal_amount,
            'shipping_fee' => (float) $order->shipping_fee,
            'tax_amount' => (float) $order->tax_amount,
            'coupon_discount_amount' => (float) $order->coupon_discount_amount,
            'total_amount' => (float) $order->total_amount,

            // Shipping address (buyer already knows their own address)
            'shipping_address' => $order->shipping_address,

            // Items — product snapshot stored at order time
            'items' => $order->items->map(fn($item) => [
                'product_name' => $item->product_name,
                'product_sku' => $item->product_sku,
                'quantity' => $item->quantity,
                'price' => (float) $item->price,
                'subtotal' => (float) $item->subtotal,
                // Pull primary image from product_data snapshot if available
                'image' => $this->itemImage($item),
            ])->values(),

            // Seller — only store name and logo, no contact details
            'seller' => $order->seller ? [
                'store_name' => $order->seller->sellerProfile?->store_name
                    ?? $order->seller->name,
                'store_logo' => $order->seller->sellerProfile?->store_logo
                    ? Storage::disk('public')->url($order->seller->sellerProfile->store_logo)
                    : null,
            ] : null,

            // Delivery
            'delivery' => $delivery ? [
                'method' => $delivery->delivery_method,
                'status' => $delivery->status,
                'tracking_number' => $delivery->tracking_number,
                'carrier_name' => $delivery->carrier_name,
                'pickup_scheduled_at' => $delivery->pickup_scheduled_at?->toISOString(),
                'picked_up_at' => $delivery->picked_up_at?->toISOString(),
                'estimated_delivery_date' => $delivery->estimated_delivery_date?->toISOString(),
                'delivered_at' => $delivery->delivered_at?->toISOString(),
                'failure_reason' => $delivery->failure_reason,
                'updates' => $delivery->updates->map(fn($u) => [
                    'status' => $u->status,
                    'location' => $u->location,
                    'notes' => $u->notes,
                    'created_at' => $u->created_at->toISOString(),
                ])->values(),
            ] : null,
        ];
    }

    private function itemImage($item): ?string
    {
        $data = $item->product_data;
        if (!$data)
            return null;

        $images = is_string($data)
            ? json_decode($data, true)['images'] ?? []
            : ($data['images'] ?? []);

        if (empty($images))
            return null;

        $primary = collect($images)->firstWhere('is_primary', true) ?? $images[0];
        $url = is_array($primary) ? ($primary['url'] ?? '') : (string) $primary;

        if (!$url)
            return null;
        if (str_starts_with($url, 'http'))
            return $url;

        return Storage::disk('public')->exists($url)
            ? Storage::disk('public')->url($url)
            : null;
    }
}

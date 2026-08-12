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
                'buyer:id,email',
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
                $orderEmail = strtolower(
                    $order->buyer?->email
                    ?? $order->shipping_address['email']
                    ?? ''
                );
                if (!$orderEmail || $email !== $orderEmail) {
                    return response()->json([
                        'success' => false,
                        'message' => 'The email address does not match this order.',
                    ], 403);
                }
            }

            $delivery = $order->delivery;
            $deliveryLocation = $this->publicDeliveryLocation($order->shipping_address);

            // Build items — merge stored snapshot with live product image
            $items = $order->items->map(function ($item) {
                $image = null;
                $imageSources = [];

                if ($item->product && $item->product->images) {
                    $imageSources[] = $item->product->images;
                }

                $productData = is_array($item->product_data)
                    ? $item->product_data
                    : (is_string($item->product_data)
                        ? json_decode($item->product_data, true)
                        : []);

                if (is_array($productData)) {
                    if (!empty($productData['images'])) {
                        $imageSources[] = $productData['images'];
                    }
                    if (!empty($productData['image'])) {
                        $imageSources[] = $productData['image'];
                    }
                }

                foreach ($imageSources as $source) {
                    $imgs = is_array($source)
                        ? $source
                        : (is_string($source) ? [$source] : []);
                    $first = collect($imgs)->first();
                    $raw = is_array($first)
                        ? ($first['url'] ?? $first['path'] ?? $first['image_url'] ?? null)
                        : $first;
                    if (!$raw || !is_string($raw)) {
                        continue;
                    }

                    $image = $this->resolvePublicImageUrl($raw);
                    if ($image) {
                        break;
                    }
                }

                // Prefer variant snapshot image when present.
                if (!$image && is_array($productData)) {
                    $variantImage = $productData['variant_image']
                        ?? $productData['image_url']
                        ?? null;
                    if (is_string($variantImage) && $variantImage !== '') {
                        $image = $this->resolvePublicImageUrl($variantImage);
                    }
                }

                return [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product_name
                        ?? $item->product?->name_en
                        ?? $item->product?->name_mm
                        ?? 'Product',
                    'product_sku' => $item->product_sku ?? $item->product?->sku,
                    'price' => (float) $item->price,
                    'quantity' => (int) $item->quantity,
                    'subtotal' => (float) ($item->subtotal ?? ($item->price * $item->quantity)),
                    'image' => $image,
                    'image_url' => $image,
                    'product_data' => is_array($productData) ? $productData : null,
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
                    'delivery_location' => $deliveryLocation,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Order tracking failed: ' . $e->getMessage(), [
                'order_number' => $orderNumber,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to retrieve order details. Please try again.',
            ], 500);
        }
    }

    private function publicDeliveryLocation(array|string|null $shippingAddress): ?string
    {
        if (is_string($shippingAddress)) {
            $decoded = json_decode($shippingAddress, true);
            $shippingAddress = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($shippingAddress)) {
            return null;
        }

        $parts = array_filter([
            $shippingAddress['city'] ?? null,
            $shippingAddress['township'] ?? null,
        ]);

        return $parts ? implode(', ', $parts) : null;
    }

    private function resolvePublicImageUrl(string $raw): ?string
    {
        $value = trim($raw);
        if ($value === '') {
            return null;
        }

        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://') || str_starts_with($value, 'data:')) {
            return $value;
        }

        // Avoid doubling /storage/storage/... when path already includes it.
        $relative = ltrim($value, '/');
        if (str_starts_with($relative, 'storage/')) {
            $relative = substr($relative, strlen('storage/'));
        }
        if (str_starts_with($relative, 'public/')) {
            $relative = substr($relative, strlen('public/'));
        }

        return url('storage/' . ltrim($relative, '/'));
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CommissionRateResolver;
use App\Notifications\OrderPlaced;
use App\Notifications\NewOrderForSeller;
use App\Models\User as UserModel;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\OrderItem;
use App\Models\Delivery;
use App\Models\DeliveryUpdate;
use App\Models\Commission;
use App\Models\CommissionRule;
use App\Models\SellerProfile;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use App\Mail\OrderOtpMail;

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

    // ── OTP ────────────────────────────────────────────────────────────────────

    /**
     * POST /orders/request-otp
     *
     * Validates the cart + shipping fields, calculates the total,
     * then emails a 6-digit OTP to the authenticated buyer.
     * The OTP is stored temporarily on a draft order (status=pending, otp_verified=false).
     * The actual order creation is completed in store() after OTP verification.
     */
    public function requestOtp(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'items'                        => 'required|array|min:1',
            'items.*.product_id'           => 'required|exists:products,id',
            'items.*.quantity'             => 'required|integer|min:1',
            'shipping_address'             => 'required|array',
            'shipping_address.full_name'   => 'required|string',
            'shipping_address.phone'       => 'required|string',
            'shipping_address.address'     => 'required|string',
            'payment_method'               => 'required|in:kbz_pay,wave_pay,cb_pay,aya_pay,mmqr,cash_on_delivery',
        ]);

        // Quick stock check before bothering to send an email
        foreach ($request->items as $item) {
            $product = Product::findOrFail($item['product_id']);
            if (!$product->is_active) {
                return response()->json(['success' => false, 'message' => "Product \"{$product->name}\" is no longer available."], 422);
            }
            if ($product->quantity < $item['quantity']) {
                return response()->json(['success' => false, 'message' => "Insufficient stock for \"{$product->name}\"."], 422);
            }
        }

        // Calculate estimated total to show in the email.
        // Use the seller's delivery zone fee where available, falling back to 5,000 MMK.
        $subtotal = collect($request->items)->sum(function ($item) {
            $product = Product::find($item['product_id']);
            return $product ? $product->price * $item['quantity'] : 0;
        });

        // Resolve shipping fee from the seller's delivery zones (best-effort — items may
        // span multiple sellers, so we use the first seller found as an estimate).
        $addr            = $request->shipping_address;
        $firstSellerId   = Product::find($request->items[0]['product_id'])?->seller_id;
        $sellerProfile   = $firstSellerId ? SellerProfile::where('user_id', $firstSellerId)->first() : null;
        $matchedZone     = $sellerProfile?->activeDeliveryAreas()
            ->byLocation($addr['country'] ?? 'Myanmar', $addr['state'] ?? null, $addr['city'] ?? null)
            ->orderByDesc('sort_order')
            ->first();
        $estimatedShipping = $matchedZone ? $matchedZone->getShippingFeeForOrder($subtotal) : 5000;

        $total = $subtotal + $estimatedShipping + ($subtotal * 0.05); // shipping + 5% tax
        $formattedTotal = number_format($total, 0) . ' MMK';

        // Generate a 6-digit OTP
        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $ttl = now()->addMinutes(10);

        // Store OTP in the Laravel Cache (database driver, table: cache).
        //
        // WHY NOT session(): This API runs under Laravel 12's `api` middleware
        // group defined in bootstrap/app.php. That group does NOT include
        // StartSession, so session() always returns a fresh empty store on
        // every request — the OTP written in requestOtp() is invisible to
        // verifyOtp(), producing "No OTP found" every time. Cache persists
        // across stateless Bearer-token requests because it's backed by the
        // database, not the HTTP session.
        Cache::put("order_otp_{$user->id}",          $otp,                 $ttl);
        Cache::put("order_otp_expires_{$user->id}",  $ttl->toISOString(),  $ttl);

        // Send synchronously — OTP is time-sensitive, the user is waiting.
        Mail::to($user->email)
            ->send(new OrderOtpMail($otp, $user->name, $formattedTotal));

        return response()->json([
            'success'    => true,
            'message'    => "A 6-digit confirmation code has been sent to {$user->email}.",
            'email_hint' => $this->maskEmail($user->email),
            'expires_in' => 600, // seconds
        ]);
    }

    /**
     * POST /orders/verify-otp
     *
     * Verifies the OTP submitted by the buyer.
     * Returns success so the frontend knows it can proceed to call POST /orders.
     */
    public function verifyOtp(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'otp' => 'required|string|size:6',
        ]);

        $storedOtp     = Cache::get("order_otp_{$user->id}");
        $storedExpires = Cache::get("order_otp_expires_{$user->id}");

        if (!$storedOtp || !$storedExpires) {
            return response()->json(['success' => false, 'message' => __('messages.orders.otp_not_found')], 422);
        }

        if (now()->gt(\Carbon\Carbon::parse($storedExpires))) {
            Cache::forget("order_otp_{$user->id}");
            Cache::forget("order_otp_expires_{$user->id}");
            return response()->json(['success' => false, 'message' => __('messages.orders.otp_expired')], 422);
        }

        if ($request->otp !== $storedOtp) {
            return response()->json(['success' => false, 'message' => __('messages.orders.otp_incorrect')], 422);
        }

        // Mark verified in Cache with a 5-minute window for the order POST to arrive.
        Cache::put("order_otp_verified_{$user->id}", true, now()->addMinutes(5));
        Cache::forget("order_otp_{$user->id}");
        Cache::forget("order_otp_expires_{$user->id}");

        return response()->json([
            'success' => true,
            'message' => __('messages.orders.otp_verified'),
        ]);
    }

    /** Mask an email address for display: hello@example.com → h***o@e***.com */
    private function maskEmail(string $email): string
    {
        [$local, $domain] = explode('@', $email, 2);
        $maskedLocal  = substr($local, 0, 1) . str_repeat('*', max(strlen($local) - 2, 1)) . substr($local, -1);
        [$domainName, $tld] = array_pad(explode('.', $domain, 2), 2, '');
        $maskedDomain = substr($domainName, 0, 1) . str_repeat('*', max(strlen($domainName) - 1, 1));
        return "{$maskedLocal}@{$maskedDomain}.{$tld}";
    }

    public function store(Request $request)
    {
        DB::beginTransaction();

        try {
            $user = Auth::user();

            // ── OTP gate ────────────────────────────────────────────────────
            $otpVerified = Cache::get("order_otp_verified_{$user->id}");
            if (!$otpVerified) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.orders.otp_required'),
                    'code'    => 'OTP_REQUIRED',
                ], 403);
            }
            // Consume the verification flag — one OTP = one order
            Cache::forget("order_otp_verified_{$user->id}");

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

                if (!$coupon) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Coupon code not found',
                    ], 422);
                }

                $validationError = $coupon->getValidationError();
                if ($validationError) {
                    return response()->json([
                        'success' => false,
                        'message' => $validationError,
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

                // ── Resolve shipping fee from seller's delivery zones ──────────────
                // Matches the buyer's destination (country → state → city, most specific
                // zone wins via sort_order DESC). Falls back to 5,000 MMK if the seller
                // has not configured any delivery zones or none match the destination.
                $addr          = $request->shipping_address;
                $sellerProfile = SellerProfile::where('user_id', $sellerId)->first();
                $matchedZone   = $sellerProfile?->activeDeliveryAreas()
                    ->byLocation(
                        $addr['country'] ?? 'Myanmar',
                        $addr['state']   ?? null,
                        $addr['city']    ?? null
                    )
                    ->orderByDesc('sort_order')
                    ->first();
                $sellerShippingFee = $matchedZone
                    ? $matchedZone->getShippingFeeForOrder($sellerSubtotal)
                    : 5000;
                $sellerTax = $sellerSubtotal * 0.05;

                // Distribute coupon discount proportionally across seller orders.
                // e.g. if this seller's products are 60% of the cart, they absorb 60% of the discount.
                $sellerCouponDiscount = $grandSubtotal > 0
                    ? round($totalCouponDiscount * ($sellerSubtotal / $grandSubtotal), 2)
                    : 0;

                $sellerTotal = max(0, $sellerSubtotal + $sellerShippingFee + $sellerTax - $sellerCouponDiscount);

                // Generate order number
                $orderNumber = 'ORD-' . date('Ymd') . '-' . str_pad(Order::count() + 1, 5, '0', STR_PAD_LEFT);

                // Resolve commission rate via priority chain:
                // account_level (tier) → business_type → category → default (5%)
                // Pass a stub Order with seller_id so the resolver can load the seller profile.
                // Items are resolved from $sellerItems directly inside the resolver stub.
                $stubOrder = new Order(['seller_id' => $sellerId]);
                $resolved = app(CommissionRateResolver::class)->resolveForSeller($sellerId, $sellerItems);
                $commissionRate = $resolved['rate'];
                $taxRate = 0.05;
                $commissionAmount = round($sellerSubtotal * $commissionRate, 2);
                $platformRevenue = $commissionAmount + $sellerTax;
                $sellerPayout = $sellerSubtotal - $commissionAmount;

                // Create order
                $order = Order::create([
                    'order_number' => $orderNumber,
                    'buyer_id' => $user->id,
                    'seller_id' => $sellerId,
                    'total_amount' => $sellerTotal,
                    'subtotal_amount' => $sellerSubtotal,
                    'shipping_fee' => $sellerShippingFee,
                    'tax_amount' => $sellerTax,
                    'tax_rate' => $taxRate,
                    'status' => self::STATUS_PENDING,
                    'payment_method' => $request->payment_method,
                    'payment_status' => self::PAYMENT_STATUS_PENDING,
                    'shipping_address' => $request->shipping_address,
                    'order_notes' => $request->notes,
                    'commission_rate' => $commissionRate,
                    'commission_amount' => $commissionAmount,
                    // Coupon columns (populated when a coupon was applied)
                    'coupon_id' => $coupon?->id,
                    'coupon_code' => $coupon?->code,
                    'coupon_discount_amount' => $sellerCouponDiscount,
                ]);

                // Record commission in commissions table for admin revenue tracking
                Commission::create([
                    'order_id' => $order->id,
                    'seller_id' => $sellerId,
                    'amount' => $commissionAmount,
                    'commission_rate' => $commissionRate,
                    'tax_amount' => $sellerTax,
                    'tax_rate' => $taxRate,
                    'platform_revenue' => $platformRevenue,
                    'seller_payout' => $sellerPayout,
                    'status' => 'pending',
                    'due_date' => now()->addDays(30),
                    'notes' => "Order {$orderNumber}: {$commissionRate}% commission + 5% tax (rule: {$resolved['rule_type']})",
                    'commission_rule_id' => $resolved['rule_id'],
                ]);

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

                // ── Fire notifications ──────────────────────────────────────
                try {
                    $order->buyer->notify(new OrderPlaced($order));
                    $sellerUser = UserModel::find($sellerId);
                    if ($sellerUser)
                        $sellerUser->notify(new NewOrderForSeller($order));
                } catch (\Exception $notifEx) {
                    \Log::warning('Order notification failed: ' . $notifEx->getMessage());
                }
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
                'message' => __('messages.orders.view_unauthorized')
            ], 403);
        }

        if ($user->hasRole('buyer') && $order->buyer_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => __('messages.orders.view_unauthorized')
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
            return response()->json(['success' => false, 'message' => __('messages.orders.cancel_unauthorized')], 403);
        }

        if ($user->hasRole('seller') && (int) $order->seller_id !== (int) $user->id) {
            return response()->json(['success' => false, 'message' => __('messages.orders.cancel_unauthorized')], 403);
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

            $order->load('items.product');

            foreach ($order->items as $item) {
                if ($item->product) {
                    $item->product->increment('quantity', $item->quantity);
                }
            }

            if ($order->coupon_id) {
                $usage = CouponUsage::where('coupon_id', $order->coupon_id)
                    ->where('user_id', $order->buyer_id)
                    ->where('order_id', $order->id)
                    ->first();

                if ($usage) {
                    $usage->delete();
                    Coupon::where('id', $order->coupon_id)
                        ->where('used_count', '>', 0)
                        ->decrement('used_count');
                }
            }

            // FIX: cancel the associated delivery record so it doesn't remain
            // in-progress while the order itself is cancelled.
            $delivery = Delivery::where('order_id', $order->id)->first();
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
            'status'       => self::STATUS_DELIVERED,
            'delivered_at' => now(),
        ]);

        // Mark the commission record as collected — platform revenue is realised
        // on delivery, not on order placement.
        Commission::where('order_id', $order->id)
            ->where('status', 'pending')
            ->update([
                'status'       => 'collected',
                'collected_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => __('messages.orders.delivery_confirmed')
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

    /**
     * GET /orders/checkout-fees?country=Myanmar&state=Yangon&city=Yangon
     *
     * Returns the live tax rate (fixed 5%) and the shipping fee resolved from
     * the seller's delivery zones for the buyer's destination.
     *
     * For multi-seller carts, returns per-seller shipping fees so the frontend
     * can display a breakdown. Falls back to 5,000 MMK per seller if no
     * matching delivery zone is found.
     *
     * Also returns the commission rate (for admin/display only — not charged
     * to the buyer; collected from the seller after delivery).
     */
    public function checkoutFees(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $user    = $request->user();
            $country = $request->input('country', 'Myanmar');
            $state   = $request->input('state');
            $city    = $request->input('city');

            $cartItems = Cart::where('user_id', $user->id)
                ->with('product:id,seller_id,category_id,price')
                ->get();

            $DEFAULT_SHIPPING = 5000;
            $TAX_RATE         = 0.05;

            $sellerShippingFees = [];
            $totalShipping      = 0;

            if ($cartItems->isNotEmpty()) {
                $sellerIds = $cartItems->pluck('product.seller_id')->filter()->unique();

                foreach ($sellerIds as $sellerId) {
                    $sellerItems    = $cartItems->filter(fn($c) => $c->product?->seller_id === $sellerId);
                    $sellerSubtotal = $sellerItems->sum(fn($c) => ($c->product?->price ?? 0) * $c->quantity);

                    $profile     = SellerProfile::where('user_id', $sellerId)->first();
                    $matchedZone = $profile?->activeDeliveryAreas()
                        ->byLocation($country, $state, $city)
                        ->orderByDesc('sort_order')
                        ->first();

                    $fee = $matchedZone
                        ? $matchedZone->getShippingFeeForOrder($sellerSubtotal)
                        : $DEFAULT_SHIPPING;

                    $sellerShippingFees[$sellerId] = $fee;
                    $totalShipping += $fee;
                }
            } else {
                $totalShipping = $DEFAULT_SHIPPING;
            }

            return response()->json([
                'success' => true,
                'data'    => [
                    'tax_rate'           => $TAX_RATE,
                    'tax_pct'            => 5.0,
                    'shipping_fee'       => $totalShipping,              // total across all sellers
                    'seller_shipping'    => $sellerShippingFees,         // per seller_id breakdown
                    'default_shipping'   => $DEFAULT_SHIPPING,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('checkoutFees failed: ' . $e->getMessage());

            return response()->json([
                'success' => true,
                'data'    => [
                    'tax_rate'         => 0.05,
                    'tax_pct'          => 5.0,
                    'shipping_fee'     => 5000,
                    'seller_shipping'  => [],
                    'default_shipping' => 5000,
                ],
            ]);
        }
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CommissionRateResolver;
use App\Services\Payment\PaymentService;
use App\Notifications\OrderPlaced;
use App\Notifications\OrderDeliveredThankYou;
use App\Notifications\OrderStatusChanged;
use App\Notifications\DeliveryStatusUpdated;
use App\Notifications\NewOrderForSeller;
use App\Models\User as UserModel;
use App\Models\CodCommissionInvoice;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Cart;
use App\Models\Order;
use App\Models\SellerOrder;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\OrderItem;
use App\Models\Delivery;
use App\Models\DeliveryUpdate;
use App\Models\Commission;
use App\Models\CommissionRule;
use App\Models\SellerProfile;
use App\Models\SellerWallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use App\Mail\OrderOtpMail;
use Carbon\Carbon;

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
            $orders = Order::with([
                    'items',
                    'delivery.deliveryUpdates',
                    'buyer:id,name,email,phone',
                    'seller:id,name,email,phone',
                    'seller.sellerProfile:id,user_id,store_name,store_slug,store_logo',
                ])
                ->where('seller_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->get();
        } else if ($user->hasRole('buyer')) {
            // For buyers, show their own orders with seller store name
            $orders = Order::with([
                    'items',
                    'delivery.deliveryUpdates',
                    'buyer:id,name,email,phone',
                    'seller:id,name,email,phone',
                    'seller.sellerProfile:id,user_id,store_name,store_slug,store_logo',
                ])
                ->where('buyer_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->get();
        } else if ($this->isAdmin($user)) {
            // For admins, show all orders
            $orders = Order::with([
                    'items',
                    'delivery.deliveryUpdates',
                    'buyer:id,name,email,phone',
                    'seller:id,name,email,phone',
                    'seller.sellerProfile:id,user_id,store_name,store_slug,store_logo',
                ])
                ->orderBy('created_at', 'desc')
                ->get();
        } else {
            return response()->json([
                'success' => false,
                'message' => __('messages.orders.view_unauthorized'),
            ], 403);
        }

        $orders = $orders->map(fn (Order $order) => $this->formatOrderForList($order, $baseUrl));

        return response()->json([
            'success' => true,
            'data' => $orders
        ], 200, [], JSON_INVALID_UTF8_SUBSTITUTE);
    }

    private function formatOrderForList(Order $order, string $baseUrl): array
    {
        $storeName = $order->seller?->sellerProfile?->store_name ?? $order->seller?->name;

        return [
            'id' => $order->id,
            'order_number' => $this->jsonSafeString($order->order_number),
            'buyer_id' => $order->buyer_id,
            'seller_id' => $order->seller_id,
            'total_amount' => $order->total_amount,
            'subtotal_amount' => $order->subtotal_amount,
            'shipping_fee' => $order->shipping_fee,
            'tax_amount' => $order->tax_amount,
            'tax_rate' => $order->tax_rate,
            'status' => $order->status,
            'payment_method' => $order->payment_method,
            'payment_status' => $order->payment_status,
            'shipping_address' => $this->jsonSafeValue($order->shipping_address),
            'billing_address' => $this->jsonSafeValue($order->billing_address),
            'order_notes' => $this->jsonSafeString($order->order_notes),
            'tracking_number' => $this->jsonSafeString($order->tracking_number),
            'shipping_carrier' => $this->jsonSafeString($order->shipping_carrier),
            'estimated_delivery' => $order->estimated_delivery?->toIso8601String(),
            'commission_rate' => $order->commission_rate,
            'commission_amount' => $order->commission_amount,
            'coupon_id' => $order->coupon_id,
            'coupon_code' => $this->jsonSafeString($order->coupon_code),
            'coupon_discount_amount' => $order->coupon_discount_amount,
            'delivered_at' => $order->delivered_at?->toIso8601String(),
            'cancelled_at' => $order->cancelled_at?->toIso8601String(),
            'created_at' => $order->created_at?->toIso8601String(),
            'updated_at' => $order->updated_at?->toIso8601String(),
            'store_name' => $this->jsonSafeString($storeName),
            'buyer' => $this->formatOrderUser($order->buyer),
            'seller' => $this->formatOrderUser($order->seller, true),
            'items' => $order->items->map(fn (OrderItem $item) => $this->formatOrderItemForList($item, $baseUrl))->values(),
            'delivery' => $this->formatDeliveryForList($order->delivery),
        ];
    }

    /**
     * Compact delivery row for order list (seller / buyer / admin dashboards).
     */
    private function formatDeliveryForList(?Delivery $delivery): ?array
    {
        if (! $delivery) {
            return null;
        }

        return [
            'id' => $delivery->id,
            'order_id' => $delivery->order_id,
            'delivery_method' => $this->jsonSafeString($delivery->delivery_method),
            'status' => $this->jsonSafeString($delivery->status),
            'tracking_number' => $this->jsonSafeString($delivery->tracking_number),
            'carrier_name' => $this->jsonSafeString($delivery->carrier_name),
            'platform_delivery_fee' => $delivery->platform_delivery_fee,
            'delivery_fee_status' => $this->jsonSafeString($delivery->delivery_fee_status),
            'pickup_address' => $this->jsonSafeString($delivery->pickup_address),
            'delivery_address' => $this->jsonSafeString($delivery->delivery_address),
            'assigned_driver_name' => $this->jsonSafeString($delivery->assigned_driver_name),
            'assigned_driver_phone' => $this->jsonSafeString($delivery->assigned_driver_phone),
            'package_weight' => $delivery->package_weight,
            'estimated_delivery_date' => $delivery->estimated_delivery_date?->toIso8601String(),
            'delivered_at' => $delivery->delivered_at?->toIso8601String(),
            'delivery_updates' => $delivery->relationLoaded('deliveryUpdates')
                ? $delivery->deliveryUpdates->map(fn (DeliveryUpdate $update) => [
                    'id' => $update->id,
                    'status' => $this->jsonSafeString($update->status),
                    'notes' => $this->jsonSafeString($update->notes),
                    'location' => $this->jsonSafeString($update->location),
                    'created_at' => $update->created_at?->toIso8601String(),
                ])->values()
                : [],
        ];
    }

    private function formatOrderUser(?UserModel $user, bool $includeStore = false): ?array
    {
        if (!$user) {
            return null;
        }

        $data = [
            'id' => $user->id,
            'name' => $this->jsonSafeString($user->name),
            'email' => $this->jsonSafeString($user->email),
            'phone' => $this->jsonSafeString($user->phone),
        ];

        if ($includeStore) {
            $data['seller_profile'] = $user->sellerProfile ? [
                'id' => $user->sellerProfile->id,
                'store_name' => $this->jsonSafeString($user->sellerProfile->store_name),
                'store_slug' => $this->jsonSafeString($user->sellerProfile->store_slug),
                'store_logo' => $this->jsonSafeString($user->sellerProfile->store_logo),
            ] : null;
        }

        return $data;
    }

    private function formatOrderItemForList(OrderItem $item, string $baseUrl): array
    {
        $productData = is_array($item->product_data) ? $item->product_data : [];
        $productData = $this->normalizeProductDataImageUrls($productData, $baseUrl);

        return [
            'id' => $item->id,
            'order_id' => $item->order_id,
            'product_id' => $item->product_id,
            'variant_id' => $item->variant_id,
            'product_name' => $this->jsonSafeString($item->product_name),
            'product_sku' => $this->jsonSafeString($item->product_sku),
            'variant_sku' => $this->jsonSafeString($item->variant_sku),
            'selected_options' => $this->jsonSafeValue($item->selected_options),
            'quantity_unit' => $this->jsonSafeString($item->quantity_unit),
            'price' => $item->price,
            'quantity' => $item->quantity,
            'subtotal' => $item->subtotal,
            'product_data' => $this->jsonSafeValue($productData),
            'created_at' => $item->created_at?->toIso8601String(),
            'updated_at' => $item->updated_at?->toIso8601String(),
        ];
    }

    private function normalizeProductDataImageUrls(array $productData, string $baseUrl): array
    {
        if (isset($productData['images']) && is_array($productData['images'])) {
            foreach ($productData['images'] as &$image) {
                if (is_array($image) && !empty($image['url']) && !str_starts_with($image['url'], 'http')) {
                    $image['url'] = $baseUrl . ltrim($image['url'], '/');
                } elseif (is_string($image) && !str_starts_with($image, 'http')) {
                    $image = $baseUrl . ltrim($image, '/');
                }
            }
            unset($image);
        }

        if (!empty($productData['image']) && is_string($productData['image']) && !str_starts_with($productData['image'], 'http')) {
            $productData['image'] = $baseUrl . ltrim($productData['image'], '/');
        }

        return $productData;
    }

    private function jsonSafeValue(mixed $value): mixed
    {
        if (is_string($value)) {
            return $this->jsonSafeString($value);
        }

        if (is_array($value)) {
            return array_map(fn ($item) => $this->jsonSafeValue($item), $value);
        }

        return $value;
    }

    private function jsonSafeString(?string $value): ?string
    {
        if ($value === null || mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        $converted = @iconv('UTF-8', 'UTF-8//IGNORE', $value);

        return $converted === false ? null : $converted;
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
            'items.*.variant_id'           => 'nullable|exists:product_variants,id',
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
                return response()->json(['success' => false, 'message' => "Product \"{$product->name_en}\" is no longer available."], 422);
            }
            if (!empty($item['variant_id'])) {
                $variant = $product->variants()->where('id', $item['variant_id'])->where('is_active', true)->first();
                if (!$variant || ($product->product_type === 'physical' && $variant->quantity < $item['quantity'])) {
                    return response()->json(['success' => false, 'message' => "Insufficient stock for \"{$product->name_en}\"."], 422);
                }
                // MOQ + step validation (variant-level)
                if ($error = $variant->validateMoqStep($item['quantity'])) {
                    return response()->json(['success' => false, 'message' => $error], 422);
                }
            } elseif ($product->product_type === 'physical') {
                if ((float) ($product->quantity ?? 0) < (float) $item['quantity']) {
                    return response()->json(['success' => false, 'message' => "Insufficient stock for \"{$product->name_en}\"."], 422);
                }

                // MOQ + step validation (product-level, no variant)
                if ($error = $product->validateMoqStep($item['quantity'])) {
                    return response()->json(['success' => false, 'message' => $error], 422);
                }
            } else {
                // MOQ + step validation (product-level, no variant)
                if ($error = $product->validateMoqStep($item['quantity'])) {
                    return response()->json(['success' => false, 'message' => $error], 422);
                }
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
            ->byLocation(
                $addr['country'] ?? 'Myanmar',
                $addr['state'] ?? null,
                $addr['city'] ?? null,
                $addr['township'] ?? null
            )
            ->orderByDesc('sort_order')
            ->first();
        $estimatedShipping = $matchedZone ? $matchedZone->getShippingFeeForOrder($subtotal) : 8000;

        $total = $subtotal + $estimatedShipping + ($subtotal * 0.00); // shipping + 5% tax
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

            // ── Idempotency — prevent double-submit ───────────────────────
            // Frontend sends X-Idempotency-Key (UUID) with every order POST.
            // We lock on it for 30 minutes — a repeat request with the same key
            // returns the already-created order rather than creating a second one.
            $idempKey = $request->header('X-Idempotency-Key');
            if ($idempKey) {
                $cacheKey = "order_idem_{$user->id}_{$idempKey}";
                if ($existingOrderId = Cache::get($cacheKey)) {
                    $existingOrder = Order::find($existingOrderId);
                    if ($existingOrder) {
                        DB::rollBack();
                        return response()->json([
                            'success' => true,
                            'message' => 'Order already placed.',
                            'data'    => ['orders' => [$existingOrder]],
                        ]);
                    }
                }
            }

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
                $product = Product::whereKey($item['product_id'])->lockForUpdate()->first();

                if (!$product) {
                    throw new \Exception("Product not found: " . $item['product_id']);
                }

                if (!$product->is_active) {
                    throw new \Exception("Product is not available: " . $product->name_en);
                }

                // Variant-aware stock check
                $variant = null;
                if (!empty($item['variant_id'])) {
                    $variant = ProductVariant::whereKey($item['variant_id'])
                        ->where('product_id', $product->id)
                        ->where('is_active', true)
                        ->lockForUpdate()
                        ->first();

                    if (!$variant) {
                        throw new \Exception("Selected variant is not available for: " . $product->name_en);
                    }
                    if ($product->product_type === 'physical' && $variant->quantity < $item['quantity']) {
                        throw new \Exception("Insufficient stock for: " . $product->name_en);
                    }
                    // MOQ + step validation (variant-level)
                    if ($error = $variant->validateMoqStep($item['quantity'])) {
                        throw new \Exception($error);
                    }
                } elseif ($product->product_type === 'physical') {
                    if ((float) ($product->quantity ?? 0) < (float) $item['quantity']) {
                        throw new \Exception("Insufficient stock for: " . $product->name_en);
                    }

                    // MOQ + step validation (product-level, no variant)
                    if ($error = $product->validateMoqStep($item['quantity'])) {
                        throw new \Exception($error);
                    }
                } else {
                    // MOQ + step validation (product-level, no variant)
                    if ($error = $product->validateMoqStep($item['quantity'])) {
                        throw new \Exception($error);
                    }
                }

                // Resolve effective price: wholesale tier > sale price > base price.
                // Tier pricing takes precedence — consistent with CartController and
                // ProductDetail.jsx display logic so the buyer is never charged more
                // than what they saw in the cart.
                $baseItemPrice = $variant ? (float) $variant->price : (float) $product->price;
                $isOnSale      = !$variant && $product->isCurrentlyOnSale();

                // Always check wholesale tiers first.
                $resolved  = $variant
                    ? $variant->resolveWholesalePrice((float) $item['quantity'])
                    : $product->resolveWholesalePrice((float) $item['quantity']);
                $tierPrice = $resolved['price'] !== $baseItemPrice ? $resolved['price'] : null;

                if ($tierPrice !== null) {
                    // Volume tier matched — use tier price (overrides sale price).
                    $itemPrice = $tierPrice;
                } elseif ($isOnSale) {
                    // No tier matched; apply active sale discount.
                    $itemPrice = (float) $product->discount_price;
                } else {
                    $itemPrice = $baseItemPrice;
                }
                $sellerId = $product->seller_id;
                $itemTotal = $itemPrice * $item['quantity'];
                $subtotal += $itemTotal;

                if (!isset($itemsBySeller[$sellerId])) {
                    $itemsBySeller[$sellerId] = [];
                }

                $itemsBySeller[$sellerId][] = [
                    'product'    => $product,
                    'variant'    => $variant,
                    'quantity'   => $item['quantity'],
                    'price'      => $itemPrice,
                    'subtotal'   => $itemTotal,
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
                        $addr['city']    ?? null,
                        $addr['township'] ?? null
                    )
                    ->orderByDesc('sort_order')
                    ->first();
                $sellerShippingFee = $matchedZone
                    ? $matchedZone->getShippingFeeForOrder($sellerSubtotal)
                    : 8000;
                $sellerTax = $sellerSubtotal * 0.00;

                // Distribute coupon discount proportionally across seller orders.
                // e.g. if this seller's products are 60% of the cart, they absorb 60% of the discount.
                $sellerCouponDiscount = $grandSubtotal > 0
                    ? round($totalCouponDiscount * ($sellerSubtotal / $grandSubtotal), 2)
                    : 0;

                $sellerTotal = max(0, $sellerSubtotal + $sellerShippingFee + $sellerTax - $sellerCouponDiscount);

                // Generate a concurrency-safe public order reference.
                $orderNumber = Order::generateOrderNumber();

                // Resolve commission rate via priority chain:
                // account_level (tier) → business_type → category → default (5%)
                $resolved = app(CommissionRateResolver::class)->resolveForSeller($sellerId, $sellerItems);
                $commissionRate = $resolved['rate'];
                $taxRate = 0.00;
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
                        'order_id'         => $order->id,
                        'product_id'       => $item['product']->id,
                        'variant_id'       => $item['variant']?->id,
                        'product_name'     => $item['product']->name_en,
                        'product_sku'      => $item['product']->sku,
                        'variant_sku'      => $item['variant']?->sku,
                        'selected_options' => $item['variant']
                            ? $item['variant']->optionValues->mapWithKeys(
                                fn($v) => [$v->option->name => $v->label]
                              )->toArray()
                            : null,
                        'quantity_unit'    => $item['variant']
                            ? $item['variant']->effectiveUnit()
                            : $item['product']->effectiveUnit(),
                        'price'            => $item['price'],
                        'quantity'         => $item['quantity'],
                        'subtotal'         => $item['subtotal'],
                        'product_data'     => [
                            'name'         => $item['product']->name_en,
                            'description'  => $item['product']->description_en,
                            'images'       => $item['product']->images,
                            'specifications' => $item['product']->specifications,
                            'category'     => $item['product']->category?->name_en ?? 'Uncategorized',
                            'seller_name'  => $item['product']->seller?->name ?? 'Unknown Seller',
                        ],
                    ]);

                    // Deduct stock from the selected variant or from product-level stock
                    // for simple physical products with no variant rows.
                    if ($item['variant'] && $item['product']->product_type === 'physical') {
                        $item['variant']->deductStock($item['quantity']);
                    } elseif ($item['product']->product_type === 'physical') {
                        if ((float) ($item['product']->quantity ?? 0) < (float) $item['quantity']) {
                            throw new \Exception("Insufficient stock for: " . $item['product']->name_en);
                        }

                        $item['product']->decrement('quantity', $item['quantity']);
                    }
                }

                // Create delivery record for each order
                Delivery::create([
                    'order_id' => $order->id,
                    'supplier_id' => $sellerId,
                    'delivery_method' => 'supplier', // Default to supplier delivery
                    'pickup_address' => $this->getSupplierAddress($sellerId),
                    'delivery_address' => $this->formatShippingAddress($request->shipping_address),
                    'status' => 'pending',
                    'package_weight' => $this->calculateOrderWeight($sellerItems),
                    'estimated_delivery_date' => now()->addDays(5),
                ]);

                $orders[] = $order;

                // ── Fire notifications ──────────────────────────────────────
                try {
                    if ($order->payment_method === Order::PAYMENT_CASH_ON_DELIVERY) {
                        $order->buyer->notify(new OrderPlaced($order));
                    }
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

            // ── Create seller_orders rows (one per seller) ──────────────
            // This gives each seller an independent sub-order with their own
            // status, shipping, and order number — without splitting the buyer's receipt.
            foreach ($orders as $idx => $order) {
                SellerOrder::firstOrCreate(
                    ['order_id' => $order->id, 'seller_id' => $order->seller_id],
                    [
                        'order_number'      => SellerOrder::generateNumber($order->order_number, $idx),
                        'subtotal_amount'   => $order->subtotal_amount,
                        'shipping_fee'      => $order->shipping_fee,
                        'tax_amount'        => $order->tax_amount,
                        'commission_amount' => $order->commission_amount,
                        'total_amount'      => $order->total_amount,
                        'delivery_method'   => 'seller',
                        'status'            => 'pending',
                        'payment_method'    => $request->input('payment_method'),
                    ]
                );
            }

            DB::commit();

            // Store idempotency mapping so repeat POSTs return this order
            if (!empty($idempKey)) {
                $firstOrder = $orders[0] ?? null;
                if ($firstOrder) {
                    Cache::put(
                        "order_idem_{$user->id}_{$idempKey}",
                        $firstOrder->id,
                        now()->addMinutes(30)
                    );
                }
            }

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

        if (! $this->canViewOrder($user, $order)) {
            return response()->json([
                'success' => false,
                'message' => __('messages.orders.view_unauthorized')
            ], 403);
        }

        // Load relations with delivery
        $order->load(['items.product', 'buyer', 'seller', 'delivery.deliveryUpdates']);

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
        $user = Auth::user();
        if (! $this->isAdmin($user)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

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
                $updates = ['status' => 'confirmed'];

                if ($order->payment_method !== 'cash_on_delivery' && $order->escrow_status !== 'held') {
                    $wallet = SellerWallet::forSeller($order->seller_id);
                    $wallet->holdEscrow((float) $order->total_amount, $order->id, Auth::id());
                    $updates['escrow_status'] = 'held';
                }

                $order->update($updates);
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

        if (! $this->canManageOrder($user, $order)) {
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

            $order->load('items.product', 'items.variant');

            foreach ($order->items as $item) {
                // Restore stock to the specific variant, or to product-level stock
                // for simple physical products with no variant rows.
                if ($item->variant && $item->product?->product_type === 'physical') {
                    $item->variant->increment('quantity', $item->quantity);
                } elseif ($item->product?->product_type === 'physical') {
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
    public function confirm(Order $order)
    {

        $user = Auth::user();
        if (! $this->canSellerManageOrder($user, $order)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        if ($order->status !== self::STATUS_PENDING) {
            return response()->json(['success' => false, 'message' => 'Only pending orders can be confirmed'], 400);
        }

        $previousStatus = $order->status;
        $order->status = 'confirmed';
        $order->save();

        // store() already creates a Delivery record when the order is placed.
        // Keep it in "pending" so the seller must explicitly choose self delivery
        // or platform logistics before dispatch.
        $delivery = Delivery::where('order_id', $order->id)->first();
        if ($delivery) {
            DeliveryUpdate::create([
                'delivery_id' => $delivery->id,
                'user_id' => Auth::id(),
                'status' => 'pending',
                'notes' => 'Order confirmed by seller. Delivery method selection is pending.',
            ]);
        }

        $this->notifyBuyerOrderStatusChanged($order, $previousStatus);

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

        if (! $this->canSellerManageOrder($user, $order)) {
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

        $previousStatus = $order->status;

        $order->update([
            'status' => self::STATUS_PROCESSING
        ]);

        $this->notifyBuyerOrderStatusChanged($order, $previousStatus);

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

        if (! $this->canSellerManageOrder($user, $order)) {
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

        DB::beginTransaction();

        try {
            $previousStatus = $order->status;

            $order->update([
            'status' => self::STATUS_SHIPPED,
            'tracking_number' => $request->tracking_number,
            'shipping_carrier' => $request->shipping_carrier
            ]);

            $delivery = Delivery::where('order_id', $order->id)->first();
            if ($delivery) {
                $trackingNumber = $request->tracking_number ?: $delivery->tracking_number ?: $delivery->generateTrackingNumber();
                $previousDeliveryStatus = $delivery->status;

                $delivery->update([
                    'status' => 'in_transit',
                    'tracking_number' => $trackingNumber,
                    'carrier_name' => $request->shipping_carrier ?: $delivery->carrier_name ?: 'Self Delivery',
                    'in_transit_at' => now(),
                ]);

                $delivery->deliveryUpdates()->create([
                    'user_id' => $user->id,
                    'status' => 'in_transit',
                    'notes' => 'Order dispatched by seller.',
                ]);
            }

            DB::commit();

            $this->notifyBuyerOrderStatusChanged($order, $previousStatus);
            if ($delivery ?? null) {
                $this->notifyDeliveryStatusChanged($delivery->fresh(['order.buyer', 'order.seller']), $previousDeliveryStatus ?? null);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Order shipment failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to mark order as shipped: ' . $e->getMessage(),
            ], 500);
        }

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

        if (! $this->canBuyerConfirmDelivery($user, $order)) {
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

        DB::beginTransaction();
        try {
            $this->finalizeDeliveredOrder($order, $user, 'Delivery confirmed by buyer.');
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Delivery confirmation failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to confirm delivery: ' . $e->getMessage(),
            ], 500);
        }

        try {
            $order->load('items', 'buyer', 'seller.sellerProfile');
            $order->buyer->notify(new OrderDeliveredThankYou($order));
        } catch (\Exception $notifEx) {
            \Log::warning('OrderDeliveredThankYou notification failed: ' . $notifEx->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => __('messages.orders.delivery_confirmed')
        ]);
    }

    private function finalizeDeliveredOrder(Order $order, UserModel $actor, string $deliveryNote): void
    {
        $order->refresh();
        $delivery = Delivery::where('order_id', $order->id)->first();

        if ($delivery && $delivery->status !== 'delivered') {
            $delivery->update([
                'status' => 'delivered',
                'delivered_at' => now(),
            ]);

            $delivery->deliveryUpdates()->create([
                'user_id' => $actor->id,
                'status' => 'delivered',
                'notes' => $deliveryNote,
            ]);
        }

        if ($order->status !== self::STATUS_DELIVERED) {
            $order->update([
                'status' => self::STATUS_DELIVERED,
                'delivered_at' => now(),
            ]);
        }

        Commission::where('order_id', $order->id)
            ->where('status', 'pending')
            ->update([
                'status' => 'collected',
                'collected_at' => now(),
            ]);

        $commission = Commission::where('order_id', $order->id)->first();

        if ($order->payment_method !== 'cash_on_delivery') {
            if ($order->escrow_status === 'held') {
                $wallet = SellerWallet::lockForSeller($order->seller_id);
                $wallet->releaseEscrow(
                    escrowAmount: (float) $order->total_amount,
                    sellerPayout: (float) ($commission?->seller_payout ?? ($order->subtotal_amount - $order->commission_amount)),
                    commissionAmount: (float) $order->commission_amount,
                    orderId: $order->id,
                    actorId: $actor->id
                );
            }

            $order->update(['escrow_status' => 'released']);
            return;
        }

        if (!CodCommissionInvoice::where('order_id', $order->id)->exists()) {
            CodCommissionInvoice::create([
                'invoice_number' => 'COD-' . now()->format('YmdHis') . '-' . mt_rand(1000, 9999),
                'order_id' => $order->id,
                'seller_id' => $order->seller_id,
                'order_subtotal' => $order->subtotal_amount,
                'commission_rate' => $order->commission_rate,
                'commission_amount' => $order->commission_amount,
                'status' => 'outstanding',
                'due_date' => now()->addDays(7)->toDateString(),
                'seller_notes' => "Commission owed for COD order #{$order->order_number}. Please settle within 7 days via bank transfer.",
            ]);

            $wallet = SellerWallet::forSeller($order->seller_id);
            $wallet->increment('cod_commission_outstanding', $order->commission_amount);
            $wallet->transactions()->create([
                'order_id' => $order->id,
                'type' => 'cod_invoice',
                'amount' => -(float) $order->commission_amount,
                'escrow_balance_after' => $wallet->escrow_balance,
                'available_balance_after' => $wallet->available_balance,
                'notes' => "COD commission invoice raised for order #{$order->order_number}. Amount: {$order->commission_amount} MMK.",
                'created_by' => $actor->id,
            ]);
        }
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

    private function formatShippingAddress(array $address): string
    {
        return implode(', ', array_filter([
            $address['full_name'] ?? null,
            $address['phone'] ?? null,
            $address['address'] ?? null,
            $address['township'] ?? null,
            $address['city'] ?? null,
            $address['state'] ?? null,
            $address['country'] ?? null,
        ]));
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
            $country   = $request->input('country', 'Myanmar');
            $state     = $request->input('state');
            $city      = $request->input('city');
            $township  = $request->input('township');

            $cartItems = Cart::where('user_id', $user->id)
                ->with('product:id,seller_id,category_id,price')
                ->get();

            $DEFAULT_SHIPPING = 8000;
            $TAX_RATE         = 0.00;

            $sellerBreakdown = [];
            $totalShipping   = 0;

            if ($cartItems->isNotEmpty()) {
                $sellerIds = $cartItems->pluck('product.seller_id')->filter()->unique();

                foreach ($sellerIds as $sellerId) {
                    $sellerItems    = $cartItems->filter(fn($c) => $c->product?->seller_id === $sellerId);
                    $sellerSubtotal = $sellerItems->sum(fn($c) => ($c->product?->price ?? 0) * $c->quantity);

                    $profile     = SellerProfile::where('user_id', $sellerId)->first();
                    $matchedZone = $profile?->activeDeliveryAreas()
                        ->byLocation($country, $state, $city, $township)
                        ->orderByDesc('sort_order')
                        ->first();

                    $fee = $matchedZone
                        ? $matchedZone->getShippingFeeForOrder($sellerSubtotal)
                        : $DEFAULT_SHIPPING;

                    $zoneMatched  = $matchedZone !== null;
                    $freeShipping = false;
                    if ($matchedZone) {
                        $rawFee = $matchedZone->getShippingFeeForOrder($sellerSubtotal);
                        if ($rawFee === 0 && $matchedZone->free_shipping_threshold && $sellerSubtotal >= $matchedZone->free_shipping_threshold) {
                            $freeShipping = true;
                        }
                        $fee = $rawFee;
                    } elseif ($profile?->default_shipping_fee) {
                        $fee = (float) $profile->default_shipping_fee;
                    }
                    $totalShipping += $fee;
                    $sellerBreakdown[] = [
                        'seller_id'              => $sellerId,
                        'store_name'             => $profile?->store_name ?? 'Seller #' . $sellerId,
                        'store_slug'             => $profile?->store_slug,
                        'shipping_fee'           => $fee,
                        'subtotal'               => $sellerSubtotal,
                        'zone_matched'           => $zoneMatched,
                        'zone_name'              => $matchedZone?->state ?? null,
                        'free_shipping_applied'  => $freeShipping,
                        'free_shipping_threshold'=> $matchedZone?->free_shipping_threshold,
                        'fee_source'             => $zoneMatched ? 'zone' : ($profile?->default_shipping_fee ? 'seller_default' : 'platform_default'),
                        'estimated_days_min'     => $matchedZone?->estimated_delivery_days_min,
                        'estimated_days_max'     => $matchedZone?->estimated_delivery_days_max,
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'data'    => [
                    'shipping_fee'  => $totalShipping,
                    'tax_rate'      => $TAX_RATE,
                    'tax_pct'       => round($TAX_RATE * 100, 2),
                    'sellers'       => $sellerBreakdown ?? [],
                    'seller_shipping' => collect($sellerBreakdown ?? [])->pluck('shipping_fee', 'seller_id'),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('checkoutFees failed: ' . $e->getMessage());

            return response()->json([
                'success' => true,
                'data'    => [
                    'shipping_fee'      => 8000,
                    'seller_shipping'   => [],
                    'default_shipping'  => 8000,
                    'platform_fee_rate' => 0.05,
                    'platform_fee_pct'  => 5.0,
                    'tax_rate'          => 0.00,
                    'tax_pct'           => 0.0,
                    'shipping_fee'     => 8000,
                    'seller_shipping'  => [],
                    'default_shipping' => 8000,
                ],
            ]);
        }
    }
    /**
     * PATCH /orders/{order}/status — admin-only
     */
    public function updateStatus(Request $request, Order $order)
    {
        $user = Auth::user();
        if (!$user->hasRole('admin') && $user->type !== 'admin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }
        $validated = $request->validate([
            'status' => 'required|in:pending,confirmed,processing,shipped,delivered,cancelled',
        ]);

        $previousStatus = $order->status;
        $delivery = Delivery::where('order_id', $order->id)->first();
        $previousDeliveryStatus = $delivery?->status;

        DB::beginTransaction();
        try {
            if ($validated['status'] === self::STATUS_DELIVERED) {
                $this->finalizeDeliveredOrder($order, $user, 'Manually marked delivered by admin.');
            } else {
                $order->update(['status' => $validated['status']]);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Admin order status update failed: ' . $e->getMessage(), [
                'order_id' => $order->id,
                'status' => $validated['status'],
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update order status: ' . $e->getMessage(),
            ], 500);
        }

        $order->refresh();
        $this->notifyBuyerOrderStatusChanged($order, $previousStatus);

        if ($validated['status'] === self::STATUS_DELIVERED) {
            $delivery = Delivery::where('order_id', $order->id)->first();
            if ($delivery) {
                $this->notifyDeliveryStatusChanged($delivery->fresh(['order.buyer', 'order.seller']), $previousDeliveryStatus);
            }
        }

        return response()->json(['success' => true, 'data' => $order->fresh()]);
    }

    private function isAdmin(?UserModel $user): bool
    {
        return (bool) ($user?->hasRole('admin') || $user?->type === 'admin');
    }

    private function canViewOrder(?UserModel $user, Order $order): bool
    {
        if (! $user) {
            return false;
        }

        if ($this->isAdmin($user)) {
            return true;
        }

        if ($user->hasRole('buyer')) {
            return (int) $order->buyer_id === (int) $user->id;
        }

        if ($user->hasRole('seller')) {
            return (int) $order->seller_id === (int) $user->id;
        }

        return false;
    }

    private function canManageOrder(?UserModel $user, Order $order): bool
    {
        return $this->canViewOrder($user, $order);
    }

    private function canSellerManageOrder(?UserModel $user, Order $order): bool
    {
        if (! $user) {
            return false;
        }

        return $this->isAdmin($user)
            || ($user->hasRole('seller') && (int) $order->seller_id === (int) $user->id);
    }

    private function canBuyerConfirmDelivery(?UserModel $user, Order $order): bool
    {
        if (! $user) {
            return false;
        }

        return $this->isAdmin($user)
            || ($user->hasRole('buyer') && (int) $order->buyer_id === (int) $user->id);
    }

    private function notifyBuyerOrderStatusChanged(Order $order, string $previousStatus): void
    {
        if ($previousStatus === $order->status) {
            return;
        }

        try {
            $order->loadMissing('buyer', 'items', 'delivery');
            $order->buyer?->notify(new OrderStatusChanged($order, $previousStatus));
        } catch (\Exception $notifEx) {
            Log::warning('OrderStatusChanged notification failed: ' . $notifEx->getMessage(), [
                'order_id' => $order->id,
            ]);
        }
    }

    private function notifyDeliveryStatusChanged(Delivery $delivery, ?string $previousStatus = null): void
    {
        if ($previousStatus !== null && $previousStatus === $delivery->status) {
            return;
        }

        try {
            $delivery->loadMissing('order.buyer', 'order.seller');
            if ($delivery->order?->buyer) {
                $delivery->order->buyer->notify(new DeliveryStatusUpdated($delivery, $previousStatus));
            }
        } catch (\Exception $notifEx) {
            Log::warning('DeliveryStatusUpdated notification failed from order flow: ' . $notifEx->getMessage(), [
                'delivery_id' => $delivery->id,
            ]);
        }
    }

}

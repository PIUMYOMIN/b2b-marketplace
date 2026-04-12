<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CommissionRateResolver;
use App\Notifications\OrderPlaced;
use App\Notifications\OrderDeliveredThankYou;
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
use App\Models\SellerWallet;
use App\Models\CodCommissionInvoice;
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
    const STATUS_PENDING    = 'pending';
    const STATUS_CONFIRMED  = 'confirmed';
    const STATUS_PROCESSING = 'processing';
    const STATUS_SHIPPED    = 'shipped';
    const STATUS_DELIVERED  = 'delivered';
    const STATUS_CANCELLED  = 'cancelled';

    const PAYMENT_STATUS_PENDING  = 'pending';
    const PAYMENT_STATUS_PAID     = 'paid';
    const PAYMENT_STATUS_FAILED   = 'failed';
    const PAYMENT_STATUS_REFUNDED = 'refunded';

    // ── Index ──────────────────────────────────────────────────────────────────

    public function index()
    {
        $user    = Auth::user();
        $baseUrl = config('app.url') . '/storage/';

        if ($user->hasRole('seller')) {
            $orders = Order::with(['items', 'buyer'])
                ->where('seller_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->get();
        } elseif ($user->hasRole('buyer')) {
            $orders = Order::with(['items', 'seller.sellerProfile'])
                ->where('buyer_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->get();
        } else {
            $orders = Order::with(['items', 'buyer', 'seller.sellerProfile'])
                ->orderBy('created_at', 'desc')
                ->get();
        }

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
                if (!empty($productData['image']) && !str_starts_with($productData['image'], 'http')) {
                    $productData['image'] = config('app.url') . '/storage/' . ltrim($productData['image'], '/');
                }
                $item->product_data = $productData;
                return $item;
            });

            $sellerProfile       = $order->seller?->sellerProfile;
            $order->store_name   = $sellerProfile?->store_name;
            $order->store_slug   = $sellerProfile?->store_slug;
            $order->store_logo   = $sellerProfile?->store_logo;

            return $order;
        });

        return response()->json(['success' => true, 'data' => $orders]);
    }

    // ── OTP ────────────────────────────────────────────────────────────────────

    public function requestOtp(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'items'                      => 'required|array|min:1',
            'items.*.product_id'         => 'required|exists:products,id',
            'items.*.quantity'           => 'required|integer|min:1',
            'shipping_address'           => 'required|array',
            'shipping_address.full_name' => 'required|string',
            'shipping_address.phone'     => 'required|string',
            'shipping_address.address'   => 'required|string',
            'payment_method'             => 'required|in:kbz_pay,wave_pay,cb_pay,aya_pay,mmqr,cash_on_delivery',
        ]);

        foreach ($request->items as $item) {
            $product = Product::findOrFail($item['product_id']);
            if (!$product->is_active) {
                return response()->json(['success' => false, 'message' => "Product \"{$product->name}\" is no longer available."], 422);
            }
            if ($product->quantity < $item['quantity']) {
                return response()->json(['success' => false, 'message' => "Insufficient stock for \"{$product->name}\"."], 422);
            }
        }

        $subtotal = collect($request->items)->sum(function ($item) {
            $product = Product::find($item['product_id']);
            return $product ? $product->price * $item['quantity'] : 0;
        });

        $addr          = $request->shipping_address;
        $firstSellerId = Product::find($request->items[0]['product_id'])?->seller_id;
        $sellerProfile = $firstSellerId ? SellerProfile::where('user_id', $firstSellerId)->first() : null;
        $matchedZone   = $sellerProfile?->activeDeliveryAreas()
            ->byLocation($addr['country'] ?? 'Myanmar', $addr['state'] ?? null, $addr['city'] ?? null)
            ->orderByDesc('sort_order')
            ->first();
        $estimatedShipping = $matchedZone ? $matchedZone->getShippingFeeForOrder($subtotal) : 5000;

        $total          = $subtotal + $estimatedShipping + ($subtotal * 0.05);
        $formattedTotal = number_format($total, 0) . ' MMK';

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $ttl = now()->addMinutes(10);

        Cache::put("order_otp_{$user->id}",         $otp,                $ttl);
        Cache::put("order_otp_expires_{$user->id}", $ttl->toISOString(), $ttl);

        Mail::to($user->email)->send(new OrderOtpMail($otp, $user->name, $formattedTotal));

        return response()->json([
            'success'    => true,
            'message'    => "A 6-digit confirmation code has been sent to {$user->email}.",
            'email_hint' => $this->maskEmail($user->email),
            'expires_in' => 600,
        ]);
    }

    public function verifyOtp(Request $request)
    {
        $user = Auth::user();

        $request->validate(['otp' => 'required|string|size:6']);

        $storedOtp     = Cache::get("order_otp_{$user->id}");
        $storedExpires = Cache::get("order_otp_expires_{$user->id}");

        if (!$storedOtp || !$storedExpires) {
            return response()->json(['success' => false, 'message' => __('messages.orders.otp_not_found')], 422);
        }

        if (now()->gt(Carbon::parse($storedExpires))) {
            Cache::forget("order_otp_{$user->id}");
            Cache::forget("order_otp_expires_{$user->id}");
            return response()->json(['success' => false, 'message' => __('messages.orders.otp_expired')], 422);
        }

        if ($request->otp !== $storedOtp) {
            return response()->json(['success' => false, 'message' => __('messages.orders.otp_incorrect')], 422);
        }

        Cache::put("order_otp_verified_{$user->id}", true, now()->addMinutes(5));
        Cache::forget("order_otp_{$user->id}");
        Cache::forget("order_otp_expires_{$user->id}");

        return response()->json(['success' => true, 'message' => __('messages.orders.otp_verified')]);
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain]       = explode('@', $email, 2);
        $maskedLocal            = substr($local, 0, 1) . str_repeat('*', max(strlen($local) - 2, 1)) . substr($local, -1);
        [$domainName, $tld]     = array_pad(explode('.', $domain, 2), 2, '');
        $maskedDomain           = substr($domainName, 0, 1) . str_repeat('*', max(strlen($domainName) - 1, 1));
        return "{$maskedLocal}@{$maskedDomain}.{$tld}";
    }

    // ── Store (Place Order) ────────────────────────────────────────────────────

    public function store(Request $request)
    {
        DB::beginTransaction();

        try {
            $user = Auth::user();

            // ── OTP gate ──────────────────────────────────────────────────────
            $otpVerified = Cache::get("order_otp_verified_{$user->id}");
            if (!$otpVerified) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.orders.otp_required'),
                    'code'    => 'OTP_REQUIRED',
                ], 403);
            }
            Cache::forget("order_otp_verified_{$user->id}");

            $request->validate([
                'items'                      => 'required|array|min:1',
                'items.*.product_id'         => 'required|exists:products,id',
                'items.*.quantity'           => 'required|integer|min:1',
                'shipping_address'           => 'required|array',
                'shipping_address.full_name' => 'required|string',
                'shipping_address.phone'     => 'required|string',
                'shipping_address.address'   => 'required|string',
                'payment_method'             => 'required|in:kbz_pay,wave_pay,cb_pay,aya_pay,mmqr,cash_on_delivery',
                'coupon_code'                => 'nullable|string|max:50',
                'coupon_id'                  => 'nullable|integer|exists:coupons,id',
                'coupon_discount_amount'     => 'nullable|numeric|min:0',
            ]);

            $isCod     = $request->payment_method === 'cash_on_delivery';
            $cartItems = $request->items;

            // ── Coupon server-side re-validation ──────────────────────────────
            $coupon              = null;
            $totalCouponDiscount = 0;

            if ($request->filled('coupon_code')) {
                $coupon = Coupon::where('code', strtoupper($request->coupon_code))->first();

                if (!$coupon) {
                    return response()->json(['success' => false, 'message' => 'Coupon code not found'], 422);
                }

                $validationError = $coupon->getValidationError();
                if ($validationError) {
                    return response()->json(['success' => false, 'message' => $validationError], 422);
                }

                if ($coupon->hasUserExhausted($user->id)) {
                    return response()->json(['success' => false, 'message' => 'You have already used this coupon'], 422);
                }

                $applicableSubtotal = 0;
                foreach ($cartItems as $item) {
                    $product = Product::find($item['product_id']);
                    if ($product && $coupon->appliesToProduct($product)) {
                        $applicableSubtotal += $product->price * $item['quantity'];
                    }
                }

                if ($applicableSubtotal <= 0) {
                    return response()->json(['success' => false, 'message' => 'This coupon does not apply to any selected products'], 422);
                }

                $totalCouponDiscount = $coupon->calculateDiscount($applicableSubtotal);
            }

            // ── Group items by seller ─────────────────────────────────────────
            $itemsBySeller = [];
            $subtotal      = 0;

            foreach ($cartItems as $item) {
                $product = Product::find($item['product_id']);

                if (!$product)              throw new \Exception("Product not found: " . $item['product_id']);
                if (!$product->is_active)   throw new \Exception("Product is not available: " . $product->name);
                if ($product->quantity < $item['quantity']) {
                    throw new \Exception("Insufficient stock for: " . $product->name);
                }

                $sellerId  = $product->seller_id;
                $itemTotal = $product->price * $item['quantity'];
                $subtotal += $itemTotal;

                $itemsBySeller[$sellerId][] = [
                    'product'  => $product,
                    'quantity' => $item['quantity'],
                    'price'    => $product->price,
                    'subtotal' => $itemTotal,
                ];
            }

            // ── Create one order per seller ───────────────────────────────────
            $orders        = [];
            $grandSubtotal = collect($itemsBySeller)->flatten(1)->sum('subtotal');

            foreach ($itemsBySeller as $sellerId => $sellerItems) {
                $sellerSubtotal = collect($sellerItems)->sum('subtotal');

                $addr          = $request->shipping_address;
                $sellerProfile = SellerProfile::where('user_id', $sellerId)->first();
                $matchedZone   = $sellerProfile?->activeDeliveryAreas()
                    ->byLocation($addr['country'] ?? 'Myanmar', $addr['state'] ?? null, $addr['city'] ?? null)
                    ->orderByDesc('sort_order')
                    ->first();
                $sellerShippingFee = $matchedZone
                    ? $matchedZone->getShippingFeeForOrder($sellerSubtotal)
                    : 5000;
                $sellerTax = $sellerSubtotal * 0.05;

                $sellerCouponDiscount = $grandSubtotal > 0
                    ? round($totalCouponDiscount * ($sellerSubtotal / $grandSubtotal), 2)
                    : 0;

                $sellerTotal = max(0, $sellerSubtotal + $sellerShippingFee + $sellerTax - $sellerCouponDiscount);

                $orderNumber      = 'ORD-' . date('Ymd') . '-' . str_pad(Order::count() + 1, 5, '0', STR_PAD_LEFT);
                $resolved         = app(CommissionRateResolver::class)->resolveForSeller($sellerId, $sellerItems);
                $commissionRate   = $resolved['rate'];
                $taxRate          = 0.05;
                $commissionAmount = round($sellerSubtotal * $commissionRate, 2);
                $platformRevenue  = $commissionAmount + $sellerTax;
                $sellerPayout     = $sellerSubtotal - $commissionAmount;

                // ── Escrow status: held for digital payments, not_applicable for COD ──
                $escrowStatus = $isCod ? 'not_applicable' : 'held';

                $order = Order::create([
                    'order_number'           => $orderNumber,
                    'buyer_id'               => $user->id,
                    'seller_id'              => $sellerId,
                    'total_amount'           => $sellerTotal,
                    'subtotal_amount'        => $sellerSubtotal,
                    'shipping_fee'           => $sellerShippingFee,
                    'tax_amount'             => $sellerTax,
                    'tax_rate'               => $taxRate,
                    'status'                 => self::STATUS_PENDING,
                    'payment_method'         => $request->payment_method,
                    'payment_status'         => $isCod ? self::PAYMENT_STATUS_PENDING : self::PAYMENT_STATUS_PAID,
                    'escrow_status'          => $escrowStatus,
                    'shipping_address'       => $request->shipping_address,
                    'order_notes'            => $request->notes,
                    'commission_rate'        => $commissionRate,
                    'commission_amount'      => $commissionAmount,
                    'coupon_id'              => $coupon?->id,
                    'coupon_code'            => $coupon?->code,
                    'coupon_discount_amount' => $sellerCouponDiscount,
                ]);

                Commission::create([
                    'order_id'           => $order->id,
                    'seller_id'          => $sellerId,
                    'amount'             => $commissionAmount,
                    'commission_rate'    => $commissionRate,
                    'tax_amount'         => $sellerTax,
                    'tax_rate'           => $taxRate,
                    'platform_revenue'   => $platformRevenue,
                    'seller_payout'      => $sellerPayout,
                    'status'             => 'pending',
                    'due_date'           => now()->addDays(30),
                    'notes'              => "Order {$orderNumber}: commission {$commissionRate}% + 5% tax (rule: {$resolved['rule_type']}). "
                                         . ($isCod ? 'COD — invoice will be raised on delivery.' : 'Escrow held until delivery confirmation.'),
                    'commission_rule_id' => $resolved['rule_id'],
                ]);

                // ── Escrow: lock seller payout for digital payments ────────────
                if (!$isCod) {
                    $wallet = SellerWallet::lockForSeller($sellerId);
                    $wallet->holdEscrow($sellerTotal, $order->id, $user->id);
                }

                foreach ($sellerItems as $item) {
                    OrderItem::create([
                        'order_id'     => $order->id,
                        'product_id'   => $item['product']->id,
                        'product_name' => $item['product']->name,
                        'product_sku'  => $item['product']->sku,
                        'price'        => $item['price'],
                        'quantity'     => $item['quantity'],
                        'subtotal'     => $item['subtotal'],
                        'product_data' => [
                            'name'           => $item['product']->name,
                            'description'    => $item['product']->description,
                            'images'         => $item['product']->images,
                            'specifications' => $item['product']->specifications,
                            'category'       => $item['product']->category->name ?? 'Uncategorized',
                            'seller_name'    => $item['product']->seller->name ?? 'Unknown Seller',
                        ],
                    ]);

                    $item['product']->decrement('quantity', $item['quantity']);
                }

                Delivery::create([
                    'order_id'              => $order->id,
                    'supplier_id'           => $sellerId,
                    'delivery_method'       => 'supplier',
                    'pickup_address'        => $this->getSupplierAddress($sellerId),
                    'delivery_address'      => $request->shipping_address['address'],
                    'status'                => 'pending',
                    'delivery_fee_status'   => 'not_applicable',
                    'package_weight'        => $this->calculateOrderWeight($sellerItems),
                    'estimated_delivery_date' => now()->addDays(5),
                ]);

                $orders[] = $order;

                try {
                    $order->buyer->notify(new OrderPlaced($order));
                    $sellerUser = UserModel::find($sellerId);
                    if ($sellerUser) $sellerUser->notify(new NewOrderForSeller($order));
                } catch (\Exception $notifEx) {
                    Log::warning('Order notification failed: ' . $notifEx->getMessage());
                }
            }

            if ($coupon && !empty($orders)) {
                $coupon->recordUsage($user->id, $orders[0]->id, $totalCouponDiscount);
            }

            Cart::where('user_id', $user->id)->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Order created successfully',
                'data'    => ['orders' => $orders, 'total_orders' => count($orders)],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Order creation failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to create order: ' . $e->getMessage()], 500);
        }
    }

    // ── Show ───────────────────────────────────────────────────────────────────

    public function show(Order $order)
    {
        $user = Auth::user();

        if ($user->hasRole('seller') && $order->seller_id !== $user->id) {
            return response()->json(['success' => false, 'message' => __('messages.orders.view_unauthorized')], 403);
        }
        if ($user->hasRole('buyer') && $order->buyer_id !== $user->id) {
            return response()->json(['success' => false, 'message' => __('messages.orders.view_unauthorized')], 403);
        }

        $order->load(['items.product', 'buyer', 'seller', 'delivery']);

        foreach ($order->items as $item) {
            $productData = $item->product_data;
            if (!empty($productData['images']) && is_array($productData['images'])) {
                $productData['images'] = $this->formatImages($productData['images']);
                $item->product_data    = $productData;
            }
            if ($item->product && !empty($item->product->images)) {
                $item->product->images = $this->formatImages($item->product->images);
            }
        }

        if ($order->delivery?->delivery_proof_image && !str_starts_with($order->delivery->delivery_proof_image, 'http')) {
            $order->delivery->delivery_proof_image = url('storage/' . ltrim($order->delivery->delivery_proof_image, '/'));
        }

        return response()->json(['success' => true, 'data' => $order]);
    }

    protected function formatImages($images): array
    {
        if (empty($images)) return [];

        return collect($images)->map(function ($image, $index) {
            if (is_string($image)) {
                $url = str_starts_with($image, 'http') ? $image : url('storage/' . ltrim($image, '/'));
                return ['url' => $url, 'angle' => 'default', 'is_primary' => $index === 0];
            }
            $url = $image['url'] ?? $image['path'] ?? '';
            if (!str_starts_with($url, 'http')) $url = url('storage/' . ltrim($url, '/'));
            return ['url' => $url, 'angle' => $image['angle'] ?? 'default', 'is_primary' => $image['is_primary'] ?? ($index === 0)];
        })->values()->all();
    }

    // ── Payment Update ─────────────────────────────────────────────────────────

    public function updatePayment(Request $request, Order $order)
    {
        $request->validate([
            'payment_status' => 'required|in:paid,failed,refunded',
            'payment_data'   => 'nullable|array',
        ]);

        DB::beginTransaction();
        try {
            $order->update([
                'payment_status' => $request->payment_status,
                'payment_data'   => $request->payment_data,
            ]);

            if ($request->payment_status === 'paid') {
                $order->update(['status' => 'confirmed']);
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Payment status updated successfully', 'data' => $order]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Failed to update payment status: ' . $e->getMessage()], 500);
        }
    }

    // ── Cancel ────────────────────────────────────────────────────────────────

    public function cancel(Order $order, Request $request)
    {
        $user = Auth::user();

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
            $order->update(['status' => self::STATUS_CANCELLED, 'cancelled_at' => now()]);

            $order->load('items.product');
            foreach ($order->items as $item) {
                if ($item->product) $item->product->increment('quantity', $item->quantity);
            }

            if ($order->coupon_id) {
                $usage = CouponUsage::where('coupon_id', $order->coupon_id)
                    ->where('user_id', $order->buyer_id)
                    ->where('order_id', $order->id)
                    ->first();
                if ($usage) {
                    $usage->delete();
                    Coupon::where('id', $order->coupon_id)->where('used_count', '>', 0)->decrement('used_count');
                }
            }

            $delivery = Delivery::where('order_id', $order->id)->first();
            if ($delivery && !in_array($delivery->status, ['delivered', 'failed'])) {
                $delivery->update(['status' => 'cancelled']);
            }

            // ── Reverse escrow for digital payments (no commission taken on cancellation) ──
            if ($order->escrow_status === 'held' && $order->payment_method !== 'cash_on_delivery') {
                $wallet = SellerWallet::lockForSeller($order->seller_id);
                $wallet->reverseEscrow($order->total_amount, $order->id, $user->id);
                $order->update(['escrow_status' => 'reversed']);
            }

            Commission::where('order_id', $order->id)->update(['status' => 'waived']);

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Order cancelled successfully', 'data' => $order->fresh()]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Order cancellation failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to cancel order: ' . $e->getMessage()], 500);
        }
    }

    // ── Confirm (seller) ───────────────────────────────────────────────────────

    public function confirm(Order $order)
    {
        $user = Auth::user();
        if ($user->hasRole('seller') && (int) $order->seller_id !== (int) $user->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }
        if ($order->status !== self::STATUS_PENDING) {
            return response()->json(['success' => false, 'message' => 'Only pending orders can be confirmed'], 400);
        }

        $order->status = 'confirmed';
        $order->save();

        $delivery = Delivery::where('order_id', $order->id)->first();
        if ($delivery) {
            $delivery->update(['status' => 'awaiting_pickup']);
            DeliveryUpdate::create([
                'delivery_id' => $delivery->id,
                'user_id'     => Auth::id(),
                'status'      => 'awaiting_pickup',
                'notes'       => 'Order confirmed by seller. Awaiting pickup.',
            ]);
        }

        return response()->json(['success' => true, 'message' => 'Order confirmed successfully', 'data' => $order->load(['items', 'delivery'])]);
    }

    // ── Process (seller) ───────────────────────────────────────────────────────

    public function process(Order $order)
    {
        $user = Auth::user();
        if ($user->hasRole('seller') && $order->seller_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized to process this order'], 403);
        }
        if ($order->status !== self::STATUS_CONFIRMED) {
            return response()->json(['success' => false, 'message' => 'Order must be confirmed before processing'], 400);
        }

        $order->update(['status' => self::STATUS_PROCESSING]);
        return response()->json(['success' => true, 'message' => 'Order marked as processing']);
    }

    // ── Ship (seller) ──────────────────────────────────────────────────────────

    public function ship(Request $request, Order $order)
    {
        $user = Auth::user();
        if ($user->hasRole('seller') && $order->seller_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized to ship this order'], 403);
        }
        if (!in_array($order->status, [self::STATUS_CONFIRMED, self::STATUS_PROCESSING])) {
            return response()->json(['success' => false, 'message' => 'Order cannot be shipped in current status'], 400);
        }

        $request->validate(['tracking_number' => 'nullable|string', 'shipping_carrier' => 'nullable|string']);

        $order->update([
            'status'           => self::STATUS_SHIPPED,
            'tracking_number'  => $request->tracking_number,
            'shipping_carrier' => $request->shipping_carrier,
        ]);

        return response()->json(['success' => true, 'message' => 'Order marked as shipped']);
    }

    // ── Confirm Delivery (buyer) ───────────────────────────────────────────────

    public function confirmDelivery(Order $order)
    {
        $user = Auth::user();

        if ($user->hasRole('buyer') && $order->buyer_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized to confirm delivery for this order'], 403);
        }

        // If the seller already uploaded delivery proof, the order is already delivered
        // and the full pipeline has already run. Return success — idempotent.
        if ($order->status === self::STATUS_DELIVERED) {
            return response()->json([
                'success' => true,
                'message' => __('messages.orders.delivery_confirmed'),
            ]);
        }

        // Gate: order must be at shipped (or out_for_delivery on delivery side)
        // to proceed. Any other status is invalid.
        if (!in_array($order->status, [self::STATUS_SHIPPED, self::STATUS_PROCESSING, self::STATUS_CONFIRMED])) {
            return response()->json(['success' => false, 'message' => 'Order cannot be confirmed in its current status.'], 400);
        }

        DB::beginTransaction();
        try {
            $order->update(['status' => self::STATUS_DELIVERED, 'delivered_at' => now()]);

            // ── Mark commission collected ──────────────────────────────────────
            Commission::where('order_id', $order->id)
                ->where('status', 'pending')
                ->update(['status' => 'collected', 'collected_at' => now()]);

            $commission = Commission::where('order_id', $order->id)->first();

            if ($order->payment_method !== 'cash_on_delivery') {
                // ── Digital payment: release escrow, deduct commission, credit payout ──
                $wallet = SellerWallet::lockForSeller($order->seller_id);
                $wallet->releaseEscrow(
                    escrowAmount:     (float) $order->total_amount,
                    sellerPayout:     (float) ($commission?->seller_payout ?? ($order->subtotal_amount - $order->commission_amount)),
                    commissionAmount: (float) $order->commission_amount,
                    orderId:          $order->id,
                    actorId:          $user->id
                );
                $order->update(['escrow_status' => 'released']);
            } else {
                // ── COD: commission is owed — raise an invoice for the seller ──────
                CodCommissionInvoice::create([
                    'invoice_number'   => CodCommissionInvoice::generateInvoiceNumber(),
                    'order_id'         => $order->id,
                    'seller_id'        => $order->seller_id,
                    'order_subtotal'   => $order->subtotal_amount,
                    'commission_rate'  => $order->commission_rate,
                    'commission_amount'=> $order->commission_amount,
                    'status'           => 'outstanding',
                    'due_date'         => now()->addDays(7)->toDateString(),
                    'seller_notes'     => "Commission owed for COD order #{$order->order_number}. "
                                       . "Please settle within 7 days via bank transfer.",
                ]);

                // Track outstanding COD debt on the wallet
                $wallet = SellerWallet::forSeller($order->seller_id);
                $wallet->increment('cod_commission_outstanding', $order->commission_amount);
                $wallet->transactions()->create([
                    'order_id'               => $order->id,
                    'type'                   => 'cod_invoice',
                    'amount'                 => -$order->commission_amount,
                    'escrow_balance_after'   => $wallet->escrow_balance,
                    'available_balance_after'=> $wallet->available_balance,
                    'notes'                  => "COD commission invoice raised for order #{$order->order_number}. "
                                             . "Amount: {$order->commission_amount} MMK. Due: " . now()->addDays(7)->toDateString(),
                    'created_by'             => $user->id,
                ]);
            }

            DB::commit();

            try {
                $order->load('items', 'buyer', 'seller.sellerProfile');
                $order->buyer->notify(new OrderDeliveredThankYou($order));
            } catch (\Exception $notifEx) {
                Log::warning('OrderDeliveredThankYou notification failed: ' . $notifEx->getMessage());
            }

            return response()->json(['success' => true, 'message' => __('messages.orders.delivery_confirmed')]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Delivery confirmation failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to confirm delivery: ' . $e->getMessage()], 500);
        }
    }

    // ── Refund (admin only) ────────────────────────────────────────────────────

    /**
     * POST /orders/{order}/refund
     *
     * Refund policy:
     *  - Commission is NEVER returned to the seller. It is permanently forfeited.
     *  - For digital orders: buyer receives subtotal + shipping (minus commission).
     *  - For COD orders: if the COD invoice is outstanding, it is waived;
     *    the admin handles the cash refund directly.
     *  - Refunds are only available for delivered orders (not cancelled).
     *
     * Only admins can approve refunds.
     */
    public function refund(Request $request, Order $order)
    {
        $user = Auth::user();
        if (!$user->hasRole('admin') && $user->type !== 'admin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        if ($order->status !== self::STATUS_DELIVERED) {
            return response()->json([
                'success' => false,
                'message' => 'Refunds can only be processed for delivered orders.',
            ], 400);
        }

        if ($order->escrow_status === 'refunded') {
            return response()->json(['success' => false, 'message' => 'This order has already been refunded.'], 400);
        }

        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        DB::beginTransaction();
        try {
            $commissionForfeited = (float) $order->commission_amount;

            // Buyer refund = subtotal + shipping (commission stays with platform)
            $buyerRefundAmount = (float) $order->subtotal_amount
                               + (float) $order->shipping_fee
                               - $commissionForfeited;
            $buyerRefundAmount = max(0, $buyerRefundAmount);

            if ($order->payment_method !== 'cash_on_delivery') {
                // For digital orders: debit from seller's available_balance.
                // The escrow was already released at delivery, so we recover from payout.
                $wallet = SellerWallet::lockForSeller($order->seller_id);

                $wallet->processRefund(
                    escrowAmount:        (float) $order->total_amount,
                    buyerRefundAmount:   $buyerRefundAmount,
                    commissionForfeited: $commissionForfeited,
                    orderId:             $order->id,
                    actorId:             $user->id
                );
            } else {
                // For COD orders: waive the outstanding invoice (if any)
                CodCommissionInvoice::where('order_id', $order->id)
                    ->where('status', 'outstanding')
                    ->update([
                        'status'       => 'waived',
                        'admin_notes'  => "Waived due to refund approved by admin #{$user->id}. Reason: {$request->reason}",
                        'confirmed_by' => $user->id,
                        'confirmed_at' => now(),
                    ]);

                // Reduce outstanding COD debt on wallet
                $wallet = SellerWallet::forSeller($order->seller_id);
                if ((float) $wallet->cod_commission_outstanding >= $commissionForfeited) {
                    $wallet->decrement('cod_commission_outstanding', $commissionForfeited);
                }
            }

            $order->update([
                'status'               => 'refunded',
                'payment_status'       => self::PAYMENT_STATUS_REFUNDED,
                'escrow_status'        => 'refunded',
                'refund_amount'        => $buyerRefundAmount,
                'commission_forfeited' => $commissionForfeited,
                'refunded_at'          => now(),
                'refund_reason'        => $request->reason,
                'refund_approved_by'   => $user->id,
            ]);

            Commission::where('order_id', $order->id)
                ->update(['status' => 'collected']); // commission kept even on refund

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Refund processed successfully. Commission of ' . number_format($commissionForfeited, 0) . ' MMK has been retained by the platform.',
                'data'    => [
                    'buyer_refund_amount'  => $buyerRefundAmount,
                    'commission_forfeited' => $commissionForfeited,
                    'payment_method'       => $order->payment_method,
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Refund failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Refund failed: ' . $e->getMessage()], 500);
        }
    }

    // ── Seller Dashboard Helpers ───────────────────────────────────────────────

    public function sellerRecentOrders()
    {
        $user = Auth::user();
        if (!$user->hasRole('seller')) {
            return response()->json(['success' => false, 'message' => 'Only sellers can access this endpoint'], 403);
        }

        $orders = Order::with(['items', 'buyer'])
            ->where('seller_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return response()->json(['success' => true, 'data' => $orders]);
    }

    public function sellerOrderStats()
    {
        $user = Auth::user();
        if (!$user->hasRole('seller')) {
            return response()->json(['success' => false, 'message' => 'Only sellers can access this endpoint'], 403);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'total_orders'    => Order::where('seller_id', $user->id)->count(),
                'pending_orders'  => Order::where('seller_id', $user->id)->where('status', self::STATUS_PENDING)->count(),
                'confirmed_orders'=> Order::where('seller_id', $user->id)->where('status', self::STATUS_CONFIRMED)->count(),
                'shipped_orders'  => Order::where('seller_id', $user->id)->where('status', self::STATUS_SHIPPED)->count(),
                'delivered_orders'=> Order::where('seller_id', $user->id)->where('status', self::STATUS_DELIVERED)->count(),
                'total_revenue'   => Order::where('seller_id', $user->id)->where('status', self::STATUS_DELIVERED)->sum('total_amount'),
                'pending_revenue' => Order::where('seller_id', $user->id)
                    ->whereIn('status', [self::STATUS_PENDING, self::STATUS_CONFIRMED, self::STATUS_SHIPPED])
                    ->sum('total_amount'),
            ],
        ]);
    }

    // ── Admin Status Override ──────────────────────────────────────────────────

    public function updateStatus(Request $request, Order $order)
    {
        $user = Auth::user();
        if (!$user->hasRole('admin') && $user->type !== 'admin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }
        $validated = $request->validate([
            'status' => 'required|in:pending,confirmed,processing,shipped,delivered,cancelled',
        ]);
        $order->update(['status' => $validated['status']]);
        return response()->json(['success' => true, 'message' => 'Order status updated.', 'data' => $order->fresh()]);
    }

    // ── Checkout Fees ──────────────────────────────────────────────────────────

    public function checkoutFees(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $user    = $request->user();
            $country = $request->input('country', 'Myanmar');
            $state   = $request->input('state');
            $city    = $request->input('city');

            $cartItems        = Cart::where('user_id', $user->id)->with('product:id,seller_id,category_id,price')->get();
            $DEFAULT_SHIPPING = 5000;
            $TAX_RATE         = 0.05;
            $sellerShippingFees = [];
            $totalShipping      = 0;

            if ($cartItems->isNotEmpty()) {
                foreach ($cartItems->pluck('product.seller_id')->filter()->unique() as $sellerId) {
                    $sellerItems    = $cartItems->filter(fn($c) => $c->product?->seller_id === $sellerId);
                    $sellerSubtotal = $sellerItems->sum(fn($c) => ($c->product?->price ?? 0) * $c->quantity);

                    $profile     = SellerProfile::where('user_id', $sellerId)->first();
                    $matchedZone = $profile?->activeDeliveryAreas()
                        ->byLocation($country, $state, $city)
                        ->orderByDesc('sort_order')
                        ->first();

                    $fee                        = $matchedZone ? $matchedZone->getShippingFeeForOrder($sellerSubtotal) : $DEFAULT_SHIPPING;
                    $sellerShippingFees[$sellerId] = $fee;
                    $totalShipping              += $fee;
                }
            } else {
                $totalShipping = $DEFAULT_SHIPPING;
            }

            return response()->json([
                'success' => true,
                'data'    => [
                    'shipping_fee'     => $totalShipping,
                    'seller_shipping'  => $sellerShippingFees,
                    'default_shipping' => $DEFAULT_SHIPPING,
                    'tax_rate'         => $TAX_RATE,
                    'tax_pct'          => round($TAX_RATE * 100, 2),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('checkoutFees failed: ' . $e->getMessage());
            return response()->json([
                'success' => true,
                'data'    => [
                    'shipping_fee'     => 5000,
                    'seller_shipping'  => [],
                    'default_shipping' => 5000,
                    'tax_rate'         => 0.05,
                    'tax_pct'          => 5.0,
                ],
            ]);
        }
    }

    // ── Private Helpers ────────────────────────────────────────────────────────

    private function getSupplierAddress(int $sellerId): string
    {
        $profile = SellerProfile::where('user_id', $sellerId)->first();
        return $profile?->address ?? 'Supplier Warehouse';
    }

    private function calculateOrderWeight(array $items): float
    {
        return collect($items)->sum(fn($item) => ($item['product']->weight_kg ?? 1) * $item['quantity']);
    }
}

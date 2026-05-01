<?php
// app/Http/Controllers/Api/RfqController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Commission;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Rfq;
use App\Models\RfqQuote;
use App\Models\RfqRecipient;
use App\Models\SellerOrder;
use App\Models\SellerProfile;
use App\Models\User;
use App\Notifications\NewOrderForSeller;
use App\Notifications\OrderPlaced;
use App\Notifications\RfqCreated;
use App\Notifications\RfqQuoteAccepted;
use App\Notifications\RfqQuoteReceived;
use App\Notifications\RfqQuoteRejected;
use App\Services\CommissionRateResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class RfqController extends Controller
{
    // ── BUYER: list my sent RFQs ───────────────────────────────────────────────
    public function listSent(Request $request)
    {
        $user = $request->user();
        $query = Rfq::query();

        if (!$user->hasRole('admin')) {
            $query->where('buyer_id', $user->id);
        }

        $rfqs = $query
            ->withCount('quotes')
            ->with([
                'buyer:id,name,email',
                'acceptedQuote.seller.sellerProfile:user_id,store_name',
                'order:id,order_number,status',   // ← include order reference
            ])
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $rfqs]);
    }

    // ── SELLER: list RFQs visible to me ────────────────────────────────────────
    public function listReceived(Request $request)
    {
        $user   = $request->user();
        $userId = $user->id;

        $query = Rfq::active()->with(['buyer:id,name,email']);

        if ($user->hasRole('admin')) {
            $query->withCount('quotes');
        } else {
            $query->visibleTo($userId)
                ->with([
                    'quotes' => fn($q) => $q->where('seller_id', $userId),
                ]);
        }

        $rfqs = $query->orderByDesc('created_at')->paginate(20);

        // Add my_quote convenience field for sellers.
        if (!$user->hasRole('admin')) {
            $rfqs->getCollection()->transform(function ($rfq) {
                $rfq->my_quote = $rfq->quotes->first();
                unset($rfq->quotes);
                return $rfq;
            });
        }

        return response()->json(['success' => true, 'data' => $rfqs]);
    }

    // ── BOTH: get single RFQ with quotes ───────────────────────────────────────
    public function show(Request $request, $id)
    {
        $user = $request->user();

        $rfq = Rfq::with([
            'buyer:id,name,email',
            'quotes.seller.sellerProfile:user_id,store_name,store_logo',
            'acceptedQuote.seller.sellerProfile:user_id,store_name',
            'order:id,order_number,status',   // ← include order reference
        ])->findOrFail($id);

        // Authorization: must be buyer, OR (seller AND has access)
        $canView = $user->hasRole('admin')
            || $rfq->buyer_id === $user->id
            || $rfq->broadcast
            || $rfq->recipients()->where('seller_id', $user->id)->exists()
            || $rfq->quotes()->where('seller_id', $user->id)->exists();

        if (!$canView) {
            return response()->json(['success' => false, 'message' => 'Not authorized'], 403);
        }

        // For sellers: hide other sellers' quotes — only show their own
        if (!$user->hasRole('admin') && $rfq->buyer_id !== $user->id) {
            $rfq->setRelation(
                'quotes',
                $rfq->quotes->where('seller_id', $user->id)->values()
            );
            // Mark as viewed
            RfqRecipient::where('rfq_id', $rfq->id)
                ->where('seller_id', $user->id)
                ->whereNull('viewed_at')
                ->update(['viewed_at' => now()]);
        }

        return response()->json(['success' => true, 'data' => $rfq]);
    }

    // ── BUYER: create new RFQ ──────────────────────────────────────────────────
    public function create(Request $request)
    {
        $v = Validator::make($request->all(), [
            'product_name'    => 'required|string|max:255',
            'category'        => 'nullable|string|max:100',
            'quantity'        => 'required|numeric|min:0.001',
            'unit'            => 'required|string|max:20',
            'specifications'  => 'nullable|string|max:5000',
            'budget_min'      => 'nullable|numeric|min:0',
            'budget_max'      => 'nullable|numeric|min:0|gte:budget_min',
            'currency'        => 'nullable|string|in:MMK,USD,THB,CNY,EUR',
            'deadline'        => 'required|date|after:today',
            'notes'           => 'nullable|string|max:2000',
            'broadcast'       => 'boolean',
            'seller_ids'      => 'nullable|array',
            'seller_ids.*'    => 'integer|exists:users,id',
        ]);
        if ($v->fails()) return response()->json(['success' => false, 'errors' => $v->errors()], 422);

        $data      = $v->validated();
        $broadcast = $data['broadcast'] ?? true;
        $sellerIds = $data['seller_ids'] ?? [];

        // If not broadcast, require at least one seller
        if (!$broadcast && empty($sellerIds)) {
            return response()->json([
                'success' => false,
                'message' => 'Select at least one seller or enable broadcast.',
            ], 422);
        }

        $rfq = DB::transaction(function () use ($data, $request, $broadcast, $sellerIds) {
            $rfq = Rfq::create([
                'rfq_number'      => Rfq::generateRfqNumber(),
                'buyer_id'        => $request->user()->id,
                'product_name'    => $data['product_name'],
                'category'        => $data['category'] ?? null,
                'quantity'        => $data['quantity'],
                'unit'            => $data['unit'],
                'specifications'  => $data['specifications'] ?? null,
                'budget_min'      => $data['budget_min'] ?? null,
                'budget_max'      => $data['budget_max'] ?? null,
                'currency'        => $data['currency'] ?? 'MMK',
                'deadline'        => $data['deadline'],
                'notes'           => $data['notes'] ?? null,
                'broadcast'       => $broadcast,
                'status'          => Rfq::STATUS_OPEN,
            ]);

            if (!$broadcast) {
                foreach ($sellerIds as $sid) {
                    RfqRecipient::create(['rfq_id' => $rfq->id, 'seller_id' => $sid]);
                }
            }
            return $rfq;
        });

        // ── Notify targeted sellers (non-broadcast only) ───────────────────
        if (!$broadcast && !empty($sellerIds)) {
            $notification = new RfqCreated($rfq->load('buyer'));
            User::whereIn('id', $sellerIds)->each(function ($seller) use ($notification) {
                try {
                    $seller->notify(clone $notification);
                } catch (\Exception $e) {
                    Log::warning("RfqCreated notification failed for seller {$seller->id}: " . $e->getMessage());
                }
            });
        }

        return response()->json([
            'success' => true,
            'message' => 'RFQ created and sent to sellers.',
            'data'    => $rfq->fresh()->load('buyer:id,name'),
        ], 201);
    }

    // ── BUYER: close RFQ (no more quotes accepted) ─────────────────────────────
    public function close(Request $request, $id)
    {
        $rfq = Rfq::where('buyer_id', $request->user()->id)->findOrFail($id);

        if (!$rfq->isOpen()) {
            return response()->json(['success' => false, 'message' => 'RFQ is not open.'], 422);
        }

        $rfq->update(['status' => Rfq::STATUS_CLOSED, 'closed_at' => now()]);

        return response()->json(['success' => true, 'data' => $rfq]);
    }

    // ── BUYER: cancel RFQ ──────────────────────────────────────────────────────
    public function cancel(Request $request, $id)
    {
        $rfq = Rfq::where('buyer_id', $request->user()->id)->findOrFail($id);

        if (in_array($rfq->status, [Rfq::STATUS_ACCEPTED, Rfq::STATUS_CANCELLED])) {
            return response()->json(['success' => false, 'message' => 'Cannot cancel.'], 422);
        }

        $rfq->update(['status' => Rfq::STATUS_CANCELLED, 'closed_at' => now()]);

        return response()->json(['success' => true, 'data' => $rfq]);
    }

    // ── SELLER: submit a quote ─────────────────────────────────────────────────
    public function submitQuote(Request $request, $rfqId)
    {
        $user = $request->user();
        $rfq  = Rfq::findOrFail($rfqId);

        // Sellers only
        if ($rfq->buyer_id === $user->id) {
            return response()->json(['success' => false, 'message' => 'Buyers cannot quote their own RFQs.'], 403);
        }

        // Check visibility
        if (!$rfq->broadcast && !$rfq->recipients()->where('seller_id', $user->id)->exists()) {
            return response()->json(['success' => false, 'message' => 'You are not invited to quote.'], 403);
        }

        // Check RFQ can still receive quotes
        if (!$rfq->canReceiveQuotes()) {
            return response()->json(['success' => false, 'message' => 'RFQ no longer accepts quotes.'], 422);
        }

        $v = Validator::make($request->all(), [
            'unit_price'     => 'required|numeric|min:0',
            'total_price'    => 'required|numeric|min:0',
            'currency'       => 'nullable|string|in:MMK,USD,THB,CNY,EUR',
            'delivery_days'  => 'required|integer|min:1|max:365',
            'validity_days'  => 'nullable|integer|min:1|max:90',
            'notes'          => 'nullable|string|max:2000',
        ]);
        if ($v->fails()) return response()->json(['success' => false, 'errors' => $v->errors()], 422);

        $validity = (int) ($v->validated()['validity_days'] ?? 7);
        $data     = array_merge($v->validated(), [
            'rfq_id'       => $rfq->id,
            'seller_id'    => $user->id,
            'currency'     => $v->validated()['currency'] ?? $rfq->currency,
            'valid_until'  => now()->addDays($validity)->toDateString(),
            'status'       => RfqQuote::STATUS_PENDING,
        ]);

        // updateOrCreate prevents duplicate quotes from the same seller
        $quote = RfqQuote::updateOrCreate(
            ['rfq_id' => $rfq->id, 'seller_id' => $user->id],
            $data
        );

        // Bump RFQ to "quoted" if it was "open"
        if ($rfq->status === Rfq::STATUS_OPEN) {
            $rfq->update(['status' => Rfq::STATUS_QUOTED]);
        }

        // ── Notify the buyer ───────────────────────────────────────────────
        try {
            $rfq->buyer->notify(new RfqQuoteReceived($rfq, $quote->load('seller.sellerProfile')));
        } catch (\Exception $e) {
            Log::warning("RfqQuoteReceived notification failed for buyer {$rfq->buyer_id}: " . $e->getMessage());
        }

        return response()->json(['success' => true, 'data' => $quote->load('seller:id,name')]);
    }

    // ── BUYER: accept a quote ──────────────────────────────────────────────────
    public function acceptQuote(Request $request, $rfqId, $quoteId)
    {
        $rfq = Rfq::with('buyer')->findOrFail($rfqId);

        if ((int) $rfq->buyer_id !== (int) $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Not authorized to accept quotes for this RFQ.'], 403);
        }

        $quote = $rfq->quotes()->findOrFail($quoteId);

        if (!$rfq->isOpen()) {
            return response()->json(['success' => false, 'message' => 'RFQ is not open.'], 422);
        }
        if (!$quote->isPending()) {
            return response()->json(['success' => false, 'message' => 'Quote is no longer pending.'], 422);
        }
        if ($quote->isExpired()) {
            return response()->json(['success' => false, 'message' => 'Quote has expired.'], 422);
        }

        // ── Create the order inside the same transaction ───────────────────────
        $order = DB::transaction(function () use ($rfq, $quote) {

            // ── 1. Accept this quote, reject all others ────────────────────
            $quote->update(['status' => RfqQuote::STATUS_ACCEPTED]);

            $rfq->quotes()
                ->where('id', '!=', $quote->id)
                ->where('status', RfqQuote::STATUS_PENDING)
                ->update(['status' => RfqQuote::STATUS_REJECTED]);

            // ── 2. Resolve commission via the same priority chain as normal orders ──
            //    account_level (tier) → business_type → category → default
            //    No catalogue product exists, so category resolution is skipped
            //    and it naturally falls through to the seller's tier or default.
            $resolved         = app(CommissionRateResolver::class)->resolveForSeller($quote->seller_id, []);
            $commissionRate   = $resolved['rate'];
            $taxRate          = 0.05;

            $subtotal         = (float) $quote->total_price;
            $commissionAmount = round($subtotal * $commissionRate, 2);
            $taxAmount        = round($subtotal * $taxRate, 2);
            $platformRevenue  = $commissionAmount + $taxAmount;
            $sellerPayout     = $subtotal - $commissionAmount;
            $totalAmount      = $subtotal + $taxAmount; // shipping = 0 (negotiated separately)

            // ── 3. Build a minimal shipping address from the buyer's profile ──
            $buyer           = $rfq->buyer;
            $shippingAddress = $this->buildShippingAddress($buyer);

            // ── 4. Create the Order ───────────────────────────────────────
            $orderNumber = 'ORD-' . date('Ymd') . '-' . str_pad(Order::count() + 1, 5, '0', STR_PAD_LEFT);

            $order = Order::create([
                'order_number'     => $orderNumber,
                'buyer_id'         => $rfq->buyer_id,
                'seller_id'        => $quote->seller_id,
                'subtotal_amount'  => $subtotal,
                'shipping_fee'     => 0,     // B2B: delivery terms in quote.delivery_days
                'tax_amount'       => $taxAmount,
                'tax_rate'         => $taxRate,
                'total_amount'     => $totalAmount,
                'status'           => Order::STATUS_PENDING,
                'payment_method'   => Order::PAYMENT_CASH_ON_DELIVERY, // default; buyer settles with seller
                'payment_status'   => Order::PAYMENT_STATUS_PENDING,
                'shipping_address' => $shippingAddress,
                'order_notes'      => implode("\n", array_filter([
                    "RFQ Reference: {$rfq->rfq_number}",
                    "Quoted delivery: {$quote->delivery_days} days",
                    $rfq->notes,
                    $quote->notes,
                ])),
                'commission_rate'   => $commissionRate,
                'commission_amount' => $commissionAmount,
            ]);

            // ── 5. Create the single OrderItem (RFQ line) ─────────────────
            OrderItem::create([
                'order_id'      => $order->id,
                'product_id'    => null,   // no catalogue product; RFQ-sourced
                'product_name'  => $rfq->product_name,
                'product_sku'   => $rfq->rfq_number,
                'quantity_unit' => $rfq->unit,
                'price'         => (float) $quote->unit_price,
                'quantity'      => (float) $rfq->quantity,
                'subtotal'      => $subtotal,
                'product_data'  => [
                    'source'         => 'rfq',
                    'rfq_id'         => $rfq->id,
                    'rfq_number'     => $rfq->rfq_number,
                    'product_name'   => $rfq->product_name,
                    'category'       => $rfq->category,
                    'specifications' => $rfq->specifications,
                    'quantity'       => (float) $rfq->quantity,
                    'unit'           => $rfq->unit,
                    'delivery_days'  => $quote->delivery_days,
                ],
            ]);

            // ── 6. Commission record (admin revenue tracking) ──────────────
            Commission::create([
                'order_id'          => $order->id,
                'seller_id'         => $quote->seller_id,
                'amount'            => $commissionAmount,
                'commission_rate'   => $commissionRate,
                'tax_amount'        => $taxAmount,
                'tax_rate'          => $taxRate,
                'platform_revenue'  => $platformRevenue,
                'seller_payout'     => $sellerPayout,
                'status'            => 'pending',
                'due_date'          => now()->addDays(30),
                'notes'             => "RFQ {$rfq->rfq_number} → Order {$orderNumber}: "
                    . "{$commissionRate}% commission + 5% tax (rule: {$resolved['rule_type']})",
                'commission_rule_id' => $resolved['rule_id'],
            ]);

            // ── 7. SellerOrder sub-record ─────────────────────────────────
            SellerOrder::create([
                'order_id'          => $order->id,
                'seller_id'         => $quote->seller_id,
                'order_number'      => $orderNumber . '-A',
                'subtotal_amount'   => $subtotal,
                'shipping_fee'      => 0,
                'tax_amount'        => $taxAmount,
                'commission_amount' => $commissionAmount,
                'total_amount'      => $totalAmount,
                'delivery_method'   => 'seller',
                'status'            => 'pending',
                'payment_method'    => Order::PAYMENT_CASH_ON_DELIVERY,
                'zone_matched'      => false,
                'fee_source'        => 'rfq',
            ]);

            // ── 8. Mark RFQ as accepted, link order ───────────────────────
            $rfq->update([
                'status'            => Rfq::STATUS_ACCEPTED,
                'accepted_quote_id' => $quote->id,
                'order_id'          => $order->id,
                'closed_at'         => now(),
            ]);

            return $order;
        });

        // ── Notifications (outside transaction so a mail failure doesn't rollback) ──

        // Winning seller: quote accepted + new order
        try {
            $quote->seller->notify(new RfqQuoteAccepted($rfq, $quote, $order));
        } catch (\Exception $e) {
            Log::warning("RfqQuoteAccepted notification failed for seller {$quote->seller_id}: " . $e->getMessage());
        }
        try {
            $quote->seller->notify(new NewOrderForSeller($order->load('items', 'buyer')));
        } catch (\Exception $e) {
            Log::warning("NewOrderForSeller (RFQ) notification failed for seller {$quote->seller_id}: " . $e->getMessage());
        }

        // Auto-rejected sellers (silent — database only)
        $rfq->quotes()
            ->where('id', '!=', $quote->id)
            ->where('status', RfqQuote::STATUS_REJECTED)
            ->with('seller')
            ->get()
            ->each(function ($rejected) use ($rfq) {
                try {
                    $rejected->seller->notify(new RfqQuoteRejected($rfq, $rejected, explicit: false));
                } catch (\Exception $e) {
                    Log::warning("RfqQuoteRejected notification failed for seller {$rejected->seller_id}: " . $e->getMessage());
                }
            });

        return response()->json([
            'success' => true,
            'message' => 'Quote accepted. Order created and seller notified.',
            'data'    => $rfq->fresh()->load([
                'acceptedQuote.seller:id,name',
                'order:id,order_number,status,total_amount',
            ]),
        ]);
    }

    // ── BUYER: reject a quote ──────────────────────────────────────────────────
    public function rejectQuote(Request $request, $rfqId, $quoteId)
    {
        $rfq = Rfq::findOrFail($rfqId);

        if ((int) $rfq->buyer_id !== (int) $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Not authorized to reject quotes for this RFQ.'], 403);
        }

        $quote = $rfq->quotes()->findOrFail($quoteId);

        if (!$quote->isPending()) {
            return response()->json(['success' => false, 'message' => 'Quote is not pending.'], 422);
        }

        $quote->update(['status' => RfqQuote::STATUS_REJECTED]);

        // ── Notify the seller (explicit rejection — sends mail) ────────────
        try {
            $quote->seller->notify(new RfqQuoteRejected($rfq, $quote, explicit: true));
        } catch (\Exception $e) {
            Log::warning("RfqQuoteRejected (explicit) notification failed for seller {$quote->seller_id}: " . $e->getMessage());
        }

        return response()->json(['success' => true, 'data' => $quote]);
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    /**
     * Build a shipping-address array from the buyer User record.
     * Used as the order's shipping_address snapshot for RFQ orders.
     * Actual delivery logistics are negotiated by buyer + seller off-platform.
     */
    private function buildShippingAddress(User $buyer): array
    {
        // Try seller profile address first (some buyers may also be sellers)
        $profile = SellerProfile::where('user_id', $buyer->id)->first();

        return [
            'full_name' => $buyer->name,
            'phone'     => $buyer->phone ?? $profile?->phone ?? '',
            'address'   => $profile?->address ?? '',
            'city'      => $profile?->city ?? '',
            'state'     => $profile?->state ?? '',
            'country'   => $profile?->country ?? 'Myanmar',
            'note'      => 'Delivery address to be confirmed with seller.',
        ];
    }
}
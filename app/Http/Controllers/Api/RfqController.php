<?php
// app/Http/Controllers/Api/RfqController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Rfq;
use App\Models\RfqQuote;
use App\Models\RfqRecipient;
use App\Models\User;
use App\Notifications\RfqCreated;
use App\Notifications\RfqQuoteAccepted;
use App\Notifications\RfqQuoteReceived;
use App\Notifications\RfqQuoteRejected;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class RfqController extends Controller
{
    // ── BUYER: list my sent RFQs ───────────────────────────────────────────────
    public function listSent(Request $request)
    {
        $rfqs = Rfq::where('buyer_id', $request->user()->id)
            ->withCount('quotes')
            ->with(['acceptedQuote.seller.sellerProfile:user_id,store_name'])
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $rfqs]);
    }

    // ── SELLER: list RFQs visible to me ────────────────────────────────────────
    public function listReceived(Request $request)
    {
        $userId = $request->user()->id;

        $rfqs = Rfq::active()
            ->visibleTo($userId)
            ->with([
                'buyer:id,name,email',
                'quotes' => fn($q) => $q->where('seller_id', $userId),
            ])
            ->orderByDesc('created_at')
            ->paginate(20);

        // Add my_quote convenience field
        $rfqs->getCollection()->transform(function ($rfq) use ($userId) {
            $rfq->my_quote = $rfq->quotes->first();
            unset($rfq->quotes);
            return $rfq;
        });

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
        ])->findOrFail($id);

        // Authorization: must be buyer, OR (seller AND has access)
        $canView = $rfq->buyer_id === $user->id
            || $rfq->broadcast
            || $rfq->recipients()->where('seller_id', $user->id)->exists()
            || $rfq->quotes()->where('seller_id', $user->id)->exists();

        if (!$canView) {
            return response()->json(['success' => false, 'message' => 'Not authorized'], 403);
        }

        // For sellers: hide other sellers' quotes — only show their own
        if ($rfq->buyer_id !== $user->id) {
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

        $data = $v->validated();
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
        // Broadcast RFQs are discoverable on the platform — we don't email
        // every seller to avoid spam. Targeted sellers get an immediate alert.
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
        $rfq = Rfq::findOrFail($rfqId);

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

        DB::transaction(function () use ($rfq, $quote) {
            // Accept this quote
            $quote->update(['status' => RfqQuote::STATUS_ACCEPTED]);

            // Reject all other pending quotes
            $rfq->quotes()
                ->where('id', '!=', $quote->id)
                ->where('status', RfqQuote::STATUS_PENDING)
                ->update(['status' => RfqQuote::STATUS_REJECTED]);

            // Mark RFQ as accepted
            $rfq->update([
                'status'            => Rfq::STATUS_ACCEPTED,
                'accepted_quote_id' => $quote->id,
                'closed_at'         => now(),
            ]);
        });

        // ── Notify the winning seller ──────────────────────────────────────
        try {
            $quote->seller->notify(new RfqQuoteAccepted($rfq, $quote));
        } catch (\Exception $e) {
            Log::warning("RfqQuoteAccepted notification failed for seller {$quote->seller_id}: " . $e->getMessage());
        }

        // ── Notify auto-rejected sellers (silent — database only) ─────────
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
            'message' => 'Quote accepted. The seller has been notified.',
            'data'    => $rfq->fresh()->load('acceptedQuote.seller:id,name'),
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
}
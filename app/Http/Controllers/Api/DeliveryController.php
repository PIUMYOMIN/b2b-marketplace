<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CodCommissionInvoice;
use App\Models\Commission;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\SellerWallet;
use App\Notifications\OrderDeliveredThankYou;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DeliveryController extends Controller
{
    /**
     * List deliveries for the authenticated user.
     * Sellers see their own; admin/courier see all or filtered.
     */
    public function index(Request $request)
    {
        try {
            $user     = $request->user();
            $isSeller = $user->type === 'seller'
                || (method_exists($user, 'hasRole') && $user->hasRole('seller'));

            // ── Stats mode (used by DashboardSummary) ──────────────────────
            if ($request->boolean('stats')) {
                $query = Delivery::query();
                if ($isSeller) {
                    $query->where('supplier_id', $user->id);
                }

                $total    = $query->count();
                $byStatus = (clone $query)
                    ->selectRaw('status, count(*) as count')
                    ->groupBy('status')
                    ->pluck('count', 'status')
                    ->toArray();

                return response()->json([
                    'success' => true,
                    'data'    => [
                        'delivery_stats' => [
                            'total'     => $total,
                            'by_status' => $byStatus,
                        ],
                    ],
                ]);
            }

            // ── List mode ──────────────────────────────────────────────────
            $query = Delivery::with(['order', 'supplier', 'platformCourier', 'deliveryUpdates'])
                ->orderBy('created_at', 'desc');

            if ($isSeller) {
                $query->where('supplier_id', $user->id);
            } elseif ($request->has('delivery_method')) {
                $query->where('delivery_method', $request->delivery_method);
            }

            $deliveries = $query->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data'    => $deliveries,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch deliveries: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to fetch deliveries'], 500);
        }
    }

    /**
     * Seller chooses delivery method for an order.
     * POST /seller/delivery/{order}/delivery-method
     */
    public function chooseDeliveryMethod(Request $request, Order $order)
    {
        try {
            $user = $request->user();

            if (
                (int) $order->seller_id   !== (int) $user->id &&
                (int) $order->supplier_id !== (int) $user->id
            ) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            $validated = $request->validate([
                'delivery_method'       => 'required|in:supplier,platform',
                'platform_delivery_fee' => 'nullable|numeric|min:0',
                'pickup_address'        => 'required_if:delivery_method,platform|nullable|string|max:500',
            ]);

            $delivery                       = Delivery::firstOrNew(['order_id' => $order->id]);
            $delivery->order_id             = $order->id;
            $delivery->supplier_id          = $user->id;
            $delivery->delivery_method      = $validated['delivery_method'];
            $delivery->platform_delivery_fee= $validated['platform_delivery_fee'] ?? 0;
            $delivery->pickup_address       = $validated['pickup_address'] ?? $delivery->pickup_address;
            $delivery->delivery_address     = $order->shipping_address ?? '';
            $delivery->status               = 'awaiting_pickup';
            $delivery->tracking_number      = $delivery->tracking_number ?? strtoupper(Str::random(12));
            $delivery->save();

            return response()->json([
                'success' => true,
                'message' => __('messages.delivery.method_set'),
                'data'    => $delivery->load(['order', 'platformCourier']),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to set delivery method: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Update delivery status.
     * POST /deliveries/{delivery}/status
     */
    public function updateStatus(Request $request, Delivery $delivery)
    {
        try {
            $user = $request->user();

            $canUpdate = (int) $delivery->supplier_id === (int) $user->id
                || ($delivery->platform_courier_id && (int) $delivery->platform_courier_id === (int) $user->id)
                || $user->hasRole('admin')
                || $user->type === 'admin';

            if (!$canUpdate) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            $validated = $request->validate([
                'status'   => 'required|in:awaiting_pickup,picked_up,in_transit,out_for_delivery,delivered,failed,cancelled,returned',
                'notes'    => 'nullable|string|max:500',
                'location' => 'nullable|string|max:200',
            ]);

            $delivery->update(['status' => $validated['status']]);

            $delivery->deliveryUpdates()->create([
                'user_id'  => $user->id,
                'status'   => $validated['status'],
                'notes'    => $validated['notes'] ?? null,
                'location' => $validated['location'] ?? null,
            ]);

            return response()->json([
                'success' => true,
                'message' => __('messages.delivery.status_updated'),
                'data'    => $delivery->load(['order', 'deliveryUpdates', 'platformCourier']),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update delivery status: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Upload delivery proof image.
     * POST /deliveries/{delivery}/proof
     *
     * This is the seller's "delivered" trigger. Uploading proof is treated as
     * physical evidence of delivery, so the full order confirmation pipeline
     * fires here — escrow release (digital) or COD invoice (cash), commission
     * collected, and the buyer thank-you email sent.
     *
     * The buyer's own "Confirm Delivery" button remains available but becomes
     * idempotent — the order is already marked delivered so it short-circuits.
     */
    public function uploadDeliveryProof(Request $request, Delivery $delivery)
    {
        try {
            $user = $request->user();

            $canUpdate = (int) $delivery->supplier_id === (int) $user->id
                || ($delivery->platform_courier_id && (int) $delivery->platform_courier_id === (int) $user->id)
                || $user->hasRole('admin')
                || $user->type === 'admin';

            if (!$canUpdate) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            $request->validate([
                'delivery_proof'  => 'required|image|max:5120',
                'recipient_name'  => 'required|string|max:200',
                'recipient_phone' => 'required|string|max:30',
            ]);

            DB::beginTransaction();

            // ── 1. Store the proof image ────────────────────────────────────
            $path = $request->file('delivery_proof')
                ->store("deliveries/{$delivery->id}/proof", 'public');

            $delivery->update([
                'delivery_proof_image' => $path,
                'recipient_name'       => $request->recipient_name,
                'recipient_phone'      => $request->recipient_phone,
                'delivered_at'         => now(),
                'status'               => 'delivered',
            ]);

            $delivery->deliveryUpdates()->create([
                'user_id' => $user->id,
                'status'  => 'delivered',
                'notes'   => 'Delivery proof uploaded by seller. Physical delivery confirmed.',
            ]);

            // ── 2. Run the order confirmation pipeline ─────────────────────
            // Load the linked order. If somehow missing, skip gracefully.
            $order = $delivery->order()->with(['buyer', 'seller.sellerProfile', 'items'])->first();

            if ($order && $order->status !== Order::STATUS_DELIVERED) {

                // Mark the order as delivered
                $order->update([
                    'status'       => Order::STATUS_DELIVERED,
                    'delivered_at' => now(),
                ]);

                // ── 2a. Mark commission collected ───────────────────────────
                Commission::where('order_id', $order->id)
                    ->where('status', 'pending')
                    ->update(['status' => 'collected', 'collected_at' => now()]);

                $commission = Commission::where('order_id', $order->id)->first();

                // ── 2b. Escrow (digital) or COD invoice ─────────────────────
                if ($order->payment_method !== 'cash_on_delivery') {
                    // Release escrow — deduct commission, credit seller payout
                    $wallet = SellerWallet::lockForSeller($order->seller_id);
                    $wallet->releaseEscrow(
                        escrowAmount:     (float) $order->total_amount,
                        sellerPayout:     (float) ($commission?->seller_payout
                                            ?? ($order->subtotal_amount - $order->commission_amount)),
                        commissionAmount: (float) $order->commission_amount,
                        orderId:          $order->id,
                        actorId:          $user->id
                    );
                    $order->update(['escrow_status' => 'released']);
                } else {
                    // COD — seller collected cash from buyer; they now owe
                    // the platform the commission. Raise an invoice.
                    CodCommissionInvoice::create([
                        'invoice_number'    => CodCommissionInvoice::generateInvoiceNumber(),
                        'order_id'          => $order->id,
                        'seller_id'         => $order->seller_id,
                        'order_subtotal'    => $order->subtotal_amount,
                        'commission_rate'   => $order->commission_rate,
                        'commission_amount' => $order->commission_amount,
                        'status'            => 'outstanding',
                        'due_date'          => now()->addDays(7)->toDateString(),
                        'seller_notes'      => "Commission owed for COD order #{$order->order_number}. "
                                            . "Please settle within 7 days via bank transfer.",
                    ]);

                    $wallet = SellerWallet::forSeller($order->seller_id);
                    $wallet->increment('cod_commission_outstanding', $order->commission_amount);
                    $wallet->transactions()->create([
                        'order_id'               => $order->id,
                        'type'                   => 'cod_invoice',
                        'amount'                 => -(float) $order->commission_amount,
                        'escrow_balance_after'   => $wallet->escrow_balance,
                        'available_balance_after'=> $wallet->available_balance,
                        'notes'                  => "COD commission invoice raised for order "
                                                  . "#{$order->order_number}. "
                                                  . "Amount: {$order->commission_amount} MMK. "
                                                  . "Due: " . now()->addDays(7)->toDateString(),
                        'created_by'             => $user->id,
                    ]);
                }
            }

            DB::commit();

            // ── 3. Thank-you email — outside the transaction so a mail failure
            //       never rolls back the financial records ───────────────────
            if ($order) {
                try {
                    // Re-load relations for the email template
                    $order->load('items', 'buyer', 'seller.sellerProfile');
                    $order->buyer->notify(new OrderDeliveredThankYou($order));
                } catch (\Exception $mailEx) {
                    // Log but never fail the request over an email issue
                    Log::warning('OrderDeliveredThankYou notification failed after proof upload: '
                        . $mailEx->getMessage(), ['order_id' => $order->id]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => __('messages.delivery.proof_uploaded'),
                'data'    => $delivery->fresh(),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to upload delivery proof: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Assign a platform courier to a delivery.
     * POST /deliveries/{delivery}/assign-courier
     */
    public function assignCourier(Request $request, Delivery $delivery)
    {
        try {
            $user = $request->user();

            if (!$user->hasRole('admin') && (int) $delivery->supplier_id !== (int) $user->id) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            $validated = $request->validate([
                'platform_courier_id' => 'required|exists:users,id',
                'driver_name'         => 'nullable|string|max:200',
                'driver_phone'        => 'nullable|string|max:30',
                'vehicle_type'        => 'nullable|string|max:100',
                'vehicle_number'      => 'nullable|string|max:50',
            ]);

            $delivery->update([
                'platform_courier_id'     => $validated['platform_courier_id'],
                'assigned_driver_name'    => $validated['driver_name']    ?? null,
                'assigned_driver_phone'   => $validated['driver_phone']   ?? null,
                'assigned_vehicle_type'   => $validated['vehicle_type']   ?? null,
                'assigned_vehicle_number' => $validated['vehicle_number'] ?? null,
                'status'                  => 'awaiting_pickup',
            ]);

            return response()->json([
                'success' => true,
                'message' => __('messages.delivery.courier_assigned'),
                'data'    => $delivery->fresh(['platformCourier']),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to assign courier: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get tracking timeline for a delivery.
     * GET /deliveries/{delivery}/tracking
     */
    public function getTrackingUpdates(Delivery $delivery)
    {
        try {
            $updates = $delivery->deliveryUpdates()
                ->with('user:id,name')
                ->orderBy('created_at', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data'    => [
                    'delivery' => $delivery->load(['order', 'platformCourier']),
                    'updates'  => $updates,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get tracking updates: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to fetch tracking'], 500);
        }
    }
}

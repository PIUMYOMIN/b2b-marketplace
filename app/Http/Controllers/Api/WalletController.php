<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CodCommissionInvoice;
use App\Models\SellerWallet;
use App\Models\WalletTransaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WalletController extends Controller
{
    // ── Seller: own wallet summary ─────────────────────────────────────────────

    /**
     * GET /seller/wallet
     * Returns the authenticated seller's wallet balances and recent transactions.
     */
    public function sellerSummary(Request $request)
    {
        $user   = Auth::user();
        $wallet = SellerWallet::forSeller($user->id);

        $recentTx = $wallet->transactions()
            ->with('order:id,order_number')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(fn($tx) => [
                'id'                      => $tx->id,
                'type'                    => $tx->type,
                'type_label'              => $tx->type_label,
                'amount'                  => $tx->amount,
                'escrow_balance_after'    => $tx->escrow_balance_after,
                'available_balance_after' => $tx->available_balance_after,
                'order_number'            => $tx->order?->order_number,
                'notes'                   => $tx->notes,
                'created_at'              => $tx->created_at->toISOString(),
            ]);

        // COD invoice summary
        $codOutstanding = CodCommissionInvoice::where('seller_id', $user->id)
            ->whereIn('status', ['outstanding', 'overdue'])
            ->sum('commission_amount');

        $codOverdueCount = CodCommissionInvoice::where('seller_id', $user->id)
            ->where('status', 'outstanding')
            ->where('due_date', '<', now()->toDateString())
            ->count();

        return response()->json([
            'success' => true,
            'data'    => [
                'wallet'           => [
                    'escrow_balance'             => (float) $wallet->escrow_balance,
                    'available_balance'          => (float) $wallet->available_balance,
                    'total_earned'               => (float) $wallet->total_earned,
                    'total_commission_paid'      => (float) $wallet->total_commission_paid,
                    'cod_commission_outstanding' => (float) $codOutstanding,
                    'cod_overdue_count'          => $codOverdueCount,
                ],
                'recent_transactions' => $recentTx,
            ],
        ]);
    }

    // ── Seller: COD invoices ───────────────────────────────────────────────────

    /**
     * GET /seller/cod-invoices
     */
    public function sellerCodInvoices(Request $request)
    {
        $user = Auth::user();

        $query = CodCommissionInvoice::with('order:id,order_number,created_at')
            ->where('seller_id', $user->id)
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $invoices = $query->paginate($request->get('per_page', 15));

        // Auto-mark overdue
        CodCommissionInvoice::where('seller_id', $user->id)
            ->where('status', 'outstanding')
            ->where('due_date', '<', now()->toDateString())
            ->update(['status' => 'overdue']);

        return response()->json(['success' => true, 'data' => $invoices]);
    }

    /**
     * POST /seller/cod-invoices/{invoice}/submit-payment
     *
     * Seller submits evidence that they have paid the COD commission.
     * Admin must then confirm it.
     */
    public function submitCodPayment(Request $request, CodCommissionInvoice $invoice)
    {
        $user = Auth::user();

        if ((int) $invoice->seller_id !== (int) $user->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        if (!in_array($invoice->status, ['outstanding', 'overdue'])) {
            return response()->json(['success' => false, 'message' => 'Invoice is not payable in its current status.'], 400);
        }

        $request->validate([
            'payment_reference' => 'required|string|max:100',
            'payment_method'    => 'required|string|in:kbz_pay,wave_pay,cb_pay,aya_pay,bank_transfer',
            'seller_notes'      => 'nullable|string|max:500',
        ]);

        $invoice->update([
            'payment_reference' => $request->payment_reference,
            'payment_method'    => $request->payment_method,
            'seller_notes'      => $request->seller_notes,
            'paid_at'           => now(),
            // Status remains outstanding/overdue until admin confirms
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Payment submitted. Awaiting admin confirmation.',
            'data'    => $invoice->fresh(),
        ]);
    }

    // ── Admin: all wallets overview ────────────────────────────────────────────

    /**
     * GET /admin/wallets
     */
    public function adminIndex(Request $request)
    {
        $user = Auth::user();
        if (!$user->hasRole('admin') && $user->type !== 'admin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $wallets = SellerWallet::with('seller:id,name,email')
            ->orderByDesc('available_balance')
            ->paginate($request->get('per_page', 20));

        $platformTotals = [
            'total_escrow_held'          => SellerWallet::sum('escrow_balance'),
            'total_available_to_sellers' => SellerWallet::sum('available_balance'),
            'total_commission_collected' => SellerWallet::sum('total_commission_paid'),
            'total_cod_outstanding'      => CodCommissionInvoice::whereIn('status', ['outstanding', 'overdue'])->sum('commission_amount'),
        ];

        return response()->json([
            'success' => true,
            'data'    => [
                'platform_totals' => $platformTotals,
                'wallets'         => $wallets,
            ],
        ]);
    }

    /**
     * GET /admin/wallets/{seller}
     */
    public function adminSellerWallet(Request $request, User $seller)
    {
        $user = Auth::user();
        if (!$user->hasRole('admin') && $user->type !== 'admin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $wallet = SellerWallet::forSeller($seller->id);

        $transactions = $wallet->transactions()
            ->with('order:id,order_number')
            ->orderByDesc('created_at')
            ->paginate($request->get('per_page', 30));

        $codInvoices = CodCommissionInvoice::where('seller_id', $seller->id)
            ->with('order:id,order_number')
            ->orderByDesc('created_at')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data'    => [
                'seller'       => ['id' => $seller->id, 'name' => $seller->name, 'email' => $seller->email],
                'wallet'       => $wallet,
                'transactions' => $transactions,
                'cod_invoices' => $codInvoices,
            ],
        ]);
    }

    // ── Admin: COD invoice management ─────────────────────────────────────────

    /**
     * GET /admin/cod-invoices
     */
    public function adminCodInvoices(Request $request)
    {
        $user = Auth::user();
        if (!$user->hasRole('admin') && $user->type !== 'admin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        // Auto-mark overdue globally
        CodCommissionInvoice::where('status', 'outstanding')
            ->where('due_date', '<', now()->toDateString())
            ->update(['status' => 'overdue']);

        $query = CodCommissionInvoice::with([
            'seller:id,name,email',
            'order:id,order_number,created_at',
        ])->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('seller_id')) {
            $query->where('seller_id', $request->seller_id);
        }

        $invoices = $query->paginate($request->get('per_page', 20));

        $summary = [
            'outstanding_count'  => CodCommissionInvoice::where('status', 'outstanding')->count(),
            'overdue_count'      => CodCommissionInvoice::where('status', 'overdue')->count(),
            'outstanding_amount' => CodCommissionInvoice::whereIn('status', ['outstanding', 'overdue'])->sum('commission_amount'),
            'collected_this_month' => CodCommissionInvoice::where('status', 'paid')
                ->whereMonth('confirmed_at', now()->month)
                ->sum('commission_amount'),
        ];

        return response()->json(['success' => true, 'data' => ['summary' => $summary, 'invoices' => $invoices]]);
    }

    /**
     * POST /admin/cod-invoices/{invoice}/confirm-payment
     *
     * Admin confirms they have received the seller's COD commission payment.
     */
    public function adminConfirmCodPayment(Request $request, CodCommissionInvoice $invoice)
    {
        $user = Auth::user();
        if (!$user->hasRole('admin') && $user->type !== 'admin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        if (!in_array($invoice->status, ['outstanding', 'overdue'])) {
            return response()->json(['success' => false, 'message' => 'Invoice is already ' . $invoice->status], 400);
        }

        $request->validate(['admin_notes' => 'nullable|string|max:500']);

        DB::beginTransaction();
        try {
            $invoice->update([
                'status'       => 'paid',
                'confirmed_by' => $user->id,
                'confirmed_at' => now(),
                'admin_notes'  => $request->admin_notes,
            ]);

            // Reduce COD outstanding on seller wallet
            $wallet = SellerWallet::forSeller($invoice->seller_id);
            if ((float) $wallet->cod_commission_outstanding >= (float) $invoice->commission_amount) {
                $wallet->decrement('cod_commission_outstanding', $invoice->commission_amount);
            }

            // Record payment in wallet ledger
            $wallet->refresh();
            $wallet->transactions()->create([
                'order_id'               => $invoice->order_id,
                'type'                   => 'cod_payment',
                'amount'                 => $invoice->commission_amount,
                'escrow_balance_after'   => $wallet->escrow_balance,
                'available_balance_after'=> $wallet->available_balance,
                'reference'              => $invoice->payment_reference,
                'notes'                  => "COD commission received for invoice #{$invoice->invoice_number}",
                'created_by'             => $user->id,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "COD commission of " . number_format($invoice->commission_amount, 0) . " MMK confirmed for invoice #{$invoice->invoice_number}.",
                'data'    => $invoice->fresh(['seller', 'order', 'confirmedByAdmin']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('COD invoice confirmation failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /admin/cod-invoices/{invoice}/waive
     */
    public function adminWaiveCodInvoice(Request $request, CodCommissionInvoice $invoice)
    {
        $user = Auth::user();
        if (!$user->hasRole('admin') && $user->type !== 'admin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $request->validate(['admin_notes' => 'required|string|max:500']);

        DB::beginTransaction();
        try {
            $invoice->update([
                'status'       => 'waived',
                'confirmed_by' => $user->id,
                'confirmed_at' => now(),
                'admin_notes'  => $request->admin_notes,
            ]);

            $wallet = SellerWallet::forSeller($invoice->seller_id);
            if ((float) $wallet->cod_commission_outstanding >= (float) $invoice->commission_amount) {
                $wallet->decrement('cod_commission_outstanding', $invoice->commission_amount);
            }

            $wallet->refresh();
            $wallet->transactions()->create([
                'order_id'               => $invoice->order_id,
                'type'                   => 'adjustment',
                'amount'                 => 0,
                'escrow_balance_after'   => $wallet->escrow_balance,
                'available_balance_after'=> $wallet->available_balance,
                'notes'                  => "COD invoice #{$invoice->invoice_number} waived by admin. Reason: {$request->admin_notes}",
                'created_by'             => $user->id,
            ]);

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Invoice waived.', 'data' => $invoice->fresh()]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ── Admin: delivery fee collection ────────────────────────────────────────

    /**
     * POST /admin/deliveries/{delivery}/collect-fee
     *
     * Admin marks a platform delivery fee as collected (manual process).
     */
    public function collectDeliveryFee(Request $request, \App\Models\Delivery $delivery)
    {
        $user = Auth::user();
        if (!$user->hasRole('admin') && $user->type !== 'admin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        if ($delivery->delivery_method !== 'platform') {
            return response()->json(['success' => false, 'message' => 'This delivery is not a platform-managed delivery.'], 400);
        }

        if ($delivery->delivery_fee_status === 'collected') {
            return response()->json(['success' => false, 'message' => 'Delivery fee already marked as collected.'], 400);
        }

        $request->validate([
            'collection_ref' => 'required|string|max:100',
            'admin_notes'    => 'nullable|string|max:500',
        ]);

        $delivery->update([
            'delivery_fee_status'        => 'collected',
            'delivery_fee_collected_at'  => now(),
            'delivery_fee_collected_by'  => $user->id,
            'delivery_fee_collection_ref'=> $request->collection_ref,
            'fee_confirmation_note'      => $request->admin_notes,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Delivery fee of ' . number_format($delivery->platform_delivery_fee, 0) . ' MMK marked as collected.',
            'data'    => $delivery->fresh(),
        ]);
    }

    /**
     * GET /admin/delivery-fees
     * Returns all platform deliveries with outstanding vs collected fee status.
     */
    public function deliveryFeeReport(Request $request)
    {
        $user = Auth::user();
        if (!$user->hasRole('admin') && $user->type !== 'admin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $query = \App\Models\Delivery::with([
            'order:id,order_number,seller_id,total_amount',
            'supplier:id,name,email',
        ])
            ->where('delivery_method', 'platform')
            ->orderByDesc('created_at');

        if ($request->filled('fee_status')) {
            $query->where('delivery_fee_status', $request->fee_status);
        }

        $deliveries = $query->paginate($request->get('per_page', 20));

        $summary = [
            'outstanding_count'  => \App\Models\Delivery::where('delivery_method', 'platform')->where('delivery_fee_status', 'outstanding')->count(),
            'outstanding_amount' => \App\Models\Delivery::where('delivery_method', 'platform')->where('delivery_fee_status', 'outstanding')->sum('platform_delivery_fee'),
            'collected_count'    => \App\Models\Delivery::where('delivery_method', 'platform')->where('delivery_fee_status', 'collected')->count(),
            'collected_amount'   => \App\Models\Delivery::where('delivery_method', 'platform')->where('delivery_fee_status', 'collected')->sum('platform_delivery_fee'),
        ];

        return response()->json(['success' => true, 'data' => ['summary' => $summary, 'deliveries' => $deliveries]]);
    }
}

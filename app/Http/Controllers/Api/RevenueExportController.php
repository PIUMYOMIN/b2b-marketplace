<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Commission;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\OrderItem;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RevenueExportController extends Controller
{
    // ── Period resolver ────────────────────────────────────────────────────────

    private function resolvePeriod(Request $request): array
    {
        $period = $request->input('period', 'month');
        $now    = Carbon::now();

        switch ($period) {
            case 'today':
                return [$now->copy()->startOfDay(), $now->copy()->endOfDay(), 'today'];
            case 'yesterday':
                return [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay(), 'yesterday'];
            case 'week':
                return [$now->copy()->startOfWeek(), $now->copy()->endOfWeek(), 'this_week'];
            case 'last_week':
                return [$now->copy()->subWeek()->startOfWeek(), $now->copy()->subWeek()->endOfWeek(), 'last_week'];
            case 'month':
                return [$now->copy()->startOfMonth(), $now->copy()->endOfMonth(), 'this_month'];
            case 'last_month':
                return [$now->copy()->subMonth()->startOfMonth(), $now->copy()->subMonth()->endOfMonth(), 'last_month'];
            case 'quarter':
                return [$now->copy()->startOfQuarter(), $now->copy()->endOfQuarter(), 'this_quarter'];
            case 'year':
                return [$now->copy()->startOfYear(), $now->copy()->endOfYear(), 'this_year'];
            case 'custom':
                $request->validate([
                    'from' => 'required|date',
                    'to'   => 'required|date|after_or_equal:from',
                ]);
                return [
                    Carbon::parse($request->from)->startOfDay(),
                    Carbon::parse($request->to)->endOfDay(),
                    'custom',
                ];
            default:
                return [$now->copy()->startOfMonth(), $now->copy()->endOfMonth(), 'this_month'];
        }
    }

    // ── Shared order builder ───────────────────────────────────────────────────

    /**
     * Build full order data with product items, buyer, seller, commission,
     * and delivery fee for each order in the given date range.
     */
    private function buildOrderReport(Carbon $start, Carbon $end, ?int $sellerId = null): array
    {
        $query = Order::with([
            'buyer:id,name,email,phone',
            'seller:id,name,email',
            'seller.sellerProfile:user_id,store_name',
            'items:id,order_id,product_id,product_name,quantity,price,subtotal',
            'commission:id,order_id,amount,commission_rate,status,platform_revenue,seller_payout,tax_amount',
        ])
        ->whereBetween('created_at', [$start, $end])
        ->orderByDesc('created_at');

        if ($sellerId) {
            $query->where('seller_id', $sellerId);
        }

        $orders = $query->get();

        // Load delivery fees per order
        $deliveryMap = Delivery::whereIn('order_id', $orders->pluck('id'))
            ->where('delivery_method', 'platform')
            ->get(['order_id', 'platform_delivery_fee', 'delivery_fee_status',
                   'fee_submitted_at', 'fee_confirmed_at'])
            ->keyBy('order_id');

        $rows = [];
        foreach ($orders as $order) {
            $comm     = $order->commission;
            $delivery = $deliveryMap[$order->id] ?? null;

            $commStatus    = $comm?->status ?? 'pending';
            $commPending   = in_array($commStatus, ['pending', 'due']) ? (float) $order->commission_amount : 0;
            $commConfirmed = in_array($commStatus, ['collected', 'paid']) ? (float) $order->commission_amount : 0;

            $delivFee       = (float) ($delivery?->platform_delivery_fee ?? 0);
            $feeStatus      = $delivery?->delivery_fee_status ?? 'not_applicable';
            $delivPending   = ($feeStatus === 'outstanding') ? $delivFee : 0;
            $delivConfirmed = ($feeStatus === 'collected')   ? $delivFee : 0;

            // Collapse items into a readable string and also as structured array
            $itemLines = $order->items->map(fn($i) =>
                "{$i->product_name} ×{$i->quantity} @ " . number_format($i->price) . " MMK"
            )->implode(' | ');

            $itemsArray = $order->items->map(fn($i) => [
                'name'     => $i->product_name,
                'qty'      => $i->quantity,
                'price'    => (float) $i->price,
                'subtotal' => (float) $i->subtotal,
            ])->values()->toArray();

            $rows[] = [
                // Identifiers
                'order_id'           => $order->id,
                'order_number'       => $order->order_number,
                'order_date'         => $order->created_at->toDateTimeString(),
                'delivered_at'       => $order->delivered_at?->toDateTimeString(),

                // Parties
                'buyer_name'         => $order->buyer?->name ?? '—',
                'buyer_email'        => $order->buyer?->email ?? '—',
                'seller_name'        => $order->seller?->sellerProfile?->store_name
                                        ?? $order->seller?->name ?? '—',
                'seller_email'       => $order->seller?->email ?? '—',

                // Items
                'items_summary'      => $itemLines ?: '—',
                'items'              => $itemsArray,
                'items_count'        => $order->items->count(),

                // Financials
                'subtotal'           => (float) $order->subtotal_amount,
                'shipping_fee'       => (float) $order->shipping_fee,
                'tax_amount'         => (float) $order->tax_amount,
                'coupon_discount'    => (float) ($order->coupon_discount_amount ?? 0),
                'total_amount'       => (float) $order->total_amount,

                // Commission
                'commission_rate'    => (float) $order->commission_rate,
                'commission_amount'  => (float) $order->commission_amount,
                'commission_status'  => $commStatus,
                'commission_pending'    => $commPending,
                'commission_confirmed'  => $commConfirmed,
                'seller_payout'      => (float) ($comm?->seller_payout ?? ($order->subtotal_amount - $order->commission_amount)),

                // Delivery fee (platform delivery only)
                'delivery_fee'          => $delivFee,
                'delivery_fee_status'   => $feeStatus,
                'delivery_fee_pending'  => $delivPending,
                'delivery_fee_confirmed'=> $delivConfirmed,

                // Status
                'order_status'       => $order->status,
                'payment_method'     => $order->payment_method,
                'payment_status'     => $order->payment_status,
                'escrow_status'      => $order->escrow_status ?? '—',
            ];
        }

        return $rows;
    }

    // ── Summary aggregates ─────────────────────────────────────────────────────

    private function buildSummary(array $rows, string $period, Carbon $start, Carbon $end): array
    {
        $total        = count($rows);
        $delivered    = count(array_filter($rows, fn($r) => $r['order_status'] === 'delivered'));
        $pending      = count(array_filter($rows, fn($r) => $r['order_status'] === 'pending'));
        $cancelled    = count(array_filter($rows, fn($r) => $r['order_status'] === 'cancelled'));

        $sum = fn($key) => array_sum(array_column($rows, $key));

        return [
            'period'             => $period,
            'from'               => $start->toDateString(),
            'to'                 => $end->toDateString(),

            // Orders
            'total_orders'       => $total,
            'delivered_orders'   => $delivered,
            'pending_orders'     => $pending,
            'cancelled_orders'   => $cancelled,

            // GMV
            'total_gmv'          => $sum('total_amount'),
            'total_subtotal'     => $sum('subtotal'),
            'total_shipping'     => $sum('shipping_fee'),
            'total_tax'          => $sum('tax_amount'),
            'total_coupon_discount' => $sum('coupon_discount'),

            // Commission
            'total_commission'          => $sum('commission_amount'),
            'total_commission_pending'  => $sum('commission_pending'),
            'total_commission_confirmed'=> $sum('commission_confirmed'),
            'total_seller_payout'       => $sum('seller_payout'),

            // Delivery fees
            'total_delivery_fees'           => $sum('delivery_fee'),
            'total_delivery_fees_pending'   => $sum('delivery_fee_pending'),
            'total_delivery_fees_confirmed' => $sum('delivery_fee_confirmed'),

            // Platform revenue
            'platform_revenue' => $sum('commission_confirmed') + $sum('delivery_fee_confirmed'),
            'platform_revenue_pending' => $sum('commission_pending') + $sum('delivery_fee_pending'),
        ];
    }

    // ── Admin: Full financial report ───────────────────────────────────────────

    /**
     * GET /admin/financial-report
     * ?period=today|yesterday|week|last_week|month|last_month|quarter|year|custom
     * &from=YYYY-MM-DD  (custom only)
     * &to=YYYY-MM-DD    (custom only)
     * &seller_id=       (optional filter)
     * &group_by=day|week|month  (for trend data)
     */
    public function adminReport(Request $request)
    {
        try {
            [$start, $end, $periodLabel] = $this->resolvePeriod($request);
            $sellerId = $request->input('seller_id');
            $groupBy  = $request->input('group_by', 'day');

            $rows    = $this->buildOrderReport($start, $end, $sellerId ? (int) $sellerId : null);
            $summary = $this->buildSummary($rows, $periodLabel, $start, $end);

            // Trend data (grouped)
            $trend = $this->buildTrend($rows, $groupBy, $start, $end);

            return response()->json([
                'success' => true,
                'summary' => $summary,
                'trend'   => $trend,
                'orders'  => $rows,
            ]);
        } catch (\Exception $e) {
            Log::error('adminReport failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ── Seller: Own financial report ───────────────────────────────────────────

    /**
     * GET /seller/financial-report
     */
    public function sellerReport(Request $request)
    {
        try {
            $user = $request->user();
            if (!$user->hasRole('seller')) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            [$start, $end, $periodLabel] = $this->resolvePeriod($request);
            $groupBy = $request->input('group_by', 'day');

            $rows    = $this->buildOrderReport($start, $end, $user->id);
            $summary = $this->buildSummary($rows, $periodLabel, $start, $end);
            $trend   = $this->buildTrend($rows, $groupBy, $start, $end);

            // Add wallet snapshot for sellers
            try {
                $wallet = \App\Models\SellerWallet::forSeller($user->id);
                $summary['wallet'] = [
                    'escrow_balance'             => (float) $wallet->escrow_balance,
                    'available_balance'          => (float) $wallet->available_balance,
                    'total_earned'               => (float) $wallet->total_earned,
                    'total_commission_paid'      => (float) $wallet->total_commission_paid,
                    'cod_commission_outstanding' => (float) $wallet->cod_commission_outstanding,
                ];
            } catch (\Exception $we) {
                $summary['wallet'] = null;
            }

            return response()->json([
                'success' => true,
                'summary' => $summary,
                'trend'   => $trend,
                'orders'  => $rows,
            ]);
        } catch (\Exception $e) {
            Log::error('sellerReport failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ── Trend builder ──────────────────────────────────────────────────────────

    private function buildTrend(array $rows, string $groupBy, Carbon $start, Carbon $end): array
    {
        // Bucket each order into a period key
        $buckets = [];
        foreach ($rows as $row) {
            $dt  = Carbon::parse($row['order_date']);
            $key = match($groupBy) {
                'week'  => $dt->format('Y-\WW'),
                'month' => $dt->format('Y-m'),
                default => $dt->format('Y-m-d'),
            };
            if (!isset($buckets[$key])) {
                $buckets[$key] = [
                    'period'       => $key,
                    'orders'       => 0,
                    'gmv'          => 0,
                    'tax'          => 0,
                    'commission'   => 0,
                    'delivery_fee' => 0,
                    'platform'     => 0,
                ];
            }
            $buckets[$key]['orders']++;
            $buckets[$key]['gmv']          += $row['total_amount'];
            $buckets[$key]['tax']          += $row['tax_amount'];
            $buckets[$key]['commission']   += $row['commission_amount'];
            $buckets[$key]['delivery_fee'] += $row['delivery_fee'];
            $buckets[$key]['platform']     += $row['commission_confirmed'] + $row['delivery_fee_confirmed'];
        }

        // Fill empty days/weeks/months in range with zeros
        $filled  = [];
        $current = $start->copy();
        while ($current <= $end) {
            $key = match($groupBy) {
                'week'  => $current->format('Y-\WW'),
                'month' => $current->format('Y-m'),
                default => $current->format('Y-m-d'),
            };
            $filled[$key] = $buckets[$key] ?? [
                'period' => $key, 'orders' => 0, 'gmv' => 0,
                'tax' => 0, 'commission' => 0, 'delivery_fee' => 0, 'platform' => 0,
            ];
            match($groupBy) {
                'week'  => $current->addWeek(),
                'month' => $current->addMonth(),
                default => $current->addDay(),
            };
        }

        return array_values($filled);
    }

    // ── Legacy endpoints (backward compat) ─────────────────────────────────────

    public function adminExport(Request $request)
    {
        return $this->adminReport($request);
    }

    public function sellerExport(Request $request)
    {
        return $this->sellerReport($request);
    }
}

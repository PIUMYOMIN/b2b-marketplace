<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RevenueExportController extends Controller
{
    /**
     * GET /admin/revenue/export
     *
     * Returns a flat list of orders with revenue breakdown for the admin
     * to export. Supports optional ?from=YYYY-MM-DD&to=YYYY-MM-DD filters.
     */
    public function adminExport(Request $request)
    {
        $request->validate([
            'from' => 'nullable|date',
            'to'   => 'nullable|date|after_or_equal:from',
        ]);

        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to   = $request->input('to',   now()->endOfMonth()->toDateString());

        $orders = Order::with(['seller:id,name,email'])
            ->whereBetween(DB::raw('DATE(created_at)'), [$from, $to])
            ->orderByDesc('created_at')
            ->get([
                'id',
                'order_number',
                'seller_id',
                'buyer_id',
                'total_amount',
                'subtotal_amount',
                'shipping_fee',
                'tax_amount',
                'commission_amount',
                'commission_rate',
                'status',
                'payment_status',
                'created_at',
                'delivered_at',
            ]);

        $summary = [
            'total_orders'     => $orders->count(),
            'total_gmv'        => $orders->sum('total_amount'),
            'total_commission' => $orders->sum('commission_amount'),
            'total_tax'        => $orders->sum('tax_amount'),
            'period_from'      => $from,
            'period_to'        => $to,
        ];

        return response()->json([
            'success' => true,
            'summary' => $summary,
            'data'    => $orders,
        ]);
    }

    /**
     * GET /seller/revenue/export
     *
     * Returns the authenticated seller's orders with revenue breakdown.
     * Supports optional ?from=YYYY-MM-DD&to=YYYY-MM-DD filters.
     */
    public function sellerExport(Request $request)
    {
        $request->validate([
            'from' => 'nullable|date',
            'to'   => 'nullable|date|after_or_equal:from',
        ]);

        $from     = $request->input('from', now()->startOfMonth()->toDateString());
        $to       = $request->input('to',   now()->endOfMonth()->toDateString());
        $sellerId = $request->user()->id;

        $orders = Order::where('seller_id', $sellerId)
            ->whereBetween(DB::raw('DATE(created_at)'), [$from, $to])
            ->orderByDesc('created_at')
            ->get([
                'id',
                'order_number',
                'total_amount',
                'subtotal_amount',
                'shipping_fee',
                'tax_amount',
                'commission_amount',
                'commission_rate',
                'status',
                'payment_status',
                'created_at',
                'delivered_at',
            ]);

        $summary = [
            'total_orders'     => $orders->count(),
            'total_revenue'    => $orders->sum('total_amount'),
            'total_commission' => $orders->sum('commission_amount'),
            'net_revenue'      => $orders->sum('total_amount') - $orders->sum('commission_amount'),
            'period_from'      => $from,
            'period_to'        => $to,
        ];

        return response()->json([
            'success' => true,
            'summary' => $summary,
            'data'    => $orders,
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use Carbon\Carbon;
use App\Models\User;
use App\Models\Order;
use App\Models\Product;
use App\Models\OrderItem;
use App\Models\Commission;
use Illuminate\Http\Request;
use App\Models\SellerProfile;
use App\Models\BusinessType;
use App\Models\Delivery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;

class DashboardController extends Controller
{
    /**
     * Get general dashboard data
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();

            if ($user->hasRole('seller')) {
                // Return seller dashboard data
                $salesSummary = $this->sellerSalesSummary($request);
                $topProducts = $this->sellerTopProducts($request);
                $recentOrders = $this->sellerRecentOrders($request);

                return response()->json([
                    'success' => true,
                    'data' => [
                        'type' => 'seller',
                        'sales_summary' => $salesSummary->getData()->data ?? [],
                        'top_products' => $topProducts->getData()->data ?? [],
                        'recent_orders' => $recentOrders->getData()->data ?? []
                    ]
                ]);
            } else {
                // Return admin dashboard data
                return response()->json([
                    'success' => true,
                    'data' => [
                        'type' => 'admin',
                        'message' => 'Admin dashboard data would go here'
                    ]
                ]);
            }

        } catch (\Exception $e) {
            \Log::error('Error in dashboard index: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch dashboard data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Admin: Get all seller profiles for management
     */
    public function getSellers(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'per_page' => 'sometimes|integer|min:1|max:100',
            'status' => 'sometimes|in:pending,approved,active,suspended,closed',
            'search' => 'sometimes|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $perPage = $request->input('per_page', 15);

        $query = SellerProfile::with(['user', 'reviews'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->withCount('products');

        // Filter by status if provided
        if ($request->has('status') && !empty($request->status)) {
            $query->where('status', $request->status);
        }

        // Search filter
        if ($request->has('search') && !empty($request->search)) {
            $query->where(function($q) use ($request) {
                $q->where('store_name', 'like', '%'.$request->search.'%')
                  ->orWhere('store_id', 'like', '%'.$request->search.'%')
                  ->orWhere('contact_email', 'like', '%'.$request->search.'%')
                  ->orWhereHas('user', function($userQuery) use ($request) {
                      $userQuery->where('name', 'like', '%'.$request->search.'%')
                               ->orWhere('email', 'like', '%'.$request->search.'%');
                  });
            });
        }

        $sellers = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $sellers
        ]);
    }

    /**
     * Admin: Approve seller profile
     */
    public function adminApprove($id)
    {
        try {
            $seller = SellerProfile::findOrFail($id);
            $seller->update(['status' => 'approved']);

            return response()->json([
                'success' => true,
                'message' => 'Seller profile approved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve seller profile'
            ], 500);
        }
    }

    /**
     * Admin: Reject seller profile
     */
    public function adminReject($id, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $seller = SellerProfile::findOrFail($id);
            $seller->update([
                'status' => 'rejected',
                'admin_notes' => $request->reason
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Seller profile rejected successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject seller profile'
            ], 500);
        }
    }


    /**
     * Get overall statistics for dashboard
     */
    public function stats()
    {
        // Commission stats
        $totalCommission   = Commission::sum('amount');
        $pendingComm       = Commission::where('status', 'pending')->sum('amount');
        $collectedComm     = Commission::whereIn('status', ['collected', 'paid'])->sum('amount');

        // Delivery fee stats
        $totalDeliveryFees     = Delivery::where('delivery_method', 'platform')->sum('platform_delivery_fee');
        $confirmedDeliveryFees = Delivery::where('delivery_method', 'platform')
                                    ->whereNotNull('fee_confirmed_at')->sum('platform_delivery_fee');
        $pendingDeliveryFees   = $totalDeliveryFees - $confirmedDeliveryFees;
        $submittedPendingFees  = Delivery::where('delivery_method', 'platform')
                                    ->whereNotNull('fee_submitted_at')
                                    ->whereNull('fee_confirmed_at')
                                    ->sum('platform_delivery_fee');

        return response()->json([
            'success' => true,
            'data' => [
                // Users
                'total_users'           => User::count(),
                'active_users'          => User::where('status', 'active')->count(),
                'total_sellers'         => User::where('type', 'seller')->count(),
                'total_buyers'          => User::where('type', 'buyer')->count(),
                // Products
                'total_products'        => Product::count(),
                'active_products'       => Product::where('is_active', true)->count(),
                // Orders
                'total_orders'          => Order::count(),
                'pending_orders'        => Order::where('status', 'pending')->count(),
                'completed_orders'      => Order::where('status', 'delivered')->count(),
                'cancelled_orders'      => Order::where('status', 'cancelled')->count(),
                // Revenue (GMV)
                'total_revenue'         => Order::sum('total_amount'),
                'confirmed_revenue'     => Order::where('status', 'delivered')->sum('total_amount'),
                // Commission
                'total_commission'      => $totalCommission,
                'commission_revenue'    => Order::whereNotNull('commission_amount')->sum('commission_amount'),
                'pending_commissions'   => $pendingComm,
                'collected_commissions' => $collectedComm,
                'paid_commissions'      => Commission::where('status', 'paid')->sum('amount'),
                // Delivery fees
                'total_delivery_fees'      => $totalDeliveryFees,
                'confirmed_delivery_fees'  => $confirmedDeliveryFees,
                'pending_delivery_fees'    => $pendingDeliveryFees,
                'submitted_delivery_fees'  => $submittedPendingFees,
                // Platform revenue
                'delivery_fee_revenue'  => $totalDeliveryFees,
                'platform_revenue'      => Order::whereNotNull('commission_amount')->sum('commission_amount') + $totalDeliveryFees,
                // Business
                'total_business_types'  => BusinessType::count(),
                'active_business_types' => BusinessType::where('is_active', true)->count(),
                'total_sellers_approved'=> SellerProfile::whereIn('status', ['approved', 'active'])->count(),
                'sellers_pending'       => SellerProfile::where('status', 'pending')->count(),
            ]
        ]);
    }


    /**
     * GET /admin/revenue-breakdown
     * Monthly breakdown of platform revenue: commission_amount (from orders)
     * + platform_delivery_fee (from deliveries with delivery_method=platform).
     * This is the actual revenue Pyonea earns, separate from GMV.
     */
    public function revenueBreakdown()
    {
        $start = Carbon::now()->subMonths(11)->startOfMonth();
        $end   = Carbon::now()->endOfMonth();

        // Monthly commission from delivered orders
        $commissions = Order::whereBetween('created_at', [$start, $end])
            ->where('status', 'delivered')
            ->whereNotNull('commission_amount')
            ->selectRaw('DATE_FORMAT(created_at, "%Y-%m") as month, SUM(commission_amount) as commission')
            ->groupBy('month')
            ->get()
            ->keyBy('month');

        // Monthly platform delivery fees
        $deliveryFees = \App\Models\Delivery::whereBetween('created_at', [$start, $end])
            ->where('delivery_method', 'platform')
            ->where('status', 'delivered')
            ->selectRaw('DATE_FORMAT(created_at, "%Y-%m") as month, SUM(platform_delivery_fee) as delivery_fee')
            ->groupBy('month')
            ->get()
            ->keyBy('month');

        // Monthly GMV (for reference)
        $gmv = Order::whereBetween('created_at', [$start, $end])
            ->where('status', 'delivered')
            ->selectRaw('DATE_FORMAT(created_at, "%Y-%m") as month, SUM(total_amount) as gmv')
            ->groupBy('month')
            ->get()
            ->keyBy('month');

        $results = [];
        $current = clone $start;
        while ($current <= $end) {
            $month = $current->format('Y-m');
            $comm  = (float) ($commissions[$month]->commission ?? 0);
            $dFee  = (float) ($deliveryFees[$month]->delivery_fee ?? 0);
            $results[] = [
                'month'        => $month,
                'commission'   => $comm,
                'delivery_fee' => $dFee,
                'platform'     => $comm + $dFee,   // total platform revenue
                'gmv'          => (float) ($gmv[$month]->gmv ?? 0),
            ];
            $current->addMonth();
        }

        return response()->json([
            'success' => true,
            'data'    => $results,
        ]);
    }


    /**
     * PATCH /seller/deliveries/{id}/submit-fee
     * Seller submits delivery fee payment proof to admin.
     */
    public function sellerSubmitDeliveryFee(Request $request, $deliveryId)
    {
        $user = $request->user();
        if ($user->type !== 'seller') {
            return response()->json(['success' => false, 'message' => 'Sellers only.'], 403);
        }
        $delivery = \App\Models\Delivery::where('id', $deliveryId)
            ->where('supplier_id', $user->id)
            ->firstOrFail();

        $request->validate(['note' => 'nullable|string|max:500']);

        $delivery->update([
            'fee_submitted_at'   => now(),
            'fee_submission_note'=> $request->note ?? 'Delivery fee paid.',
        ]);
        return response()->json(['success' => true, 'message' => 'Fee submission sent to admin.', 'data' => $delivery->fresh()]);
    }

    /**
     * PATCH /admin/deliveries/{id}/confirm-fee
     * Admin confirms delivery fee received from seller.
     */
    public function adminConfirmDeliveryFee(Request $request, $deliveryId)
    {
        $user = $request->user();
        if ($user->type !== 'admin' && !$user->hasRole('admin')) {
            return response()->json(['success' => false, 'message' => 'Admins only.'], 403);
        }
        $delivery = \App\Models\Delivery::findOrFail($deliveryId);
        $request->validate(['note' => 'nullable|string|max:500']);

        $delivery->update([
            'fee_confirmed_at'      => now(),
            'fee_confirmed_by'      => $user->id,
            'fee_confirmation_note' => $request->note ?? 'Confirmed by admin.',
        ]);

        return response()->json(['success' => true, 'message' => 'Delivery fee confirmed.', 'data' => $delivery->fresh()]);
    }

    public function salesReport(Request $request)
    {
        $request->validate([
            'period' => 'required|in:today,week,month,year,custom',
            'start_date' => 'required_if:period,custom|date',
            'end_date' => 'required_if:period,custom|date|after_or_equal:start_date'
        ]);

        $period = $request->period;
        $now = Carbon::now();

        switch ($period) {
            case 'today':
                $start = $now->startOfDay();
                $end = $now->endOfDay();
                break;
            case 'week':
                $start = $now->startOfWeek();
                $end = $now->endOfWeek();
                break;
            case 'month':
                $start = $now->startOfMonth();
                $end = $now->endOfMonth();
                break;
            case 'year':
                $start = $now->startOfYear();
                $end = $now->endOfYear();
                break;
            case 'custom':
                $start = Carbon::parse($request->start_date)->startOfDay();
                $end = Carbon::parse($request->end_date)->endOfDay();
                break;
        }

        $orders = Order::whereBetween('created_at', [$start, $end])
            ->where('status', 'delivered')
            ->get();

        $salesData = [
            'total_orders' => $orders->count(),
            'total_revenue' => $orders->sum('total_amount'),
            'total_commission' => $orders->sum('commission_amount'),
            'average_order_value' => $orders->avg('total_amount'),
            'top_products' => $this->getTopProducts($start, $end),
            'sales_by_day' => $this->getSalesByDay($start, $end)
        ];

        return response()->json([
            'success' => true,
            'data' => $salesData
        ]);
    }

    protected function getTopProducts($start, $end, $limit = 5)
    {
        return OrderItem::whereBetween('created_at', [$start, $end])
            ->selectRaw('product_id, sum(quantity) as total_quantity, sum(unit_price * quantity) as total_revenue')
            ->groupBy('product_id')
            ->orderBy('total_revenue', 'desc')
            ->with('product:id,name,slug')
            ->take($limit)
            ->get();
    }

    protected function getSalesByDay($start, $end)
    {
        $sales = Order::whereBetween('created_at', [$start, $end])
            ->where('status', 'delivered')
            ->selectRaw('DATE(created_at) as date, count(*) as count, sum(total_amount) as revenue')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Fill missing days with zero values
        $results = [];
        $current = clone $start;
        while ($current <= $end) {
            $date = $current->format('Y-m-d');
            $sale = $sales->firstWhere('date', $date);

            $results[] = [
                'date' => $date,
                'count' => $sale ? $sale->count : 0,
                'revenue' => $sale ? $sale->revenue : 0
            ];

            $current->addDay();
        }

        return $results;
    }

    public function topSellers(Request $request)
    {
        $period = $request->input('period', 'month');
        $now = Carbon::now();

        switch ($period) {
            case 'today':
                $start = $now->startOfDay();
                $end = $now->endOfDay();
                break;
            case 'week':
                $start = $now->startOfWeek();
                $end = $now->endOfWeek();
                break;
            case 'month':
                $start = $now->startOfMonth();
                $end = $now->endOfMonth();
                break;
            case 'year':
                $start = $now->startOfYear();
                $end = $now->endOfYear();
                break;
            default:
                $start = $now->startOfMonth();
                $end = $now->endOfMonth();
                break;
        }

        $topSellers = Order::whereBetween('created_at', [$start, $end])
            ->where('status', 'delivered')
            ->join('order_items', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->select('products.user_id as seller_id', DB::raw('SUM(order_items.unit_price * order_items.quantity) as revenue'))
            ->groupBy('seller_id')
            ->orderByDesc('revenue')
            ->with('seller:id,name,email')
            ->take(5)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $topSellers,
        ]);
    }

    /**
     * Get user registrations over time (daily for last 30 days)
     */
    public function userRegistrationsOverTime()
    {
        $start = Carbon::now()->subDays(30)->startOfDay();
        $end = Carbon::now()->endOfDay();

        $registrations = User::whereBetween('created_at', [$start, $end])
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $results = [];
        $current = clone $start;
        while ($current <= $end) {
            $date = $current->format('Y-m-d');
            $record = $registrations->firstWhere('date', $date);
            $results[] = [
                'date' => $date,
                'count' => $record ? $record->count : 0,
            ];
            $current->addDay();
        }

        return response()->json([
            'success' => true,
            'data' => $results,
        ]);
    }

    /**
     * Get orders status summary (count by status)
     */
    public function orderStatusSummary()
    {
        $statuses = Order::select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status');

        return response()->json([
            'success' => true,
            'data' => $statuses,
        ]);
    }

    /**
     * Get monthly revenue trend for last 12 months
     */
    public function monthlyRevenueTrend()
    {
        $start = Carbon::now()->subMonths(11)->startOfMonth();
        $end = Carbon::now()->endOfMonth();

        $revenues = Order::whereBetween('created_at', [$start, $end])
            ->where('status', 'delivered')
            ->selectRaw('DATE_FORMAT(created_at, "%Y-%m") as month, SUM(total_amount) as revenue')
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        $results = [];
        $current = clone $start;
        while ($current <= $end) {
            $month = $current->format('Y-m');
            $results[] = [
                'month' => $month,
                'revenue' => $revenues->has($month) ? $revenues[$month]->revenue : 0,
            ];
            $current->addMonth();
        }

        return response()->json([
            'success' => true,
            'data' => $results,
        ]);
    }

    /**
     * Get recent orders with buyer and seller info
     */
    public function recentOrders()
    {
        $orders = Order::with(['buyer:id,name,email', 'items.product.user:id,name'])
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $orders,
        ]);
    }

    /**
     * Get commission payout summary by month
     */
    public function commissionSummary()
    {
        $start = Carbon::now()->subMonths(11)->startOfMonth();
        $end = Carbon::now()->endOfMonth();

        $commissions = Commission::whereBetween('created_at', [$start, $end])
            ->selectRaw('DATE_FORMAT(created_at, "%Y-%m") as month, SUM(amount) as total, status')
            ->groupBy('month', 'status')
            ->orderBy('month')
            ->get()
            ->groupBy('month');

        $results = [];
        $current = clone $start;
        while ($current <= $end) {
            $month = $current->format('Y-m');
            $monthData = $commissions->get($month, collect());
            $paid = $monthData->where('status', 'paid')->sum('total');
            $pending = $monthData->where('status', 'pending')->sum('total');
            $results[] = [
                'month' => $month,
                'paid_commissions' => $paid,
                'pending_commissions' => $pending,
            ];
            $current->addMonth();
        }

        return response()->json([
            'success' => true,
            'data' => $results,
        ]);
    }

    /**
     * Get users count grouped by role
     */
    public function usersCountByRole()
    {
        $roles = ['admin', 'seller', 'buyer']; // adjust roles as per your system
        $data = [];

        foreach ($roles as $role) {
            $data[$role] = User::role($role)->count();  // Assuming you use spatie/laravel-permission or similar
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get recently registered users
     */
    public function recentUsers(Request $request)
    {
        $limit = $request->input('limit', 10);

        $users = User::orderBy('created_at', 'desc')
            ->take($limit)
            ->get(['id', 'name', 'email', 'created_at']);

        return response()->json([
            'success' => true,
            'data' => $users,
        ]);
    }

    /**
     * Get count of active vs inactive users
     */
    public function activeInactiveUsers()
    {
        $activeUsers = User::where('status', 'active')->count();
        $inactiveUsers = User::where('status', 'inactive')->count();

        return response()->json([
            'success' => true,
            'data' => [
                'active_users' => $activeUsers,
                'inactive_users' => $inactiveUsers,
            ],
        ]);
    }

    /**
     * Get user growth over last 30 days (daily registrations)
     */
    public function userGrowthLast30Days()
    {
        $start = Carbon::now()->subDays(30)->startOfDay();
        $end = Carbon::now()->endOfDay();

        $registrations = User::whereBetween('created_at', [$start, $end])
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $results = [];
        $current = clone $start;

        while ($current <= $end) {
            $date = $current->format('Y-m-d');
            $record = $registrations->firstWhere('date', $date);

            $results[] = [
                'date' => $date,
                'registrations' => $record ? $record->count : 0,
            ];

            $current->addDay();
        }

        return response()->json([
            'success' => true,
            'data' => $results,
        ]);
    }

    /**
     * Get seller sales summary
     */
    public function sellerSalesSummary(Request $request)
    {
        try {
            $user = $request->user();

            // Check if user is a seller
            if (!$user->hasRole('seller')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only sellers can access this endpoint'
                ], 403);
            }

            // Get date range (default to last 30 days)
            $startDate = $request->input('start_date', Carbon::now()->subDays(30)->format('Y-m-d'));
            $endDate = $request->input('end_date', Carbon::now()->format('Y-m-d'));

            // Total products
            $totalProducts = Product::where('seller_id', $user->id)->count();
            $activeProducts = Product::where('seller_id', $user->id)
                ->where('is_active', true)
                ->count();

            // Sales data (you'll need to adjust this based on your Order model structure)
            // seller_id is on orders table — no order_items join needed
            $salesData = DB::table('orders')
                ->where('seller_id', $user->id)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->select(
                    DB::raw('COUNT(*) as total_orders'),
                    DB::raw('COALESCE(SUM(total_amount), 0) as total_revenue'),
                    DB::raw('COALESCE(AVG(total_amount), 0) as average_order_value')
                )
                ->first();

            // Order status counts (direct on orders)
            $orderStatusCounts = DB::table('orders')
                ->where('seller_id', $user->id)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->select('status', DB::raw('COUNT(*) as count'))
                ->groupBy('status')
                ->get()
                ->pluck('count', 'status');

            // Recent sales trend (last 7 days)
            $recentSalesTrend = DB::table('orders')
                ->where('seller_id', $user->id)
                ->where('created_at', '>=', Carbon::now()->subDays(7))
                ->select(
                    DB::raw('DATE(created_at) as date'),
                    DB::raw('COALESCE(SUM(total_amount), 0) as revenue'),
                    DB::raw('COUNT(*) as orders_count')
                )
                ->groupBy('date')
                ->orderBy('date')
                ->get();

            // Top selling products
            // Join through orders to get seller_id (order_items has no seller_id)
            $topProducts = DB::table('order_items')
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->join('products', 'order_items.product_id', '=', 'products.id')
                ->where('orders.seller_id', $user->id)
                ->whereBetween('orders.created_at', [$startDate, $endDate])
                ->select(
                    'products.id',
                    'products.name_en as name',
                    DB::raw('COALESCE(SUM(order_items.quantity), 0) as total_sold'),
                    DB::raw('COALESCE(SUM(order_items.price * order_items.quantity), 0) as total_revenue')
                )
                ->groupBy('products.id', 'products.name_en')
                ->orderBy('total_sold', 'desc')
                ->limit(5)
                ->get();

            $summary = [
                'period' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'days' => Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate)) + 1
                ],
                'products' => [
                    'total' => $totalProducts,
                    'active' => $activeProducts,
                    'inactive' => $totalProducts - $activeProducts
                ],
                'sales' => [
                    'total_orders' => $salesData->total_orders ?? 0,
                    'total_items_sold' => $salesData->total_items_sold ?? 0,
                    'total_revenue' => $salesData->total_revenue ?? 0,
                    'average_order_value' => $salesData->average_order_value ?? 0,
                    'revenue_formatted' => number_format($salesData->total_revenue ?? 0, 2) . ' MMK'
                ],
                'orders_by_status' => $orderStatusCounts,
                'recent_trend' => $recentSalesTrend,
                'top_products' => $topProducts
            ];

            return response()->json([
                'success' => true,
                'data' => $summary
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in sellerSalesSummary: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch sales summary: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get seller top products
     */
    public function sellerTopProducts(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user->hasRole('seller')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only sellers can access this endpoint'
                ], 403);
            }

            $limit = $request->input('limit', 5);
            $days = $request->input('days', 30);

            // Join through orders for seller_id — order_items has no seller_id column
            $topProducts = DB::table('order_items')
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->join('products', 'order_items.product_id', '=', 'products.id')
                ->where('orders.seller_id', $user->id)
                ->where('orders.created_at', '>=', Carbon::now()->subDays($days))
                ->select(
                    'products.id',
                    'products.name_en as name',
                    'products.price',
                    'products.images',
                    DB::raw('COALESCE(SUM(order_items.quantity), 0) as total_sold'),
                    DB::raw('COALESCE(SUM(order_items.price * order_items.quantity), 0) as total_revenue')
                )
                ->groupBy('products.id', 'products.name_en', 'products.price', 'products.images')
                ->orderBy('total_sold', 'desc')
                ->limit($limit)
                ->get();

            // Format images
            $topProducts = $topProducts->map(function ($product) {
                $images = json_decode($product->images, true) ?? [];
                $primaryImage = collect($images)->firstWhere('is_primary', true) ?? $images[0] ?? null;

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'price' => $product->price,
                    'image' => $primaryImage['url'] ?? null,
                    'total_sold' => $product->total_sold,
                    'total_revenue' => $product->total_revenue,
                    'average_rating' => $product->average_rating ? round($product->average_rating, 2) : null
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $topProducts
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in sellerTopProducts: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch top products: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get seller recent orders
     */
    public function sellerRecentOrders(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user->hasRole('seller')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only sellers can access this endpoint'
                ], 403);
            }

            $limit = $request->input('limit', 10);

            // Adjust this query based on your Order and OrderItem models
            // seller_id is directly on orders — no order_items join needed
            $recentOrders = DB::table('orders')
                ->where('orders.seller_id', $user->id)
                ->select(
                    'orders.id',
                    'orders.order_number',
                    'orders.status',
                    'orders.total_amount',
                    'orders.created_at',
                    DB::raw('(SELECT COALESCE(SUM(oi.quantity),0) FROM order_items oi WHERE oi.order_id = orders.id) as total_items'),
                    DB::raw('(SELECT GROUP_CONCAT(oi.product_name SEPARATOR ", ") FROM order_items oi WHERE oi.order_id = orders.id LIMIT 3) as product_names')
                )
                ->orderBy('orders.created_at', 'desc')
                ->limit($limit)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $recentOrders
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in sellerRecentOrders: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch recent orders: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * GET /seller/commission-summary
     */
    public function sellerCommissionSummary(Request $request)
    {
        $user = $request->user();
        if (!$user->hasRole('seller') && $user->type !== 'seller') {
            return response()->json(['success' => false, 'message' => 'Sellers only.'], 403);
        }
        $totalCommission   = Commission::where('seller_id', $user->id)->sum('amount');
        $pendingCommission = Commission::where('seller_id', $user->id)->where('status', 'pending')->sum('amount');
        $paidCommission    = Commission::where('seller_id', $user->id)->whereIn('status', ['collected', 'paid'])->sum('amount');
        $commissionRate    = Commission::where('seller_id', $user->id)->latest()->value('commission_rate') ?? 0.05;
        $totalDeliveryFees    = \App\Models\Delivery::where('supplier_id', $user->id)->where('delivery_method', 'platform')->sum('platform_delivery_fee');
        $pendingDeliveryFees  = \App\Models\Delivery::where('supplier_id', $user->id)->where('delivery_method', 'platform')->whereNull('fee_confirmed_at')->sum('platform_delivery_fee');
        $confirmedDeliveryFees= \App\Models\Delivery::where('supplier_id', $user->id)->where('delivery_method', 'platform')->whereNotNull('fee_confirmed_at')->sum('platform_delivery_fee');
        $submittedPending     = \App\Models\Delivery::where('supplier_id', $user->id)->where('delivery_method', 'platform')->whereNotNull('fee_submitted_at')->whereNull('fee_confirmed_at')->sum('platform_delivery_fee');
        return response()->json([
            'success' => true,
            'data' => [
                'commission' => [
                    'total'    => (float) $totalCommission,
                    'pending'  => (float) $pendingCommission,
                    'paid'     => (float) $paidCommission,
                    'rate'     => (float) $commissionRate,
                    'rate_pct' => round($commissionRate * 100, 2),
                ],
                'delivery_fees' => [
                    'total'     => (float) $totalDeliveryFees,
                    'pending'   => (float) $pendingDeliveryFees,
                    'confirmed' => (float) $confirmedDeliveryFees,
                    'submitted_awaiting' => (float) $submittedPending,
                ],
            ],
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // ADMIN — DELIVERY FEE MANAGEMENT
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * GET /admin/delivery-fees
     * List all platform-method deliveries with their fee status.
     * Used by DeliveryFeeManagement component.
     */
    public function adminListDeliveryFees(Request $request)
    {
        $base = \App\Models\Delivery::where('delivery_method', 'platform');
        $query = (clone $base)
            ->with(['order:id,order_number,buyer_id', 'supplier:id,name'])
            ->orderByDesc('created_at');

        // Filter by delivery_fee_status enum: outstanding | collected
        if ($feeStatus = $request->fee_status) {
            $query->where('delivery_fee_status', $feeStatus);
        }

        $deliveries = $query->paginate($request->get('per_page', 20));

        $summary = [
            'outstanding_count'  => (clone $base)->where('delivery_fee_status', 'outstanding')->count(),
            'outstanding_amount' => (clone $base)->where('delivery_fee_status', 'outstanding')->sum('platform_delivery_fee'),
            'collected_count'    => (clone $base)->where('delivery_fee_status', 'collected')->count(),
            'collected_amount'   => (clone $base)->where('delivery_fee_status', 'collected')->sum('platform_delivery_fee'),
        ];

        return response()->json([
            'success' => true,
            'data'    => ['deliveries' => $deliveries, 'summary' => $summary],
        ]);
    }

    /**
     * GET /admin/delivery-fees/pending
     * Deliveries where seller submitted payment but admin hasn't confirmed yet.
     * Used by DeliveryFeeReview component.
     */
    public function adminPendingDeliveryFees(Request $request)
    {
        $fees = \App\Models\Delivery::with(['order:id,order_number', 'supplier:id,name'])
            ->where('delivery_method', 'platform')
            ->whereNotNull('fee_submitted_at')
            ->whereNull('fee_confirmed_at')
            ->orderBy('fee_submitted_at')
            ->get();

        return response()->json(['success' => true, 'data' => $fees]);
    }

    /**
     * POST /admin/deliveries/{id}/collect-fee
     * Admin marks the delivery fee as collected from seller.
     * Used by DeliveryFeeManagement Collect modal.
     */
    public function adminCollectDeliveryFee(Request $request, $id)
    {
        $user = $request->user();
        if (!$user->hasRole('admin') && $user->type !== 'admin') {
            return response()->json(['success' => false, 'message' => 'Admins only.'], 403);
        }

        $delivery = \App\Models\Delivery::findOrFail($id);

        $validated = $request->validate([
            'collection_ref' => 'required|string|max:200',
            'admin_notes'    => 'nullable|string|max:500',
        ]);

        $delivery->update([
            // System 1: delivery_fee_status enum tracks collection state
            'delivery_fee_status'        => 'collected',
            'delivery_fee_collected_at'  => now(),
            'delivery_fee_collected_by'  => $user->id,
            'delivery_fee_collection_ref'=> $validated['collection_ref'],
            // System 2: fee_confirmed columns for DeliveryFeeReview
            'fee_confirmed_at'           => now(),
            'fee_confirmed_by'           => $user->id,
            'fee_confirmation_note'      => $validated['collection_ref']
                . ($validated['admin_notes'] ? ' — ' . $validated['admin_notes'] : ''),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Delivery fee marked as collected.',
            'data'    => $delivery->fresh(['order', 'supplier']),
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // ADMIN — COD COMMISSION INVOICE MANAGEMENT
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * GET /admin/cod-invoices
     * List COD commission invoices with optional status filter.
     * Used by CodInvoiceManagement component.
     */
    public function adminListCodInvoices(Request $request)
    {
        $query = \App\Models\CodCommissionInvoice::with([
                'seller:id,name',
                'order:id,order_number',
                'confirmedBy:id,name',
            ])
            ->orderByDesc('created_at');

        if ($status = $request->status) {
            $query->where('status', $status);
        }

        $invoices = $query->paginate($request->get('per_page', 20));

        $summary = [
            'outstanding' => \App\Models\CodCommissionInvoice::where('status', 'outstanding')->count(),
            'overdue'     => \App\Models\CodCommissionInvoice::where('status', 'overdue')->count(),
            'paid'        => \App\Models\CodCommissionInvoice::where('status', 'paid')->count(),
            'waived'      => \App\Models\CodCommissionInvoice::where('status', 'waived')->count(),
            'total_owed'  => \App\Models\CodCommissionInvoice::whereIn('status', ['outstanding','overdue'])->sum('commission_amount'),
            'total_paid'  => \App\Models\CodCommissionInvoice::where('status', 'paid')->sum('commission_amount'),
        ];

        return response()->json([
            'success' => true,
            'data'    => ['invoices' => $invoices, 'summary' => $summary],
        ]);
    }

    /**
     * POST /admin/cod-invoices/{id}/confirm-payment
     * Admin confirms a seller paid their COD commission invoice.
     * Used by CodInvoiceManagement confirm modal.
     */
    public function adminConfirmCodPayment(Request $request, $id)
    {
        $user = $request->user();
        if (!$user->hasRole('admin') && $user->type !== 'admin') {
            return response()->json(['success' => false, 'message' => 'Admins only.'], 403);
        }

        $invoice = \App\Models\CodCommissionInvoice::findOrFail($id);

        $validated = $request->validate([
            'payment_reference' => 'nullable|string|max:200',
            'payment_method'    => 'nullable|string|max:100',
            'admin_notes'       => 'nullable|string|max:500',
        ]);

        $invoice->update([
            'status'             => 'paid',
            'paid_at'            => now(),
            'admin_confirmed_at' => now(),
            'confirmed_by'       => $user->id,
            'payment_reference'  => $validated['payment_reference'] ?? null,
            'payment_method'     => $validated['payment_method'] ?? null,
            'admin_notes'        => $validated['admin_notes'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'COD invoice marked as paid.',
            'data'    => $invoice->fresh(['seller', 'order', 'confirmedBy']),
        ]);
    }

    /**
     * POST /admin/cod-invoices/{id}/waive
     * Admin waives a COD commission invoice (writes off the debt).
     * Used by CodInvoiceManagement waive modal.
     */
    public function adminWaiveCodInvoice(Request $request, $id)
    {
        $user = $request->user();
        if (!$user->hasRole('admin') && $user->type !== 'admin') {
            return response()->json(['success' => false, 'message' => 'Admins only.'], 403);
        }

        $invoice = \App\Models\CodCommissionInvoice::findOrFail($id);

        $validated = $request->validate([
            'admin_notes' => 'required|string|max:500',
        ]);

        $invoice->update([
            'status'             => 'waived',
            'admin_confirmed_at' => now(),
            'confirmed_by'       => $user->id,
            'admin_notes'        => $validated['admin_notes'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'COD invoice waived.',
            'data'    => $invoice->fresh(['seller', 'order']),
        ]);
    }

}
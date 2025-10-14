<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Order;
use App\Models\Product;
use App\Models\Commission;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

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

    public function stats()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'total_users' => User::count(),
                'active_users' => User::where('status', 'active')->count(),
                'total_products' => Product::count(),
                'active_products' => Product::where('is_active', true)->count(),
                'total_orders' => Order::count(),
                'pending_orders' => Order::where('status', 'pending')->count(),
                'completed_orders' => Order::where('status', 'delivered')->count(),
                'total_revenue' => Order::sum('total_amount'),
                'pending_commissions' => Commission::where('status', 'pending')->sum('amount'),
                'paid_commissions' => Commission::where('status', 'paid')->sum('amount')
            ]
        ]);
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
            $salesData = DB::table('orders')
                ->join('order_items', 'orders.id', '=', 'order_items.order_id')
                ->where('order_items.seller_id', $user->id)
                ->whereBetween('orders.created_at', [$startDate, $endDate])
                ->select(
                    DB::raw('COUNT(DISTINCT orders.id) as total_orders'),
                    DB::raw('SUM(order_items.quantity) as total_items_sold'),
                    DB::raw('SUM(order_items.price * order_items.quantity) as total_revenue'),
                    DB::raw('AVG(order_items.price * order_items.quantity) as average_order_value')
                )
                ->first();

            // Order status counts
            $orderStatusCounts = DB::table('orders')
                ->join('order_items', 'orders.id', '=', 'order_items.order_id')
                ->where('order_items.seller_id', $user->id)
                ->whereBetween('orders.created_at', [$startDate, $endDate])
                ->select(
                    'orders.status',
                    DB::raw('COUNT(DISTINCT orders.id) as count')
                )
                ->groupBy('orders.status')
                ->get()
                ->pluck('count', 'status');

            // Recent sales trend (last 7 days)
            $recentSalesTrend = DB::table('orders')
                ->join('order_items', 'orders.id', '=', 'order_items.order_id')
                ->where('order_items.seller_id', $user->id)
                ->where('orders.created_at', '>=', Carbon::now()->subDays(7))
                ->select(
                    DB::raw('DATE(orders.created_at) as date'),
                    DB::raw('SUM(order_items.price * order_items.quantity) as revenue'),
                    DB::raw('COUNT(DISTINCT orders.id) as orders_count')
                )
                ->groupBy('date')
                ->orderBy('date')
                ->get();

            // Top selling products
            $topProducts = DB::table('order_items')
                ->join('products', 'order_items.product_id', '=', 'products.id')
                ->where('order_items.seller_id', $user->id)
                ->whereBetween('order_items.created_at', [$startDate, $endDate])
                ->select(
                    'products.id',
                    'products.name',
                    DB::raw('SUM(order_items.quantity) as total_sold'),
                    DB::raw('SUM(order_items.price * order_items.quantity) as total_revenue')
                )
                ->groupBy('products.id', 'products.name')
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

            $topProducts = DB::table('order_items')
                ->join('products', 'order_items.product_id', '=', 'products.id')
                ->where('order_items.seller_id', $user->id)
                ->where('order_items.created_at', '>=', Carbon::now()->subDays($days))
                ->select(
                    'products.id',
                    'products.name',
                    'products.price',
                    'products.images',
                    DB::raw('SUM(order_items.quantity) as total_sold'),
                    DB::raw('SUM(order_items.price * order_items.quantity) as total_revenue'),
                    DB::raw('AVG(order_items.rating) as average_rating')
                )
                ->groupBy('products.id', 'products.name', 'products.price', 'products.images')
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
            $recentOrders = DB::table('orders')
                ->join('order_items', 'orders.id', '=', 'order_items.order_id')
                ->where('order_items.seller_id', $user->id)
                ->select(
                    'orders.id',
                    'orders.order_number',
                    'orders.status',
                    'orders.total_amount',
                    'orders.created_at',
                    DB::raw('SUM(order_items.quantity) as total_items'),
                    DB::raw('GROUP_CONCAT(order_items.product_name) as product_names')
                )
                ->groupBy('orders.id', 'orders.order_number', 'orders.status', 'orders.total_amount', 'orders.created_at')
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
}
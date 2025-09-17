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
    public function index()
    {
        return response()->json([
            'success' => true,
            'message' => 'Admin dashboard loaded successfully.',
            'data' => [
                'user_count' => User::count(),
                'product_count' => Product::count(),
                'order_count' => Order::count(),
                'total_revenue' => Order::sum('total_amount'),
            ]
        ]);
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
}
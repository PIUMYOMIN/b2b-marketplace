<?php

namespace App\Http\Controllers\Api;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use App\Models\SellerProfile;
use App\Models\BusinessType;
use App\Models\ShippingSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class SellerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // ✅ Handle "top sellers" case
        if ($request->boolean('top')) {
            $topSellers = SellerProfile::with(['user', 'reviews'])
                ->withAvg('reviews', 'rating')
                ->withCount(['reviews', 'products'])
                ->withCount([
                    'orders as customers_count' => function ($q) {
                        $q->distinct('user_id');
                    }
                ])
                ->whereIn('status', ['approved', 'active'])
                ->orderByDesc('reviews_avg_rating')
                ->orderByDesc('reviews_count')
                ->take(6)
                ->get();

            // Convert store logo and banner to full URLs for top sellers
            $topSellers->transform(function ($seller) {
                $sellerData = $seller->toArray();
                $sellerData['store_logo'] = !empty($sellerData['store_logo'])
                    ? url('storage/' . ltrim($sellerData['store_logo'], '/'))
                    : null;

                $sellerData['store_banner'] = !empty($sellerData['store_banner'])
                    ? url('storage/' . ltrim($sellerData['store_banner'], '/'))
                    : null;

                return $sellerData;
            });

            return response()->json([
                'success' => true,
                'data' => $topSellers
            ]);
        }

        // Validate filters and pagination
        $validator = Validator::make($request->all(), [
            'per_page' => 'sometimes|integer|min:1|max:100',
            'search' => 'sometimes|string|max:255',
            'business_type' => 'sometimes|string|in:individual,company,retail,wholesale,manufacturer,partnership,cooperative',
            'city' => 'sometimes|string|max:100',
            'min_rating' => 'sometimes|numeric|min:0|max:5',
            'sort' => 'sometimes|in:newest,rating,name'
        ]);

        // Validation failed
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Pagination
        $perPage = $request->input('per_page', 15);

        // Base query
        $query = SellerProfile::with(['user', 'reviews'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews', 'products')
            ->whereIn('status', ['approved', 'active']);

        // Apply filters
        if ($request->has('search') && $request->search !== null) {
            $query->where(function ($q) use ($request) {
                $q->where('store_name', 'like', '%' . $request->search . '%')
                                                            ->orWhere('store_description', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->has('business_type') && $request->business_type !== null) {
            $query->where('business_type', $request->business_type);
        }

        if ($request->has('city') && $request->city !== null) {
            $query->where('city', $request->city);
        }

        if ($request->has('min_rating') && $request->input('min_rating') !== null) {
            // use proper PHP request access and ensure numeric comparison
            $query->having('reviews_avg_rating', '>=', (float) $request->input('min_rating'));
        }

        // Apply sorting
        switch ($request->input('sort', 'newest')) {
            case 'rating':
                $query->orderBy('reviews_avg_rating', 'desc');
                break;
            case 'name':
                $query->orderBy('store_name', 'asc');
                break;
            default:
                $query->latest();
        }

        $sellers = $query->paginate($perPage);

        // Convert store logo and banner to full URLs for paginated results
        $sellers->getCollection()->transform(function ($seller) {
            $sellerData = $seller->toArray();
            $sellerData['store_logo'] = !empty($sellerData['store_logo'])
                ? url('storage/' . ltrim($sellerData['store_logo'], '/'))
                : null;

            $sellerData['store_banner'] = !empty($sellerData['store_banner'])
                ? url('storage/' . ltrim($sellerData['store_banner'], '/'))
                : null;

            return $sellerData;
        });

        return response()->json([
            'success' => true,
            'data' => $sellers,
            'meta' => [
                'current_page' => $sellers->currentPage(),
                'per_page' => $sellers->perPage(),
                'total' => $sellers->total(),
                'last_page' => $sellers->lastPage(),
            ]
        ]);
    }

    public function dashboard(Request $request)
    {
        try {
            $user = $request->user();

            if (!isset($user->type) || $user->type !== 'seller') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only sellers can access this endpoint'
                ], 403);
            }

            // Get store data
            $store = SellerProfile::where('user_id', $user->id)->first();

            if (!$store) {
                return response()->json([
                    'success' => false,
                    'message' => 'Seller profile not found'
                ], 404);
            }

            // Get quick stats
            $quickStats = $this->getQuickStats($user->id);

            // Get recent activity
            $recentActivity = $this->getRecentActivity($user->id);

            // Get performance metrics
            $performance = $this->getPerformanceMetrics($user->id);

            return response()->json([
                'success' => true,
                'data' => [
                    'store' => $store,
                    'quick_stats' => $quickStats,
                    'recent_activity' => $recentActivity,
                    'performance' => $performance
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error in seller dashboard: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch dashboard data'
            ], 500);
        }
    }

    /**
     * Get quick stats for dashboard
     */
    private function getQuickStats($sellerId)
    {
        $startDate = Carbon::now()->subDays(30);
        $endDate = Carbon::now();

        // Total products
        $totalProducts = Product::where('seller_id', $sellerId)->count();
        $activeProducts = Product::where('seller_id', $sellerId)
            ->where('is_active', true)
            ->count();

        // Sales data
        $salesData = DB::table('orders')
            ->where('seller_id', $sellerId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select(
                DB::raw('COUNT(*) as total_orders'),
                DB::raw('SUM(total_amount) as total_revenue'),
                DB::raw('AVG(total_amount) as average_order_value')
            )
            ->first();

        // Pending orders
        $pendingOrders = DB::table('orders')
            ->where('seller_id', $sellerId)
            ->where('status', 'pending')
            ->count();

        // Total customers (unique buyers)
        $totalCustomers = DB::table('orders')
            ->where('seller_id', $sellerId)
            ->distinct('buyer_id')
            ->count('buyer_id');

        return [
            'total_products' => $totalProducts,
            'active_products' => $activeProducts,
            'total_orders' => $salesData->total_orders ?? 0,
            'total_revenue' => $salesData->total_revenue ?? 0,
            'average_order_value' => $salesData->average_order_value ?? 0,
            'pending_orders' => $pendingOrders,
            'total_customers' => $totalCustomers
        ];
    }

    /**
     * Get recent activity for dashboard
     */
    private function getRecentActivity($sellerId)
    {
        $recentOrders = Order::where('seller_id', $sellerId)
            ->with(['buyer:id,name,email'])
            ->select(['id', 'order_number', 'status', 'total_amount', 'created_at', 'buyer_id'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($order) {
                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'status' => $order->status,
                    'total_amount' => $order->total_amount,
                    'created_at' => $order->created_at->format('M j, Y g:i A'),
                    'buyer' => $order->buyer ? [
                        'name' => $order->buyer->name,
                        'email' => $order->buyer->email
                    ] : null
                ];
            });

        $recentReviews = DB::table('reviews')
            ->join('products', 'reviews.product_id', '=', 'products.id')
            ->join('users', 'reviews.user_id', '=', 'users.id')
            ->where('products.seller_id', $sellerId)
            ->select('reviews.*', 'products.name as product_name', 'users.name as   user_name')
            ->orderBy('reviews.created_at', 'desc')
            ->limit(5)
            ->get();

        return [
            'recent_orders' => $recentOrders,
            'recent_reviews' => $recentReviews
        ];
    }

    /**
     * Get performance metrics
     */
    private function getPerformanceMetrics($sellerId)
    {
        $thirtyDaysAgo = Carbon::now()->subDays(30);

        // Order completion rate
        $totalOrders = Order::where('seller_id', $sellerId)
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->count();

        $completedOrders = Order::where('seller_id', $sellerId)
            ->where('status', 'delivered')
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->count();

        $completionRate = $totalOrders > 0 ? ($completedOrders / $totalOrders) * 100 : 0;

        // Average rating
        $averageRating = DB::table('reviews')
            ->join('products', 'reviews.product_id', '=', 'products.id')
            ->where('products.seller_id', $sellerId)
            ->avg('reviews.rating');

        // Response time (average time to confirm orders)
        $avgResponseTime = DB::table('orders')
            ->where('seller_id', $sellerId)
            ->whereNotNull('confirmed_at')
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, created_at, confirmed_at)) as    avg_minutes')
            ->first();

        return [
            'order_completion_rate' => round($completionRate, 2),
            'average_rating' => $averageRating ? round($averageRating, 2) : 0,
            'average_response_time_minutes' => $avgResponseTime->avg_minutes ? round($avgResponseTime->avg_minutes) : 0,
            'customer_satisfaction' => $averageRating ? round(($averageRating / 5) * 100, 2) : 0
        ];
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        // Check if user is seller
        if (!isset($user->type) || $user->type !== 'seller') {
            return response()->json([
                'success' => false,
                'message' => 'User is not a seller'
            ], 403);
        }

        // ✅ Check if seller profile already exists
        $existingProfile = SellerProfile::where('user_id', $user->id)->first();
        if ($existingProfile) {
            return response()->json([
                'success' => false,
                'message' => 'Seller profile already exists'
            ], 409);
        }

        $validator = Validator::make($request->all(), [
            // Store Basic Info
            'store_name' => 'required|string|max:255|unique:seller_profiles,store_name',
            'business_type' => 'required|in:individual,company,retail,wholesale,manufacturer,service,partnership,   private_limited,public_limited,cooperative',
            'store_description' => 'nullable|string|max:2000',
            'contact_email' => 'required|email|max:255',
            'contact_phone' => 'required|string|max:20',
            'store_logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'store_banner' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120',

            // Business Details
            'business_registration_number' => 'nullable|string|max:255',
            'tax_id' => 'nullable|string|max:255',
            'website' => 'nullable|url|max:255',
            'account_number' => 'nullable|string|max:255',
            'social_facebook' => 'nullable|url|max:255',
            'social_instagram' => 'nullable|url|max:255',
            'social_twitter' => 'nullable|url|max:255',
            'social_linkedin' => 'nullable|url|max:255',

            // Address Info
            'address' => 'required|string|max:500',
            'city' => 'required|string|max:255',
            'state' => 'required|string|max:255',
            'country' => 'required|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'location' => 'nullable|string|max:255',

            // Additional Info
            'year_established' => 'nullable|integer|min:1900|max:' . date('Y'),
            'employees_count' => 'nullable|in:1-5,6-20,21-50,51-100,101-200,201-500,501+',
            'production_capacity' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $validated = $validator->validated();

            // Remove file fields from validated data as we'll handle them separately
            $fileFields = ['store_logo', 'store_banner'];
            $createData = array_diff_key($validated, array_flip($fileFields));

            // ✅ Create new seller profile (first time creation)
            $sellerProfile = SellerProfile::create(array_merge($createData, [
                'user_id' => $user->id,
                'store_id' => SellerProfile::generateStoreId(),
                'store_slug' => SellerProfile::generateStoreSlug($validated['store_name']),
                'status' => SellerProfile::STATUS_PENDING,
                'store_logo' => null,
                'store_banner' => null
            ]));

            // Handle store logo upload after profile creation
            if ($request->hasFile('store_logo')) {
                $logoPath = $this->uploadStoreLogo($request->file('store_logo'), $sellerProfile->id);
                if ($logoPath) {
                    $sellerProfile->update(['store_logo' => $logoPath]);
                }
            }

            // Handle store banner upload after profile creation
            if ($request->hasFile('store_banner')) {
                $bannerPath = $this->uploadStoreBanner($request->file('store_banner'), $sellerProfile->id);
                if ($bannerPath) {
                    $sellerProfile->update(['store_banner' => $bannerPath]);
                }
            }

            Log::info('Seller profile created for user: ' . $user->id . ', Profile ID: ' . $sellerProfile->id);

            return response()->json([
                'success' => true,
                'message' => 'Seller profile created successfully and submitted for approval',
                'data' => $sellerProfile->fresh()
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to create seller profile: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());

            // If profile was created but file upload failed, delete the profile
            if (isset($sellerProfile)) {
                $sellerProfile->delete();
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to create seller profile: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload store logo (public endpoint or internal usage)
     */
    public function uploadStoreLogo($requestOrFile, $sellerProfileId = null)
    {
        try {
            // Handle both Request object and UploadedFile object
            if ($requestOrFile instanceof Request) {
                $user = $requestOrFile->user();
                $sellerProfile = SellerProfile::where('user_id', $user->id)->first();

                if (!$sellerProfile) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Seller profile not found'
                    ], 404);
                }

                $validator = Validator::make($requestOrFile->all(), [
                    'image' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048'
                ]);

                if ($validator->fails()) {
                    return response()->json([
                        'success' => false,
                        'errors' => $validator->errors()
                    ], 422);
                }

                $file = $requestOrFile->file('image');
                $profileId = $sellerProfile->id;
            } else {
                // Direct file upload (internal usage)
                $file = $requestOrFile;
                $profileId = $sellerProfileId;
            }

            $path = $this->saveStoreLogo($file, $profileId);

            if (!$path) {
                if ($requestOrFile instanceof Request) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to upload logo'
                    ], 500);
                }
                return null;
            }

            // Update seller profile with logo path if Request object
            if ($requestOrFile instanceof Request) {
                $sellerProfile->update(['store_logo' => $path]);

                return response()->json([
                    'success' => true,
                    'message' => 'Store logo uploaded successfully',
                    'data' => [
                        'url' => url('storage/' . $path),
                        'path' => $path
                    ]
                ]);
            }

            // Return path if called internally
            return $path;

        } catch (\Exception $e) {
            Log::error('Failed to upload store logo: ' . $e->getMessage());
            if ($requestOrFile instanceof Request) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to upload store logo'
                ], 500);
            }
            return null;
        }
    }

    /**
     * Save store logo file
     */
    private function saveStoreLogo($file, $storeId)
    {
        $sellerProfile = SellerProfile::find($storeId);
        try {
            // Create organized path structure
            // $basePath = "stores/{$storeId}/logo";
            $basePath = "sellers/{$sellerProfile->id}/logo";

            // Ensure directory exists
            Storage::disk('public')->makeDirectory($basePath);

            // Generate unique filename
            $timestamp = time();
            $random = Str::random(8);
            $extension = $file->getClientOriginalExtension();
            $filename = "logo_{$timestamp}_{$random}.{$extension}";

            // Store the file - use $basePath, not $path
            $filePath = $file->storeAs($basePath, $filename, 'public'); // Change to $filePath

            Log::info('Store logo uploaded successfully', [
                'store_id' => $storeId,
                'path' => $filePath, // Use $filePath
                'filename' => $filename
            ]);

            return $filePath; // Return $filePath
        } catch (\Exception $e) {
            Log::error('Failed to upload store logo: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Upload store banner (public endpoint)
     */
    public function uploadStoreBanner(Request $request)
    {
        try {
            $user = $request->user();
            $sellerProfile = SellerProfile::where('user_id', $user->id)->first();

            if (!$sellerProfile) {
                return response()->json([
                    'success' => false,
                    'message' => 'Seller profile not found'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:5120'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $path = $this->saveStoreBanner($request->file('image'), $sellerProfile->id);

            if (!$path) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to upload banner'
                ], 500);
            }

            // Update seller profile with banner path
            $sellerProfile->update(['store_banner' => $path]);

            return response()->json([
                'success' => true,
                'message' => 'Store banner uploaded successfully',
                'data' => [
                    'url' => url('storage/' . $path),
                    'path' => $path
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to upload store banner: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload store banner'
            ], 500);
        }
    }

    /**
     * Save store banner file
     */
    private function saveStoreBanner($file, $storeId)
    {
        $sellerProfile = SellerProfile::find($storeId);
        try {
            // Create organized path structure
            // $basePath = "stores/{$storeId}/logo";
            $basePath = "sellers/{$sellerProfile->id}/banner"; // Change variable name

            // Ensure directory exists
            Storage::disk('public')->makeDirectory($basePath);

            // Generate unique filename
            $timestamp = time();
            $random = Str::random(8);
            $extension = $file->getClientOriginalExtension();
            $filename = "banner_{$timestamp}_{$random}.{$extension}";

            // Store the file - use $basePath, not $path
            $filePath = $file->storeAs($basePath, $filename, 'public'); // Change to $filePath

            Log::info('Store banner uploaded successfully', [
                'store_id' => $storeId,
                'path' => $filePath, // Use $filePath
                'filename' => $filename
            ]);

            return $filePath; // Return $filePath
        } catch (\Exception $e) {
            Log::error('Failed to upload store banner: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get my store details (authenticated seller)
     */
    public function myStore(Request $request)
    {
        $sellerProfile = SellerProfile::where('user_id', $request->user()->id)->first();

        if (!$sellerProfile) {
            return response()->json([
                'success' => false,
                'message' => 'Seller profile not found'
            ], 404);
        }

        $sellerProfileData = $sellerProfile->toArray();

        // Convert store logo/banner to full URLs
        $sellerProfileData['store_logo'] = !empty($sellerProfileData['store_logo'])
            ? url('storage/' . ltrim($sellerProfileData['store_logo'], '/'))
            : null;

        $sellerProfileData['store_banner'] = !empty($sellerProfileData['store_banner'])
            ? url('storage/' . ltrim($sellerProfileData['store_banner'], '/'))
            : null;

        // Convert product images to full URLs
        if (isset($sellerProfileData['products']['data'])) {
            foreach ($sellerProfileData['products']['data'] as &$product) {
                if (isset($product['images'])) {
                    foreach ($product['images'] as &$image) {
                        if (!str_starts_with($image['url'], 'http')) {
                            $image['url'] = url('storage/' . ltrim($image['url'], '/'));
                        }
                    }
                }
            }
        }

        return response()->json([
            'success' => true,
            'data' => $sellerProfileData
        ]);
    }


    /**
     * Get seller details (public endpoint)
     */
    public function show(SellerProfile $seller)
    {
        // Check if seller is approved/active (optional)
        if (!in_array($seller->status, ['approved', 'active'])) {
            return response()->json([
                'success' => false,
                'message' => 'Seller profile not found'
            ], 404);
        }

        // Get seller's products (only active ones)
        $products = Product::where('seller_id', $seller->user_id)
            ->where('is_active', true)
            ->with(['category'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->paginate(12);

        // Convert seller logo and banner to full URLs
        $sellerData = $seller->toArray();
        $sellerData['store_logo'] = !empty($sellerData['store_logo'])
            ? url('storage/' . ltrim($sellerData['store_logo'], '/'))
            : null;
        $sellerData['store_banner'] = !empty($sellerData['store_banner'])
            ? url('storage/' . ltrim($sellerData['store_banner'], '/'))
            : null;

        // Convert product images to full URLs
        if ($products->count() > 0) {
            $products->getCollection()->transform(function ($product) {
                if (isset($product['images'])) {
                    $images = is_string($product['images']) ? json_decode($product['images'], true) : $product['images'];
                    if (is_array($images)) {
                        foreach ($images as &$image) {
                            if (isset($image['url']) && !str_starts_with($image['url'], 'http')) {
                                $image['url'] = url('storage/' . ltrim($image['url'], '/'));
                            }
                        }
                        $product['images'] = $images;
                    }
                }
                return $product;
            });
        }

        // Get follow status and count
        $isFollowing = false;
        $followersCount = 0;

        try {
            if ($seller->user && method_exists($seller->user, 'followers')) {
                $followersCount = $seller->user->followers()->count();
                if (auth()->check() && method_exists(auth()->user(), 'isFollowing')) {
                    $isFollowing = auth()->user()->isFollowing($seller->user->id);
                }
            }
        } catch (\Exception $e) {
            \Log::warning('Follow functionality not available: ' . $e->getMessage());
        }

        // Get seller stats
        $stats = [
            'total_products' => Product::where('seller_id', $seller->user_id)->count(),
            'active_products' => Product::where('seller_id', $seller->user_id)
                ->where('is_active', true)->count(),
            'total_orders' => \App\Models\Order::where('seller_id', $seller->user_id)->count(),
            'total_sales' => \App\Models\Order::where('seller_id', $seller->user_id)
                ->where('status', 'delivered')->count(),
            'member_since' => $seller->created_at->format('M Y'),
            'followers_count' => $followersCount
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'seller' => $sellerData,
                'products' => $products,
                'stats' => $stats,
                'is_following' => $isFollowing
            ]
        ]);
    }

    /**
     * Update seller profile
     */
    public function update(Request $request, $id)
    {
        try {
            $seller = SellerProfile::findOrFail($id);
            $user = Auth::user();

            // Authorization check
            if ($user->id !== $seller->user_id && (!isset($user->type) || $user->type !== 'admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to update this seller profile'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'store_name' => 'sometimes|string|max:255|unique:seller_profiles,store_name,' . $id,
                'business_type' => 'sometimes|in:individual,company,retail,wholesale,manufacturer',
                'business_registration_number' => 'nullable|string|max:255',
                'tax_id' => 'nullable|string|max:255',
                'store_description' => 'nullable|string|max:2000',
                'contact_email' => 'sometimes|email|max:255',
                'contact_phone' => 'sometimes|string|max:20',
                'website' => 'nullable|url|max:255',
                'social_facebook' => 'nullable|url|max:255',
                'social_twitter' => 'nullable|url|max:255',
                'social_instagram' => 'nullable|url|max:255',
                'social_linkedin' => 'nullable|url|max:255',
                'social_youtube' => 'nullable|url|max:255',
                'address' => 'sometimes|string|max:500',
                'city' => 'sometimes|string|max:100',
                'state' => 'sometimes|string|max:100',
                'country' => 'sometimes|string|max:100',
                'postal_code' => 'nullable|string|max:20',
                'store_logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
                'store_banner' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120',
                'certificate' => 'nullable|string|max:500',
                'location' => 'nullable|string|max:255',
                'year_established' => 'nullable|integer|min:1900|max:' . date('Y'),
                'employees_count' => 'nullable|in:1-5,6-20,21-50,51-100,101-200,201-500,501+',
                'production_capacity' => 'nullable|string|max:255',
                'quality_certifications' => 'nullable|array',
                'quality_certifications.*' => 'string|max:255',
                'status' => 'sometimes|in:pending,approved,rejected,suspended,closed',
                'reason' => 'sometimes|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $validated = $validator->validated();

            // Handle store logo upload
            if ($request->hasFile('store_logo')) {
                $logoPath = $this->uploadStoreLogo($request->file('store_logo'), $seller->id);
                if ($logoPath) {
                    // Delete old logo if exists
                    if ($seller->store_logo) {
                        Storage::disk('public')->delete($seller->store_logo);
                    }
                    $validated['store_logo'] = $logoPath;
                }
            }

            // Handle store banner upload
            if ($request->hasFile('store_banner')) {
                $bannerPath = $this->uploadStoreBanner($request->file('store_banner'), $seller->id);
                if ($bannerPath) {
                    // Delete old banner if exists
                    if ($seller->store_banner) {
                        Storage::disk('public')->delete($seller->store_banner);
                    }
                    $validated['store_banner'] = $bannerPath;
                }
            }

            // Only admin can change status
            if (!isset($user->type) || $user->type !== 'admin' && isset($validated['status'])) {
                unset($validated['status']);
            }

            // Admin status update logic
            if (isset($user->type) && $user->type === 'admin' && isset($validated['status'])) {
                $seller->status = $validated['status'];

                // Optional: Store admin notes if reason is provided
                if (isset($validated['reason'])) {
                    $seller->admin_notes = $validated['reason'];
                }
            }

            // Regenerate slug if store name changes
            if (isset($validated['store_name']) && $validated['store_name'] !== $seller->store_name) {
                $validated['store_slug'] = SellerProfile::generateUniqueSlug($validated['store_name']);
            }

            $seller->update($validated);

            return response()->json([
                'success' => true,
                'data' => $seller->fresh(),
                'message' => 'Seller profile updated successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update seller profile: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update seller profile: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateMyStore(Request $request)
    {
        try {
            $user = $request->user();
            $seller = SellerProfile::where('user_id', $user->id)->first();

            if (!$seller) {
                return response()->json([
                    'success' => false,
                    'message' => 'Seller profile not found'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'store_name' => 'sometimes|string|max:255|unique:seller_profiles,store_name,' . $seller->id,
                'business_type' => 'sometimes|in:individual,company,retail,wholesale,manufacturer,service',
                'business_registration_number' => 'nullable|string|max:255',
                'tax_id' => 'nullable|string|max:255',
                'store_description' => 'nullable|string|max:2000',
                'contact_email' => 'sometimes|email|max:255',
                'contact_phone' => 'sometimes|string|max:20',
                'website' => 'nullable|url|max:255',
                'social_facebook' => 'nullable|url|max:255',
                'social_twitter' => 'nullable|url|max:255',
                'social_instagram' => 'nullable|url|max:255',
                'social_linkedin' => 'nullable|url|max:255',
                'address' => 'sometimes|string|max:500',
                'city' => 'sometimes|string|max:100',
                'state' => 'sometimes|string|max:100',
                'country' => 'sometimes|string|max:100',
                'postal_code' => 'nullable|string|max:20',
                'store_logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
                'store_banner' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120',
                'account_number' => 'nullable|string|max:255',
                'location' => 'nullable|string|max:255',
                'year_established' => 'nullable|integer|min:1900|max:' . date('Y'),
                'employees_count' => 'nullable|in:1-5,6-20,21-50,51-100,101-200,201-500,501+',
                'production_capacity' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $validated = $validator->validated();

            \Log::info('Updating store profile', [
                'user_id' => $user->id,
                'seller_id' => $seller->id,
                'has_logo_file' => $request->hasFile('store_logo'),
                'has_banner_file' => $request->hasFile('store_banner')
            ]);

            // Handle store logo upload
            if ($request->hasFile('store_logo')) {
                $logoPath = $this->uploadStoreLogo($request->file('store_logo'), $seller->id);
                if ($logoPath) {
                    // Delete old logo if exists
                    if ($seller->store_logo && Storage::disk('public')->exists($seller->store_logo)) {
                        Storage::disk('public')->delete($seller->store_logo);
                    }
                    $validated['store_logo'] = $logoPath;
                    \Log::info('Logo uploaded successfully', ['path' => $logoPath]);
                }
            } elseif ($request->has('store_logo') && is_string($request->store_logo)) {
                // Keep existing logo path if provided as string
                $validated['store_logo'] = $request->store_logo;
            }

            // Handle store banner upload
            if ($request->hasFile('store_banner')) {
                $bannerPath = $this->uploadStoreBanner($request->file('store_banner'), $seller->id);
                if ($bannerPath) {
                    // Delete old banner if exists
                    if ($seller->store_banner && Storage::disk('public')->exists($seller->store_banner)) {
                        Storage::disk('public')->delete($seller->store_banner);
                    }
                    $validated['store_banner'] = $bannerPath;
                    \Log::info('Banner uploaded successfully', ['path' => $bannerPath]);
                }
            } elseif ($request->has('store_banner') && is_string($request->store_banner)) {
                // Keep existing banner path if provided as string
                $validated['store_banner'] = $request->store_banner;
            }

            // Regenerate slug if store name changes
            if (isset($validated['store_name']) && $validated['store_name'] !== $seller->store_name) {
                $validated['store_slug'] = \App\Models\SellerProfile::generateUniqueSlug($validated['store_name']);
            }

            $seller->update($validated);

            \Log::info('Store profile updated successfully', [
                'seller_id' => $seller->id,
                'updated_fields' => array_keys($validated)
            ]);

            return response()->json([
                'success' => true,
                'data' => $seller->fresh(),
                'message' => 'Store profile updated successfully'
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to update store profile: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update store profile: ' . $e->getMessage()
            ], 500);
        }
    }


    /**
     * Delete seller profile (admin only)
     */
    public function destroy($id)
    {
        try {
            $seller = SellerProfile::findOrFail($id);

            // Delete associated files and directories
            if ($seller->store_logo) {
                Storage::disk('public')->delete($seller->store_logo);
            }
            if ($seller->store_banner) {
                Storage::disk('public')->delete($seller->store_banner);
            }
            if ($seller->certificate) {
                Storage::disk('public')->delete($seller->certificate);
            }

            // Delete the entire store profile directory if it exists
            $storeProfilePath = "store_profile/{$seller->id}";
            if (Storage::disk('public')->exists($storeProfilePath)) {
                Storage::disk('public')->deleteDirectory($storeProfilePath);
            }

            $seller->delete();

            return response()->json([
                'success' => true,
                'message' => 'Seller profile deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete seller profile: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get business types
     */
    public function getBusinessTypes()
    {
        $businessTypes = BusinessType::active()
            ->ordered()
            ->get()
            ->map(function ($type) {
                return [
                    'value' => $type->slug_en,
                    'label' => $type->name_en,
                    'description_en' => $type->description_en,
                    'requires_registration' => $type->requires_registration,
                    'document_requirements' => $type->getDocumentRequirements(),
                    'icon' => $type->icon,
                    'color' => $type->color,
                    'is_individual' => !$type->requires_business_certificate && !$type->requires_tax_document
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $businessTypes
        ]);
    }

    /**
     * Complete seller onboarding
     */
    public function completeOnboarding(Request $request)
    {
        try {
            $user = $request->user();

            // Check if user is seller
            if ($user->type !== 'seller') {
                return response()->json([
                    'success' => false,
                    'message' => 'User is not a seller'
                ], 403);
            }

            $sellerProfile = SellerProfile::where('user_id', $user->id)->first();

            if (!$sellerProfile) {
                return response()->json([
                    'success' => false,
                    'message' => 'Seller profile not found'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                // Store Basic Info
                'store_name' => 'required|string|max:255|unique:seller_profiles,store_name,' . $sellerProfile->id,
                // Use only the business types that exist in your database
                'business_type' => 'required|in:individual,company,retail,wholesale,service',
                'store_description' => 'nullable|string|max:2000',
                'contact_email' => 'required|email|max:255',
                'contact_phone' => 'required|string|max:20',
                'store_logo' => 'nullable|string|max:500',
                'store_banner' => 'nullable|string|max:500',

                // Business Details
                'business_registration_number' => 'nullable|string|max:255',
                'tax_id' => 'nullable|string|max:255',
                'website' => 'nullable|url|max:255',
                'account_number' => 'nullable|string|max:255',
                'social_facebook' => 'nullable|url|max:255',
                'social_instagram' => 'nullable|url|max:255',
                'social_twitter' => 'nullable|url|max:255',
                'social_linkedin' => 'nullable|url|max:255',

                // Address Info
                'address' => 'required|string|max:500',
                'city' => 'required|string|max:255',
                'state' => 'required|string|max:255',
                'country' => 'required|string|max:255',
                'postal_code' => 'nullable|string|max:20',
                'location' => 'nullable|string|max:255',

                // Additional Info
                'year_established' => 'nullable|integer|min:1900|max:' . date('Y'),
                'employees_count' => 'nullable|in:1-5,6-20,21-50,51-100,101-200,201-500,501+',
                'production_capacity' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $validated = $validator->validated();

            // Handle file uploads if provided as files
            if ($request->hasFile('store_logo')) {
                $logoPath = $this->uploadStoreLogo($request->file('store_logo'), $sellerProfile->id);
                if ($logoPath) {
                    $validated['store_logo'] = $logoPath;
                }
            } elseif ($request->has('store_logo') && is_string($request->store_logo)) {
                // If it's already a string path, use it directly
                $validated['store_logo'] = $request->store_logo;
            }

            if ($request->hasFile('store_banner')) {
                $bannerPath = $this->uploadStoreBanner($request->file('store_banner'), $sellerProfile->id);
                if ($bannerPath) {
                    $validated['store_banner'] = $bannerPath;
                }
            } elseif ($request->has('store_banner') && is_string($request->store_banner)) {
                // If it's already a string path, use it directly
                $validated['store_banner'] = $request->store_banner;
            }

            // Update seller profile
            $sellerProfile->update($validated);

            // Update status to pending for admin approval
            $sellerProfile->update(['status' => SellerProfile::STATUS_PENDING]);

            Log::info('Seller onboarding completed for user: ' . $user->id . ', Profile ID: ' . $sellerProfile->id);

            return response()->json([
                'success' => true,
                'message' => 'Seller onboarding completed successfully and submitted for approval',
                'data' => $sellerProfile->fresh()
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to complete seller onboarding: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Failed to complete seller onboarding: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get seller onboarding status
     */
    public function getOnboardingStatus(Request $request)
    {
        try {
            $user = $request->user();
            $isSeller = $user->type === 'seller' || $user->hasRole('seller');

            if (!$isSeller) {
                return response()->json(['success' => true, 'data' => ['is_seller' => false]]);
            }

            $sellerProfile = SellerProfile::where('user_id', $user->id)->first();

            if (!$sellerProfile) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'is_seller' => true,
                        'has_profile' => false,
                        'onboarding_complete' => false,
                        'needs_onboarding' => true,
                        'current_step' => 'store-basic',
                        'progress' => 0
                    ]
                ]);
            }

            // Better step detection
            $stepsCompleted = [];

            // Check store-basic
            if (
                !empty($sellerProfile->store_name) &&
                !empty($sellerProfile->business_type_id) &&
                !empty($sellerProfile->contact_email) &&
                !empty($sellerProfile->contact_phone)
            ) {
                $stepsCompleted[] = 'store-basic';
            }

            // Check business-details
            $businessType = $sellerProfile->businessType;
            $businessDetailsComplete = true;
            if ($businessType) {
                if ($businessType->requires_registration && empty($sellerProfile->business_registration_number)) {
                    $businessDetailsComplete = false;
                }
                if ($businessType->requires_tax_document && empty($sellerProfile->tax_id)) {
                    $businessDetailsComplete = false;
                }
            }
            if ($businessDetailsComplete) {
                $stepsCompleted[] = 'business-details';
            }

            // Check address
            if (
                !empty($sellerProfile->address) &&
                !empty($sellerProfile->city) &&
                !empty($sellerProfile->state) &&
                !empty($sellerProfile->country)
            ) {
                $stepsCompleted[] = 'address';
            }

            // Check documents
            if ($sellerProfile->documents_submitted) {
                $stepsCompleted[] = 'documents';
            }

            // Determine current step
            $stepOrder = ['store-basic', 'business-details', 'address', 'documents', 'review-submit'];
            $currentStep = 'store-basic';

            foreach ($stepOrder as $step) {
                if (!in_array($step, $stepsCompleted)) {
                    $currentStep = $step;
                    break;
                }
            }

            $progress = (count($stepsCompleted) / count($stepOrder)) * 100;

            return response()->json([
                'success' => true,
                'data' => [
                    'is_seller' => true,
                    'has_profile' => true,
                    'onboarding_complete' => $sellerProfile->onboarding_completed_at !== null,
                    'needs_onboarding' => $sellerProfile->onboarding_completed_at === null,
                    'current_step' => $currentStep,
                    'completed_steps' => $stepsCompleted,
                    'progress' => $progress,
                    'profile_status' => $sellerProfile->status
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error in getOnboardingStatus: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to get status'], 500);
        }
    }

    private function determineCurrentStep($sellerProfile)
    {
        if (empty($sellerProfile->store_name) || empty($sellerProfile->business_type_id)) {
            return 'store-basic';
        }

        if (empty($sellerProfile->business_registration_number) && $sellerProfile->business_type !== 'individual') {
            return 'business-details';
        }

        if (empty($sellerProfile->address) || empty($sellerProfile->city)) {
            return 'address';
        }

        if (!$sellerProfile->documents_submitted) {
            return 'documents';
        }

        return 'review-submit';
    }

    /**
     * Check and redirect seller based on profile status
     */
    public function checkProfileStatus(Request $request)
    {
        try {
            $user = $request->user();

            if ($user->type !== 'seller') {
                return response()->json([
                    'success' => false,
                    'message' => 'User is not a seller'
                ], 403);
            }

            $sellerProfile = SellerProfile::where('user_id', $user->id)->first();

            if (!$sellerProfile) {
                return response()->json([
                    'success' => true,
                    'redirect_to' => '/seller/onboarding/store-basic',
                    'message' => 'Seller profile not found. Redirect to onboarding.',
                    'data' => [
                        'has_profile' => false,
                        'profile_status' => 'not_created'
                    ]
                ]);
            }

            // Check if profile is complete
            if (!$sellerProfile->hasCompleteProfile()) {
                $missingFields = $sellerProfile->getMissingFields();
                $currentStep = $sellerProfile->getOnboardingStep();

                return response()->json([
                    'success' => true,
                    'redirect_to' => "/seller/onboarding/{$currentStep}",
                    'message' => 'Profile incomplete. Please complete missing information.',
                    'data' => [
                        'has_profile' => true,
                        'profile_complete' => false,
                        'missing_fields' => $missingFields,
                        'current_step' => $currentStep,
                        'profile_status' => $sellerProfile->status
                    ]
                ]);
            }

            // Check if required documents are uploaded
            if (!$sellerProfile->hasRequiredDocuments()) {
                $missingDocuments = $sellerProfile->getMissingDocuments();
                return response()->json([
                    'success' => true,
                    'redirect_to' => '/seller/onboarding/documents',
                    'message' => 'Documents required. Please upload required documents.',
                    'data' => [
                        'has_profile' => true,
                        'profile_complete' => true,
                        'documents_complete' => false,
                        'missing_documents' => $missingDocuments,
                        'profile_status' => $sellerProfile->status
                    ]
                ]);
            }

            // Check if documents are submitted for review
            if (!$sellerProfile->documents_submitted) {
                return response()->json([
                    'success' => true,
                    'redirect_to' => '/seller/onboarding/submit',
                    'message' => 'Documents uploaded. Please review and submit for verification.',
                    'data' => [
                        'has_profile' => true,
                        'profile_complete' => true,
                        'documents_complete' => true,
                        'documents_submitted' => false,
                        'profile_status' => $sellerProfile->status
                    ]
                ]);
            }

            // Check if profile is verified
            if (!$sellerProfile->isVerified()) {
                return response()->json([
                    'success' => true,
                    'redirect_to' => '/seller/dashboard',
                    'message' => 'Profile under review. You can view dashboard but cannot sell yet.',
                    'data' => [
                        'has_profile' => true,
                        'profile_complete' => true,
                        'documents_complete' => true,
                        'documents_submitted' => true,
                        'verified' => false,
                        'verification_status' => $sellerProfile->verification_status,
                        'profile_status' => $sellerProfile->status,
                        'can_sell' => false
                    ]
                ]);
            }

            // Profile is complete and verified
            return response()->json([
                'success' => true,
                'redirect_to' => '/seller/dashboard',
                'message' => 'Profile complete and verified.',
                'data' => [
                    'has_profile' => true,
                    'profile_complete' => true,
                    'documents_complete' => true,
                    'documents_submitted' => true,
                    'verified' => true,
                    'verified_at' => $sellerProfile->verified_at,
                    'verification_status' => $sellerProfile->verification_status,
                    'verification_level' => $sellerProfile->verification_level,
                    'profile_status' => $sellerProfile->status,
                    'can_sell' => true
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error checking profile status: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to check profile status'
            ], 500);
        }
    }

    /**
     * Update store basic information
     */
    public function updateStoreBasic(Request $request)
    {
        try {
            $user = $request->user();
            $sellerProfile = SellerProfile::where('user_id', $user->id)->first();

            if (!$sellerProfile) {
                return response()->json([
                    'success' => false,
                    'message' => 'Seller profile not found'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'store_name' => 'required|string|max:255|unique:seller_profiles,store_name,' . $sellerProfile->id,
                'business_type_slug' => 'required|exists:business_types,slug_en',
                'store_description' => 'nullable|string|max:2000',
                'contact_email' => 'required|email|max:255',
                'contact_phone' => 'required|string|max:20',
                'store_logo' => 'nullable|string|max:500',
                'store_banner' => 'nullable|string|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $validated = $validator->validated();

            $businessType = BusinessType::where('slug_en', $validated['business_type_slug'])->first();

            if (!$businessType) {
                return response()->json([
                    'success' => false,
                    'message' => 'Business type not found'
                ], 422);
            }

            // Handle store logo - convert URL to storage path if needed
            $logoPath = $sellerProfile->store_logo; // Keep existing by default
            if (isset($validated['store_logo']) && is_string($validated['store_logo'])) {
                $logoValue = $validated['store_logo'];

                if (str_starts_with($logoValue, url('storage/'))) {
                    $logoPath = str_replace(url('storage/'), '', $logoValue);

                    if (!Storage::disk('public')->exists($logoPath)) {
                        Log::warning('Logo file does not exist in storage: ' . $logoPath);
                        $logoPath = $sellerProfile->store_logo;
                    }
                }
                // If it's already a storage path (not a full URL)
                else if (!str_starts_with($logoValue, 'http')) {
                    // Check if it's a valid storage path
                    if (Storage::disk('public')->exists($logoValue)) {
                        $logoPath = $logoValue;
                    } else {
                        Log::warning('Invalid logo storage path: ' . $logoValue);
                        $logoPath = $sellerProfile->store_logo;
                    }
                }
                // If it's some other URL format, reject it
                else {
                    Log::warning('Invalid logo URL format - must be from our storage: ' . $logoValue);
                    $logoPath = $sellerProfile->store_logo; // Keep existing
                }
            }

            // Handle store banner - same logic
            $bannerPath = $sellerProfile->store_banner; // Keep existing by default
            if (isset($validated['store_banner']) && is_string($validated['store_banner'])) {
                $bannerValue = $validated['store_banner'];

                if (str_starts_with($bannerValue, url('storage/'))) {
                    $extractedPath = str_replace(url('storage/'), '', $bannerValue);

                    if (Storage::disk('public')->exists($extractedPath)) {
                        $bannerPath = $extractedPath;
                    } else {
                        Log::warning('Banner file does not exist in storage: ' . $extractedPath);
                        $bannerPath = $sellerProfile->store_banner;
                    }
                } else if (!str_starts_with($bannerValue, 'http')) {
                    if (Storage::disk('public')->exists($bannerValue)) {
                        $bannerPath = $bannerValue;
                    } else {
                        Log::warning('Invalid banner storage path: ' . $bannerValue);
                        $bannerPath = $sellerProfile->store_banner;
                    }
                } else {
                    Log::warning('Invalid banner URL format - must be from our storage: ' . $bannerValue);
                    $bannerPath = $sellerProfile->store_banner;
                }
            }

            $storeSlug = $sellerProfile->store_slug;
            if ($validated['store_name'] !== $sellerProfile->store_name) {
                $storeSlug = SellerProfile::generateStoreSlug($validated['store_name']);
            }

            $updateData = [
                'store_name' => $validated['store_name'],
                'store_slug' => $storeSlug,
                'business_type_id' => $businessType->id,
                'business_type' => $businessType->slug,
                'store_description' => $validated['store_description'] ?? null,
                'contact_email' => $validated['contact_email'],
                'contact_phone' => $validated['contact_phone'],
                'store_logo' => $logoPath,
                'store_banner' => $bannerPath,
            ];

            // Update status if it's still in setup_pending
            if ($sellerProfile->status === SellerProfile::STATUS_SETUP_PENDING) {
                $updateData['status'] = SellerProfile::STATUS_PENDING;
            }

            // Update seller profile
            $sellerProfile->update($updateData);

            // Load the business type relationship
            $sellerProfile->load('businessType');

            Log::info('Store basic info updated', [
                'user_id' => $user->id,
                'seller_profile_id' => $sellerProfile->id,
                'business_type_id' => $businessType->id,
                'store_name' => $validated['store_name'],
                'logo_updated' => $logoPath !== $sellerProfile->getOriginal('store_logo'),
                'banner_updated' => $bannerPath !== $sellerProfile->getOriginal('store_banner'),
                'logo_path' => $logoPath,
                'banner_path' => $bannerPath
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Store basic information updated successfully',
                'data' => [
                    'seller_profile' => $sellerProfile,
                    'business_type' => [
                        'id' => $businessType->id,
                        'slug_en' => $businessType->slug_en,
                        'name' => $businessType->name,
                        'description_en' => $businessType->description_en,
                        'is_individual' => $businessType->isIndividualType(),
                        'requires_registration' => $businessType->requires_registration,
                        'requires_tax_document' => $businessType->requires_tax_document,
                        'document_requirements' => $businessType->getDocumentRequirements()
                    ],
                    'media_urls' => [
                        'store_logo' => $sellerProfile->store_logo ? url('storage/' . $sellerProfile->store_logo) : null,
                        'store_banner' => $sellerProfile->store_banner ? url('storage/' . $sellerProfile->store_banner) : null
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update store basic info: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update store basic information: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update business details
     */
    public function updateBusinessDetails(Request $request)
    {
        try {
            $user = $request->user();
            $sellerProfile = SellerProfile::where('user_id', $user->id)
                ->with('businessType')
                ->first();

            if (!$sellerProfile) {
                return response()->json([
                    'success' => false,
                    'message' => 'Seller profile not found'
                ], 404);
            }

            // Build validation rules dynamically
            $rules = [
                'website' => 'nullable|url|max:255',
                'account_number' => 'nullable|string|max:255',
                'social_facebook' => 'nullable|url|max:255',
                'social_instagram' => 'nullable|url|max:255',
                'social_twitter' => 'nullable|url|max:255',
                'social_linkedin' => 'nullable|url|max:255',
            ];

            // Add conditional rules
            $businessType = $sellerProfile->businessType;
            if ($businessType && !$businessType->isIndividualType()) {
                if ($businessType->requires_registration) {
                    $rules['business_registration_number'] = 'required|string|max:255';
                }
                if ($businessType->requires_tax_document) {
                    $rules['tax_id'] = 'required|string|max:255';
                }
            } else {
                $rules['business_registration_number'] = 'nullable|string|max:255';
                $rules['tax_id'] = 'nullable|string|max:255';
            }

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $validated = $validator->validated();
            $sellerProfile->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Business details updated successfully',
                'next_step' => 'address',  // Explicit next step
                'data' => $sellerProfile->fresh()
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update business details: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update business details: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update address information
     */
    public function updateAddress(Request $request)
    {
        try {
            $user = $request->user();
            $sellerProfile = SellerProfile::where('user_id', $user->id)->first();

            if (!$sellerProfile) {
                return response()->json([
                    'success' => false,
                    'message' => 'Seller profile not found'
                ], 404);
            }

            // Get country options for validation
            $countries = [
                'Myanmar',
                'Thailand',
                'China',
                'India',
                'Bangladesh',
                'Laos',
                'Cambodia',
                'Vietnam',
                'Singapore',
                'Malaysia',
                'Indonesia',
                'Philippines',
                'Japan',
                'South Korea'
            ];

            $validator = Validator::make($request->all(), [
                'address' => 'required|string|max:500',
                'city' => 'required|string|max:100',
                'state' => 'required|string|max:100',
                'country' => 'required|string|max:100|in:' . implode(',', $countries),
                'postal_code' => 'nullable|string|max:20',
                'location' => 'nullable|string|max:255',
                'latitude' => 'nullable|numeric|between:-90,90',
                'longitude' => 'nullable|numeric|between:-180,180',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $validated = $validator->validated();

            // Handle location from coordinates if provided
            if (isset($validated['latitude']) && isset($validated['longitude'])) {
                if (!isset($validated['location'])) {
                    $validated['location'] = $validated['latitude'] . ',' . $validated['longitude'];
                }
            }

            // Prepare update data
            $updateData = [
                'address' => $validated['address'],
                'city' => $validated['city'],
                'state' => $validated['state'],
                'country' => $validated['country'],
                'postal_code' => $validated['postal_code'] ?? null,
                'location' => $validated['location'] ?? null,
            ];

            // Update the seller profile
            $sellerProfile->update($updateData);

            // Update status if still in pending state
            if ($sellerProfile->status === SellerProfile::STATUS_SETUP_PENDING) {
                $sellerProfile->update(['status' => SellerProfile::STATUS_PENDING]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Address information updated successfully',
                'data' => $sellerProfile->fresh()
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update address info: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update address information: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload document with business type validation
     */
    public function uploadDocument(Request $request)
    {
        try {
            $user = $request->user();

            if ($user->type !== 'seller') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only sellers can upload documents'
                ], 403);
            }

            $sellerProfile = SellerProfile::where('user_id', $user->id)
                ->with('businessType')
                ->first();

            if (!$sellerProfile) {
                return response()->json([
                    'success' => false,
                    'message' => 'Seller profile not found'
                ], 404);
            }

            // Get business type requirements
            $businessType = $sellerProfile->businessType;

            if (!$businessType) {
                return response()->json([
                    'success' => false,
                    'message' => 'Business type not found. Please complete store basic information first.'
                ], 422);
            }

            // Get allowed document types for this business type
            $allowedTypes = $this->getAllowedDocumentTypes($businessType);

            $validator = Validator::make($request->all(), [
                'document_type' => ['required', 'string', 'in:' . implode(',', $allowedTypes)],
                'document' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $documentType = $request->document_type;
            $file = $request->file('document');

            // Validate file against specific requirements
            $validationError = $this->validateDocumentFile($file, $documentType, $businessType);
            if ($validationError) {
                return response()->json([
                    'success' => false,
                    'message' => $validationError
                ], 422);
            }

            // Generate unique filename
            $timestamp = time();
            $random = Str::random(8);
            $extension = $file->getClientOriginalExtension();
            $filename = "{$documentType}_{$timestamp}_{$random}.{$extension}";

            // Create organized path structure
            $path = "sellers/{$sellerProfile->id}/documents/{$documentType}";

            // Store file
            $filePath = $file->storeAs($path, $filename, 'public');

            // Update seller profile with document path
            if ($documentType === 'additional_documents') {
                // Handle additional documents array
                $additionalDocs = $sellerProfile->additional_documents ?? [];
                $additionalDocs[] = [
                    'name' => $file->getClientOriginalName(),
                    'path' => $filePath,
                    'type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                    'uploaded_at' => now()->toISOString(),
                    'verified' => false
                ];
                $sellerProfile->additional_documents = $additionalDocs;
            } else {
                // Delete old file if exists
                if (!empty($sellerProfile->$documentType)) {
                    Storage::disk('public')->delete($sellerProfile->$documentType);
                }
                $sellerProfile->$documentType = $filePath;
            }

            $sellerProfile->save();

            // Log the upload
            Log::info('Document uploaded', [
                'user_id' => $user->id,
                'seller_profile_id' => $sellerProfile->id,
                'document_type' => $documentType,
                'business_type' => $businessType->slug_en,
                'file_path' => $filePath
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Document uploaded successfully',
                'data' => [
                    'type' => $documentType,
                    'url' => Storage::url($filePath),
                    'path' => $filePath,
                    'uploaded_at' => now()->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Document upload failed: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload document: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get allowed document types based on business type
     */
    private function getAllowedDocumentTypes($businessType)
    {
        $allowedTypes = [];

        // Identity documents are always required for all business types
        $allowedTypes[] = 'identity_document_front';
        $allowedTypes[] = 'identity_document_back';

        // Business registration document for registered businesses
        if ($businessType->requires_registration) {
            $allowedTypes[] = 'business_registration_document';
        }

        // Tax document for businesses that need it
        if ($businessType->requires_tax_document) {
            $allowedTypes[] = 'tax_registration_document';
        }

        // Business certificate for specific business types
        if ($businessType->requires_business_certificate) {
            $allowedTypes[] = 'business_certificate';
        }

        // Additional documents are always allowed
        $allowedTypes[] = 'additional_documents';

        return array_unique($allowedTypes);
    }

    /**
     * Validate document file based on type and business requirements
     */
    private function validateDocumentFile($file, $documentType, $businessType)
    {
        // File type validation
        $allowedMimes = [
            'pdf' => 'application/pdf',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png'
        ];

        $mimeType = $file->getMimeType();
        $extension = strtolower($file->getClientOriginalExtension());

        if (!in_array($mimeType, $allowedMimes) || !array_key_exists($extension, $allowedMimes)) {
            return 'Invalid file type. Only PDF, JPG, JPEG, PNG files are allowed.';
        }

        // File size validation
        $maxSize = 5 * 1024 * 1024; // 5MB in bytes

        // Stricter size limits for identity documents
        $identityDocTypes = ['identity_document_front', 'identity_document_back'];
        if (in_array($documentType, $identityDocTypes)) {
            $maxSize = 2 * 1024 * 1024; // 2MB for identity documents
        }

        if ($file->getSize() > $maxSize) {
            $maxSizeMB = $maxSize / (1024 * 1024);
            return "File size exceeds maximum limit of {$maxSizeMB}MB.";
        }

        // Business-specific validation
        if ($documentType === 'business_registration_document' && !$businessType->requires_registration) {
            return 'Business registration document is not required for your business type.';
        }

        if ($documentType === 'tax_registration_document' && !$businessType->requires_tax_document) {
            return 'Tax registration document is not required for your business type.';
        }

        if ($documentType === 'business_certificate' && !$businessType->requires_business_certificate) {
            return 'Business certificate is not required for your business type.';
        }

        return null; // No validation errors
    }

    /**
     * Get document requirements based on business type
     */
    public function getDocumentRequirements(Request $request)
    {
        try {
            $user = $request->user();

            if ($user->type !== 'seller') {
                return response()->json([
                    'success' => false,
                    'message' => 'User is not a seller'
                ], 403);
            }

            $sellerProfile = SellerProfile::where('user_id', $user->id)
                ->with('businessType')
                ->first();

            if (!$sellerProfile) {
                return response()->json([
                    'success' => false,
                    'message' => 'Seller profile not found'
                ], 404);
            }

            if (!$sellerProfile->businessType) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please select a business type first'
                ], 422);
            }

            $businessType = $sellerProfile->businessType;

            // Generate requirements based on business type
            $requirements = $this->generateDocumentRequirements($businessType);

            // Check which documents are already uploaded
            $uploadedDocuments = [];
            $missingDocuments = [];

            foreach ($requirements as $req) {
                $field = $req['type'];
                $isUploaded = !empty($sellerProfile->$field);

                $uploadedDocuments[$field] = [
                    'uploaded' => $isUploaded,
                    'url' => $isUploaded ? $sellerProfile->getDocumentUrl($field) : null,
                    'name' => $isUploaded ? basename($sellerProfile->$field) : null
                ];

                if ($req['required'] && !$isUploaded) {
                    $missingDocuments[] = $req['label'];
                }
            }

            // Get additional documents
            $additionalDocuments = $sellerProfile->getAdditionalDocuments() ?? [];

            return response()->json([
                'success' => true,
                'data' => [
                    'business_type' => [
                        'id' => $businessType->id,
                        'name_en' => $businessType->name_en,
                        'slug_en' => $businessType->slug_en,
                        'description_en' => $businessType->description_en,
                    ],
                    'is_individual' => $businessType->isIndividualType(),
                    'requirements' => $requirements,
                    'uploaded_documents' => $uploadedDocuments,
                    'additional_documents' => $additionalDocuments,
                    'missing_documents' => $missingDocuments,
                    'documents_submitted' => $sellerProfile->documents_submitted,
                    'document_status' => $sellerProfile->document_status,
                    'document_rejection_reason' => $sellerProfile->document_rejection_reason,
                    'requirements_summary' => $this->getRequirementsSummary($businessType)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get document requirements: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get document requirements: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate document requirements based on business type
     */
    private function generateDocumentRequirements($businessType)
    {
        $requirements = [];

        // Identity Documents (always required)
        $requirements[] = [
            'type' => 'identity_document_front',
            'label' => 'Front of Identity Document',
            'description' => 'Clear photo of the front side of your ID card, passport, or driving license',
            'required' => true,
            'accepted_formats' => 'jpg, jpeg, png',
            'max_size' => '2MB',
            'help_text' => 'Make sure all information is clearly visible'
        ];

        $requirements[] = [
            'type' => 'identity_document_back',
            'label' => 'Back of Identity Document',
            'description' => 'Clear photo of the back side of your ID card or passport',
            'required' => true,
            'accepted_formats' => 'jpg, jpeg, png',
            'max_size' => '2MB',
            'help_text' => 'Make sure all information is clearly visible'
        ];

        // Business Registration Document
        if ($businessType->requires_registration) {
            $requirements[] = [
                'type' => 'business_registration_document',
                'label' => 'Business Registration Certificate',
                'description' => 'Official business registration certificate from government authority',
                'required' => true,
                'accepted_formats' => 'pdf, jpg, jpeg, png',
                'max_size' => '5MB',
                'help_text' => 'Upload the complete registration document'
            ];
        }

        // Tax Registration Document
        if ($businessType->requires_tax_document) {
            $requirements[] = [
                'type' => 'tax_registration_document',
                'label' => 'Tax Registration Certificate',
                'description' => 'Tax identification registration document',
                'required' => true,
                'accepted_formats' => 'pdf, jpg, jpeg, png',
                'max_size' => '5MB',
                'help_text' => 'Official tax registration document with TIN'
            ];
        }

        // Business Certificate
        if ($businessType->requires_business_certificate) {
            $requirements[] = [
                'type' => 'business_certificate',
                'label' => 'Business License/Certificate',
                'description' => 'Business operating license or certificate',
                'required' => true,
                'accepted_formats' => 'pdf, jpg, jpeg, png',
                'max_size' => '5MB',
                'help_text' => 'Valid business license or certification document'
            ];
        }

        // Additional Documents (optional for all)
        $requirements[] = [
            'type' => 'additional_documents',
            'label' => 'Additional Supporting Documents',
            'description' => 'Any other documents that support your business verification',
            'required' => false,
            'accepted_formats' => 'pdf, jpg, jpeg, png',
            'max_size' => '5MB',
            'help_text' => 'Bank statements, utility bills, additional certifications, etc.'
        ];

        return $requirements;
    }

    /**
     * Get human-readable requirements summary
     */
    private function getRequirementsSummary($businessType)
    {
        $summary = [
            'identity_documents' => 'Identity proof (front & back)',
        ];

        if ($businessType->requires_registration) {
            $summary['business_registration'] = 'Business registration certificate';
        }

        if ($businessType->requires_tax_document) {
            $summary['tax_document'] = 'Tax registration document';
        }

        if ($businessType->requires_business_certificate) {
            $summary['business_certificate'] = 'Business license/certificate';
        }

        $summary['additional_documents'] = 'Additional supporting documents (optional)';

        return $summary;
    }

    /**
     * Mark documents as complete
     */
    public function markDocumentsComplete(Request $request)
    {
        try {
            $user = $request->user();
            $sellerProfile = SellerProfile::where('user_id', $user->id)
                ->with('businessType')
                ->first();

            if (!$sellerProfile) {
                return response()->json([
                    'success' => false,
                    'message' => 'Seller profile not found'
                ], 404);
            }

            // Get business type requirements
            $businessType = $sellerProfile->businessType;
            if (!$businessType) {
                return response()->json([
                    'success' => false,
                    'message' => 'Business type not found. Please complete store basic information.'
                ], 422);
            }

            // Check required documents based on business type
            $missingDocuments = $this->getMissingRequiredDocuments($sellerProfile, $businessType);

            if (!empty($missingDocuments)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please upload all required documents',
                    'missing_documents' => $missingDocuments,
                    'requirements' => $this->generateDocumentRequirements($businessType)
                ], 422);
            }

            // Verify document files exist
            $invalidDocuments = $this->validateDocumentFilesExist($sellerProfile, $businessType);
            if (!empty($invalidDocuments)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Some uploaded documents are invalid or missing',
                    'invalid_documents' => $invalidDocuments
                ], 422);
            }

            // Mark documents as submitted
            $sellerProfile->update([
                'documents_submitted' => true,
                'documents_submitted_at' => now(),
                'document_status' => 'pending', // Use string directly
                'verification_status' => 'under_review', // Use string directly
                'status' => $sellerProfile->status === 'setup_pending' ? 'pending' : $sellerProfile->status
            ]);

            // Log document submission
            Log::info('Documents marked as complete', [
                'user_id' => $user->id,
                'seller_profile_id' => $sellerProfile->id,
                'business_type' => $businessType->slug_en,
                'submitted_at' => now()->toISOString()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Documents submitted successfully for verification',
                'data' => [
                    'seller_profile' => $sellerProfile,
                    'next_steps' => [
                        'verification_time' => '1-3 business days',
                        'notification' => 'You will receive an email when verification is complete'
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to mark documents complete: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark documents as complete: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get missing required documents based on business type
     */
    private function getMissingRequiredDocuments($sellerProfile, $businessType)
    {
        $missing = [];

        // Always check identity documents
        if (empty($sellerProfile->identity_document_front)) {
            $missing[] = 'Front of Identity Document';
        }

        if (empty($sellerProfile->identity_document_back)) {
            $missing[] = 'Back of Identity Document';
        }

        // Check business registration
        if ($businessType->requires_registration && empty($sellerProfile->business_registration_document)) {
            $missing[] = 'Business Registration Certificate';
        }

        // Check tax document
        if ($businessType->requires_tax_document && empty($sellerProfile->tax_registration_document)) {
            $missing[] = 'Tax Registration Certificate';
        }

        // Check business certificate
        if ($businessType->requires_business_certificate && empty($sellerProfile->business_certificate)) {
            $missing[] = 'Business License/Certificate';
        }

        return $missing;
    }

    /**
     * Validate that uploaded document files actually exist
     */
    private function validateDocumentFilesExist($sellerProfile, $businessType)
    {
        $invalid = [];

        $documentsToCheck = [
            'identity_document_front',
            'identity_document_back'
        ];

        if ($businessType->requires_registration) {
            $documentsToCheck[] = 'business_registration_document';
        }

        if ($businessType->requires_tax_document) {
            $documentsToCheck[] = 'tax_registration_document';
        }

        if ($businessType->requires_business_certificate) {
            $documentsToCheck[] = 'business_certificate';
        }

        foreach ($documentsToCheck as $field) {
            if (!empty($sellerProfile->$field) && !Storage::disk('public')->exists($sellerProfile->$field)) {
                $invalid[] = [
                    'type' => $field,
                    'path' => $sellerProfile->$field,
                    'error' => 'File not found'
                ];
            }
        }

        return $invalid;
    }

    /**
     * Delete a specific document
     */
    public function deleteDocument(Request $request, $documentType)
    {
        try {
            $user = $request->user();

            if ($user->type !== 'seller') {
                return response()->json([
                    'success' => false,
                    'message' => 'User is not a seller'
                ], 403);
            }

            $sellerProfile = SellerProfile::where('user_id', $user->id)->first();

            if (!$sellerProfile) {
                return response()->json([
                    'success' => false,
                    'message' => 'Seller profile not found'
                ], 404);
            }

            // Check if documents are already submitted
            if ($sellerProfile->documents_submitted) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete documents after submission. Contact support for changes.'
                ], 403);
            }

            // Handle regular document fields
            $documentFields = [
                'business_registration_document',
                'tax_registration_document',
                'identity_document_front',
                'identity_document_back',
                'business_certificate'
            ];

            if (in_array($documentType, $documentFields)) {
                // Delete regular document
                if (!empty($sellerProfile->$documentType)) {
                    Storage::disk('public')->delete($sellerProfile->$documentType);
                    $sellerProfile->update([$documentType => null]);
                }
            } elseif ($documentType === 'additional_documents') {
                // Handle additional documents - delete all or specific?
                if ($request->has('index')) {
                    // Delete specific additional document
                    $additionalDocs = $sellerProfile->additional_documents ?? [];
                    $index = $request->input('index');

                    if (isset($additionalDocs[$index])) {
                        if (isset($additionalDocs[$index]['path'])) {
                            Storage::disk('public')->delete($additionalDocs[$index]['path']);
                        }
                        unset($additionalDocs[$index]);
                        $sellerProfile->additional_documents = array_values($additionalDocs);
                        $sellerProfile->save();
                    }
                } else {
                    // Delete all additional documents
                    $additionalDocs = $sellerProfile->additional_documents ?? [];
                    foreach ($additionalDocs as $doc) {
                        if (isset($doc['path'])) {
                            Storage::disk('public')->delete($doc['path']);
                        }
                    }
                    $sellerProfile->additional_documents = [];
                    $sellerProfile->save();
                }
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid document type'
                ], 422);
            }

            // Log deletion
            Log::info('Document deleted', [
                'user_id' => $user->id,
                'seller_profile_id' => $sellerProfile->id,
                'document_type' => $documentType
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Document deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to delete document: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete document: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get uploaded documents with validation
     */
    public function getUploadedDocuments(Request $request)
    {
        try {
            $user = $request->user();

            if ($user->type !== 'seller') {
                return response()->json([
                    'success' => false,
                    'message' => 'User is not a seller'
                ], 403);
            }

            $sellerProfile = SellerProfile::where('user_id', $user->id)
                ->with('businessType')
                ->first();

            if (!$sellerProfile) {
                return response()->json([
                    'success' => false,
                    'message' => 'Seller profile not found'
                ], 404);
            }

            $businessType = $sellerProfile->businessType;
            $requirements = $businessType ? $this->generateDocumentRequirements($businessType) : [];

            $documents = [];
            $documentStatus = [];

            foreach ($requirements as $req) {
                $field = $req['type'];

                if ($field === 'additional_documents') {
                    continue; // Handle separately
                }

                if (!empty($sellerProfile->$field)) {
                    $fileExists = Storage::disk('public')->exists($sellerProfile->$field);

                    $documents[$field] = [
                        'name' => basename($sellerProfile->$field),
                        'url' => $fileExists ? $sellerProfile->getDocumentUrl($field) : null,
                        'uploaded_at' => $sellerProfile->updated_at->toISOString(),
                        'file_exists' => $fileExists,
                        'required' => $req['required'],
                        'status' => $fileExists ? 'uploaded' : 'missing'
                    ];

                    $documentStatus[$field] = $fileExists ? 'uploaded' : 'invalid';
                } else {
                    $documentStatus[$field] = 'missing';
                }
            }

            // Get additional documents
            $additionalDocuments = $sellerProfile->getAdditionalDocuments() ?? [];

            // Calculate completion status
            $totalRequired = count(array_filter($requirements, fn($req) => $req['required'] && $req['type'] !== 'additional_documents'));
            $uploadedRequired = count(array_filter(
                $documentStatus,
                fn($status, $type) =>
                $status === 'uploaded' &&
                in_array($type, array_column(array_filter($requirements, fn($req) => $req['required']), 'type'))
                ,
                ARRAY_FILTER_USE_BOTH
            ));

            $completionPercentage = $totalRequired > 0 ? ($uploadedRequired / $totalRequired) * 100 : 0;

            return response()->json([
                'success' => true,
                'data' => [
                    'documents' => $documents,
                    'additional_documents' => $additionalDocuments,
                    'document_status' => $documentStatus,
                    'requirements' => $requirements,
                    'completion' => [
                        'percentage' => $completionPercentage,
                        'uploaded_required' => $uploadedRequired,
                        'total_required' => $totalRequired,
                        'is_complete' => $uploadedRequired >= $totalRequired
                    ],
                    'documents_submitted' => $sellerProfile->documents_submitted,
                    'document_status' => $sellerProfile->document_status,
                    'verification_status' => $sellerProfile->verification_status
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get uploaded documents: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get uploaded documents'
            ], 500);
        }
    }

    /**
     * Complete onboarding with documents validation
     */
    public function completeOnboardingWithDocuments(Request $request)
    {
        try {
            $user = $request->user();

            if ($user->type !== 'seller') {
                return response()->json([
                    'success' => false,
                    'message' => 'User is not a seller'
                ], 403);
            }

            $sellerProfile = SellerProfile::where('user_id', $user->id)
                ->with('businessType')
                ->first();

            if (!$sellerProfile) {
                return response()->json([
                    'success' => false,
                    'message' => 'Seller profile not found'
                ], 404);
            }

            // Get business type
            $businessType = $sellerProfile->businessType;
            if (!$businessType) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please select a business type first'
                ], 422);
            }

            // Check if all required documents are uploaded
            $missingDocs = $this->getMissingRequiredDocuments($sellerProfile, $businessType);
            if (!empty($missingDocs)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please upload all required documents',
                    'missing_documents' => $missingDocs
                ], 422);
            }

            // Check if profile is complete
            if (!$sellerProfile->hasCompleteProfile()) {
                $missingFields = $sellerProfile->getMissingFields();
                return response()->json([
                    'success' => false,
                    'message' => 'Please complete all required fields',
                    'missing_fields' => $missingFields
                ], 422);
            }

            // Mark documents as submitted
            $sellerProfile->update([
                'documents_submitted' => true,
                'documents_submitted_at' => now(),
                'document_status' => 'pending',
                'verification_status' => 'under_review',
                'status' => 'pending'
            ]);

            // Mark onboarding as complete
            $sellerProfile->update([
                'onboarding_completed_at' => now()
            ]);

            // Log the submission
            Log::info('Seller onboarding completed with documents', [
                'user_id' => $user->id,
                'seller_profile_id' => $sellerProfile->id,
                'business_type' => $businessType->slug_en,
                'verification_status' => $sellerProfile->verification_status
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Onboarding completed successfully. Your application is under review.',
                'data' => [
                    'seller_profile' => $sellerProfile->fresh(),
                    'estimated_review_time' => '1-3 business days',
                    'next_steps' => [
                        'verification' => 'Our team will review your documents',
                        'notification' => 'You will receive an email notification',
                        'dashboard' => 'You can access your seller dashboard'
                    ],
                    'verification_status' => $sellerProfile->verification_status,
                    'document_status' => $sellerProfile->document_status
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to complete onboarding with documents: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to complete onboarding: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validate documents before submission (helper method)
     */
    public function validateDocuments(Request $request)
    {
        try {
            $user = $request->user();

            if ($user->type !== 'seller') {
                return response()->json([
                    'success' => false,
                    'message' => 'User is not a seller'
                ], 403);
            }

            $sellerProfile = SellerProfile::where('user_id', $user->id)
                ->with('businessType')
                ->first();

            if (!$sellerProfile) {
                return response()->json([
                    'success' => false,
                    'message' => 'Seller profile not found'
                ], 404);
            }

            $businessType = $sellerProfile->businessType;
            if (!$businessType) {
                return response()->json([
                    'success' => false,
                    'message' => 'Business type not found'
                ], 422);
            }

            // Check missing documents
            $missingDocs = $this->getMissingRequiredDocuments($sellerProfile, $businessType);

            // Validate file existence
            $invalidDocs = $this->validateDocumentFilesExist($sellerProfile, $businessType);

            $isValid = empty($missingDocs) && empty($invalidDocs);

            return response()->json([
                'success' => true,
                'data' => [
                    'is_valid' => $isValid,
                    'missing_documents' => $missingDocs,
                    'invalid_documents' => $invalidDocs,
                    'requirements' => $this->generateDocumentRequirements($businessType),
                    'summary' => $this->getRequirementsSummary($businessType)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Document validation failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to validate documents'
            ], 500);
        }
    }

    /**
     * Get current onboarding data for the authenticated seller
     */
    public function getOnboardingData(Request $request)
    {
        try {
            $user = $request->user();

            // Check if user is seller
            if ($user->type !== 'seller') {
                return response()->json([
                    'success' => false,
                    'message' => 'User is not a seller'
                ], 403);
            }

            $sellerProfile = SellerProfile::where('user_id', $user->id)
                ->with(['businessType'])
                ->first();

            if (!$sellerProfile) {
                return response()->json([
                    'success' => false,
                    'message' => 'Seller profile not found'
                ], 404);
            }

            // Get business type info
            $businessTypeInfo = null;
            if ($sellerProfile->businessType) {
                $businessTypeInfo = [
                    'id' => $sellerProfile->businessType->id,
                    'name_en' => $sellerProfile->businessType->name_en,
                    'slug_en' => $sellerProfile->businessType->slug_en,
                    'description' => $sellerProfile->businessType->description_en,
                    'is_individual' => $sellerProfile->businessType->isIndividualType(),
                    'requires_registration' => $sellerProfile->businessType->requires_registration,
                    'requires_tax_document' => $sellerProfile->businessType->requires_tax_document,
                    'document_requirements' => $sellerProfile->businessType->getDocumentRequirements()
                ];
            }

            // Get uploaded documents
            $uploadedDocuments = [];
            $documentFields = [
                'business_registration_document',
                'tax_registration_document',
                'identity_document_front',
                'identity_document_back'
            ];

            foreach ($documentFields as $field) {
                if (!empty($sellerProfile->$field)) {
                    $uploadedDocuments[$field] = [
                        'uploaded' => true,
                        'url' => $sellerProfile->getDocumentUrl($field),
                        'name' => basename($sellerProfile->$field)
                    ];
                } else {
                    $uploadedDocuments[$field] = [
                        'uploaded' => false,
                        'url' => null,
                        'name' => null
                    ];
                }
            }

            // Get additional documents
            $additionalDocuments = $sellerProfile->getAdditionalDocuments() ?? [];

            // Calculate onboarding progress
            $progress = $this->calculateOnboardingProgress($sellerProfile);

            // Get current step
            $currentStep = $this->getCurrentStep($sellerProfile);

            // Prepare response data
            $data = [
                'store_basic' => [
                    'store_name' => $sellerProfile->store_name,
                    'store_slug' => $sellerProfile->store_slug,
                    'business_type_slug' => $sellerProfile->business_type,
                    'business_type_id' => $sellerProfile->business_type_id,
                    'contact_email' => $sellerProfile->contact_email,
                    'contact_phone' => $sellerProfile->contact_phone,
                    'store_description' => $sellerProfile->store_description,
                    'store_logo' => $sellerProfile->store_logo ?
                        url('storage/' . ltrim($sellerProfile->store_logo, '/')) : null,
                    'store_banner' => $sellerProfile->store_banner ?
                        url('storage/' . ltrim($sellerProfile->store_banner, '/')) : null,
                ],
                'business_details' => [
                    'business_registration_number' => $sellerProfile->business_registration_number,
                    'tax_id' => $sellerProfile->tax_id,
                    'website' => $sellerProfile->website,
                    'account_number' => $sellerProfile->account_number,
                    'social_facebook' => $sellerProfile->social_facebook,
                    'social_instagram' => $sellerProfile->social_instagram,
                    'social_twitter' => $sellerProfile->social_twitter,
                    'social_linkedin' => $sellerProfile->social_linkedin,
                ],
                'address' => [
                    'address' => $sellerProfile->address,
                    'city' => $sellerProfile->city,
                    'state' => $sellerProfile->state,
                    'country' => $sellerProfile->country,
                    'postal_code' => $sellerProfile->postal_code,
                    'location' => $sellerProfile->location,
                ],
                'documents' => [
                    'uploaded_documents' => $uploadedDocuments,
                    'additional_documents' => $additionalDocuments,
                    'documents_submitted' => $sellerProfile->documents_submitted,
                    'document_status' => $sellerProfile->document_status,
                    'documents_submitted_at' => $sellerProfile->documents_submitted_at,
                ],
                'onboarding_status' => [
                    'current_step' => $currentStep,
                    'progress_percentage' => $progress,
                    'profile_status' => $sellerProfile->status,
                    'verification_status' => $sellerProfile->verification_status,
                    'onboarding_completed_at' => $sellerProfile->onboarding_completed_at,
                    'verified_at' => $sellerProfile->verified_at,
                ],
                'business_type_info' => $businessTypeInfo,
                'store_info' => [
                    'store_id' => $sellerProfile->store_id,
                    'created_at' => $sellerProfile->created_at,
                    'updated_at' => $sellerProfile->updated_at,
                ]
            ];

            Log::info('Onboarding data retrieved', [
                'user_id' => $user->id,
                'seller_profile_id' => $sellerProfile->id,
                'current_step' => $currentStep,
                'progress' => $progress
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Onboarding data retrieved successfully',
                'data' => $data
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get onboarding data: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get onboarding data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate onboarding progress percentage
     */
    private function calculateOnboardingProgress($sellerProfile)
    {
        $steps = [
            'store_basic' => [
                'fields' => ['store_name', 'business_type_id', 'contact_email', 'contact_phone'],
                'weight' => 20
            ],
            'business_details' => [
                'fields' => [],
                'weight' => 20,
                'conditional' => function ($profile) {
                    if ($profile->businessType && $profile->businessType->isIndividualType()) {
                        return ['contact_email', 'contact_phone'];
                    }
                    return ['business_registration_number', 'tax_id', 'contact_email', 'contact_phone'];
                }
            ],
            'address' => [
                'fields' => ['address', 'city', 'state', 'country'],
                'weight' => 20
            ],
            'documents' => [
                'fields' => [],
                'weight' => 20,
                'conditional' => function ($profile) {
                    $requiredDocs = [];
                    if ($profile->businessType) {
                        $requirements = $profile->businessType->getDocumentRequirements();
                        $requiredDocs = collect($requirements)
                            ->where('required', true)
                            ->pluck('type')
                            ->toArray();
                    }
                    return $requiredDocs;
                }
            ],
            'review_submit' => [
                'fields' => ['onboarding_completed_at'],
                'weight' => 20
            ]
        ];

        $totalWeight = 100;
        $completedWeight = 0;

        foreach ($steps as $step => $config) {
            $fields = $config['fields'];

            // Handle conditional fields
            if (isset($config['conditional']) && is_callable($config['conditional'])) {
                $conditionalFields = $config['conditional']($sellerProfile);
                $fields = array_merge($fields, $conditionalFields);
            }

            if (empty($fields)) {
                $completedWeight += $config['weight'];
                continue;
            }

            $stepCompleted = true;
            foreach ($fields as $field) {
                if ($field === 'onboarding_completed_at') {
                    if (!$sellerProfile->$field) {
                        $stepCompleted = false;
                        break;
                    }
                } elseif ($field === 'documents_submitted') {
                    if (!$sellerProfile->documents_submitted) {
                        $stepCompleted = false;
                        break;
                    }
                } elseif (str_contains($field, '_document')) {
                    // Check if document is uploaded
                    if (empty($sellerProfile->$field)) {
                        $stepCompleted = false;
                        break;
                    }
                } elseif (empty($sellerProfile->$field)) {
                    $stepCompleted = false;
                    break;
                }
            }

            if ($stepCompleted) {
                $completedWeight += $config['weight'];
            }
        }

        return min(100, $completedWeight);
    }

    /**
     * Get current onboarding step
     */
    private function getCurrentStep($sellerProfile)
    {
        $steps = [
            'store-basic',
            'business-details',
            'address',
            'documents',
            'review'
        ];

        // Check if onboarding is already complete
        if ($sellerProfile->onboarding_completed_at) {
            return 'complete';
        }

        // Check documents step
        if ($sellerProfile->documents_submitted) {
            return 'review';
        }

        // Check if all required documents are uploaded (but not submitted)
        $hasAllDocuments = true;
        if ($sellerProfile->businessType) {
            $requirements = $sellerProfile->businessType->getDocumentRequirements();
            $requiredDocs = collect($requirements)
                ->where('required', true)
                ->pluck('type')
                ->toArray();

            foreach ($requiredDocs as $docType) {
                if (empty($sellerProfile->$docType)) {
                    $hasAllDocuments = false;
                    break;
                }
            }
        }

        if (
            $hasAllDocuments &&
            !empty($sellerProfile->address) &&
            !empty($sellerProfile->city) &&
            !empty($sellerProfile->state) &&
            !empty($sellerProfile->country)
        ) {
            return 'documents';
        }

        // Check address step
        if (
            !empty($sellerProfile->address) &&
            !empty($sellerProfile->city) &&
            !empty($sellerProfile->state) &&
            !empty($sellerProfile->country)
        ) {
            return 'address';
        }

        // Check business details step
        $businessDetailsComplete = true;
        if ($sellerProfile->businessType) {
            if (
                $sellerProfile->businessType->requires_registration &&
                empty($sellerProfile->business_registration_number)
            ) {
                $businessDetailsComplete = false;
            }
            if (
                $sellerProfile->businessType->requires_tax_document &&
                empty($sellerProfile->tax_id)
            ) {
                $businessDetailsComplete = false;
            }
        }

        if (
            $businessDetailsComplete &&
            !empty($sellerProfile->store_name) &&
            !empty($sellerProfile->business_type_id) &&
            !empty($sellerProfile->contact_email) &&
            !empty($sellerProfile->contact_phone)
        ) {
            return 'business-details';
        }

        // Default to store-basic
        return 'store-basic';
    }

    public function submitOnboarding(Request $request)
    {
        try {
            $user = $request->user();
            $sellerProfile = SellerProfile::where('user_id', $user->id)
                ->with('businessType')
                ->first();

            if (!$sellerProfile) {
                return response()->json([
                    'success' => false,
                    'message' => 'Seller profile not found'
                ], 404);
            }

            // Validate all steps are complete
            $errors = [];

            // 1. Store basic validation
            if (empty($sellerProfile->store_name) || empty($sellerProfile->business_type_id)) {
                $errors[] = 'Store basic information incomplete';
            }

            // 2. Business details validation
            $businessType = $sellerProfile->businessType;
            if ($businessType && !$businessType->isIndividualType()) {
                if ($businessType->requires_registration && empty($sellerProfile->business_registration_number)) {
                    $errors[] = 'Business registration number required';
                }
                if ($businessType->requires_tax_document && empty($sellerProfile->tax_id)) {
                    $errors[] = 'Tax ID required';
                }
            }

            // 3. Address validation
            if (
                empty($sellerProfile->address) || empty($sellerProfile->city) ||
                empty($sellerProfile->state) || empty($sellerProfile->country)
            ) {
                $errors[] = 'Address information incomplete';
            }

            // 4. Documents validation
            if (!$sellerProfile->documents_submitted) {
                $errors[] = 'Documents not submitted';
            }

            if (!empty($errors)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Onboarding incomplete',
                    'errors' => $errors,
                    'missing_fields' => $errors
                ], 422);
            }

            // Update profile status
            $sellerProfile->update([
                'onboarding_completed_at' => now(),
                'status' => SellerProfile::STATUS_PENDING,
                'verification_status' => 'pending',  // Use string literal
                'document_status' => 'pending'       // Use string literal
            ]);

            // Log the submission
            Log::info('Seller onboarding submitted', [
                'user_id' => $user->id,
                'seller_profile_id' => $sellerProfile->id,
                'store_name' => $sellerProfile->store_name
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Onboarding submitted successfully. Your store is now under review.',
                'data' => $sellerProfile,
                'next_steps' => [
                    'review_time' => '1-3 business days',
                    'notification' => 'You will receive an email when your store is approved',
                    'dashboard_access' => 'You can access your seller dashboard'
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to submit onboarding: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit onboarding: ' . $e->getMessage()
            ], 500);
        }
    }


    public function saveStep(Request $request, $step)
    {
        try {
            $user = $request->user();
            $sellerProfile = SellerProfile::where('user_id', $user->id)->firstOrFail();

            $validators = [
                'store-basic' => [
                    'store_name' => 'required|string|max:255',
                    'business_type_slug' => 'required|exists:business_types,slug_en',
                    'contact_email' => 'required|email',
                    'contact_phone' => 'required|string'
                ],
                'business-details' => [
                    'business_registration_number' => 'nullable|string|max:255',
                    'tax_id' => 'nullable|string|max:255',
                    'website' => 'nullable|url'
                ],
                'address' => [
                    'address' => 'required|string|max:500',
                    'city' => 'required|string|max:100',
                    'state' => 'required|string|max:100',
                    'country' => 'required|string|max:100'
                ]
            ];

            if (!isset($validators[$step])) {
                return response()->json(['success' => false, 'message' => 'Invalid step'], 400);
            }

            $validator = Validator::make($request->all(), $validators[$step]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
            }

            // Save step data
            $sellerProfile->update($validator->validated());

            // Update progress tracking
            $this->updateOnboardingProgress($sellerProfile, $step);

            return response()->json([
                'success' => true,
                'message' => ucfirst(str_replace('-', ' ', $step)) . ' saved successfully',
                'next_step' => $this->getNextStep($step),
                'progress' => $this->calculateProgress($sellerProfile)
            ]);

        } catch (\Exception $e) {
            Log::error('Save step failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to save step'], 500);
        }
    }

    private function updateOnboardingProgress($sellerProfile, $step)
    {
        $steps = ['store-basic', 'business-details', 'address', 'documents', 'review'];
        $currentStepIndex = array_search($step, $steps);

        $progress = [
            'current_step' => $step,
            'completed_steps' => $steps,
            'progress_percentage' => (($currentStepIndex + 1) / count($steps)) * 100
        ];

        $sellerProfile->update(['onboarding_progress' => json_encode($progress)]);
    }

    /**
     * Get verification review for admin
     */
    public function getVerificationReview(Request $request)
    {
        try {
            $user = $request->user();

            if ($user->type !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only admin can access this endpoint'
                ], 403);
            }

            $perPage = $request->input('per_page', 15);
            $search = $request->input('search');
            $status = $request->input('status');

            // Get sellers who have uploaded documents
            $query = SellerProfile::where(function ($q) {
                // Include sellers with any document uploaded
                $q->whereNotNull('identity_document_front')
                    ->orWhereNotNull('business_registration_document')
                    ->orWhereNotNull('tax_registration_document')
                    ->orWhereNotNull('identity_document_back')
                    ->orWhereNotNull('business_certificate')
                    ->orWhereNotNull('additional_documents');
            });

            // Apply filters
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('store_name', 'like', "%{$search}%")
                        ->orWhere('store_id', 'like', "%{$search}%")
                        ->orWhere('contact_email', 'like', "%{$search}%")
                        ->orWhere('contact_phone', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($userQuery) use ($search) {
                            $userQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            }

            if ($status) {
                $query->where('document_status', $status);
            }

            $sellers = $query->with(['user:id,name,email'])
                ->orderBy('documents_submitted_at', 'desc')
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            // Add document URLs
            $sellers->getCollection()->transform(function ($seller) {
                $sellerData = $seller->toArray();

                // Get all document URLs
                $documents = [];
                $documentFields = [
                    'identity_document_front',
                    'identity_document_back',
                    'business_registration_document',
                    'tax_registration_document',
                    'business_certificate',
                    'store_logo',
                    'store_banner'
                ];

                foreach ($documentFields as $field) {
                    if (!empty($seller->$field)) {
                        $documents[$field] = [
                            'url' => $seller->getDocumentUrl($field),
                            'label' => ucwords(str_replace('_', ' ', $field))
                        ];
                    }
                }

                $sellerData['documents'] = $documents;
                $sellerData['has_documents'] = !empty($documents);
                $sellerData['additional_documents'] = $seller->getAdditionalDocuments();

                return $sellerData;
            });

            return response()->json([
                'success' => true,
                'data' => $sellers,
                'meta' => [
                    'current_page' => $sellers->currentPage(),
                    'per_page' => $sellers->perPage(),
                    'total' => $sellers->total(),
                    'last_page' => $sellers->lastPage(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get verification review: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get verification review'
            ], 500);
        }
    }

    /**
     * Get verification status
     */
    public function getVerificationStatus(Request $request)
    {
        try {
            $user = $request->user();

            if ($user->type !== 'seller') {
                return response()->json([
                    'success' => false,
                    'message' => 'User is not a seller'
                ], 403);
            }

            $sellerProfile = SellerProfile::where('user_id', $user->id)->first();

            if (!$sellerProfile) {
                return response()->json([
                    'success' => false,
                    'message' => 'Seller profile not found'
                ], 404);
            }

            $verificationHistory = DB::table('verification_logs')
                ->where('seller_profile_id', $sellerProfile->id)
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'verification_status' => $sellerProfile->verification_status,
                    'verification_level' => $sellerProfile->verification_level,
                    'document_status' => $sellerProfile->document_status,
                    'status' => $sellerProfile->status,
                    'verified_at' => $sellerProfile->verified_at,
                    'verified_by' => $sellerProfile->verified_by,
                    'verification_notes' => $sellerProfile->verification_notes,
                    'document_rejection_reason' => $sellerProfile->document_rejection_reason,
                    'documents_submitted_at' => $sellerProfile->documents_submitted_at,
                    'onboarding_completed_at' => $sellerProfile->onboarding_completed_at,
                    'history' => $verificationHistory,
                    'has_verification_badge' => $sellerProfile->hasVerificationBadge(),
                    'badge_type' => $sellerProfile->badge_type,
                    'badge_expires_at' => $sellerProfile->badge_expires_at
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get verification status: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get verification status'
            ], 500);
        }
    }


    /**
     * Get verification history
     */
    public function getVerificationHistory(Request $request)
    {
        try {
            $user = $request->user();

            if ($user->type !== 'seller') {
                return response()->json([
                    'success' => false,
                    'message' => 'User is not a seller'
                ], 403);
            }

            $sellerProfile = SellerProfile::where('user_id', $user->id)->first();

            if (!$sellerProfile) {
                return response()->json([
                    'success' => false,
                    'message' => 'Seller profile not found'
                ], 404);
            }

            $history = DB::table('verification_logs')
                ->where('seller_profile_id', $sellerProfile->id)
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($log) {
                    return [
                        'action' => $log->action,
                        'notes' => $log->notes,
                        'performed_by' => $log->performed_by,
                        'created_at' => $log->created_at,
                        'previous_status' => $log->previous_status,
                        'new_status' => $log->new_status
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $history
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get verification history: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get verification history'
            ], 500);
        }
    }

    /**
     * Get sellers pending verification (Admin)
     */
    public function getPendingVerification(Request $request)
    {
        try {
            $user = $request->user();

            if ($user->type !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only admin can access this endpoint'
                ], 403);
            }

            $perPage = $request->input('per_page', 15);

            $sellers = SellerProfile::pendingVerification()
                ->with(['user:id,name,email,phone'])
                ->orderBy('onboarding_completed_at', 'desc')
                ->paginate($perPage);

            // Add document URLs
            $sellers->getCollection()->transform(function ($seller) {
                $sellerData = $seller->toArray();

                // Get document URLs
                $documents = [];
                $documentFields = [
                    'business_registration_document',
                    'tax_registration_document',
                    'identity_document_front',
                    'identity_document_back'
                ];

                foreach ($documentFields as $field) {
                    if (!empty($seller->$field)) {
                        $documents[$field] = $seller->getDocumentUrl($field);
                    }
                }

                $sellerData['documents'] = $documents;
                $sellerData['additional_documents'] = $seller->getAdditionalDocuments();

                return $sellerData;
            });

            return response()->json([
                'success' => true,
                'data' => $sellers,
                'meta' => [
                    'current_page' => $sellers->currentPage(),
                    'per_page' => $sellers->perPage(),
                    'total' => $sellers->total(),
                    'last_page' => $sellers->lastPage(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get pending verification: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get pending verification'
            ], 500);
        }
    }

    /**
     * Get seller status (Admin)
     */
    public function getSellersWithDocuments(Request $request)
    {
        try {
            $user = $request->user();

            if ($user->type !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only admin can access this endpoint'
                ], 403);
            }

            $perPage = $request->input('per_page', 15);

            // Get sellers who have uploaded documents but may not be in verification queue
            $sellers = SellerProfile::where(function ($query) {
                $query->whereNotNull('identity_document_front')
                    ->orWhereNotNull('business_registration_document')
                    ->orWhereNotNull('tax_registration_document');
            })
                ->where(function ($query) {
                    // Include pending verification OR sellers with documents that need review
                    $query->whereIn('verification_status', ['pending', 'under_review'])
                        ->orWhere('document_status', '!=', 'approved');
                })
                ->with(['user:id,name,email,phone'])
                ->orderBy('documents_submitted_at', 'desc')
                ->paginate($perPage);

            // Format response
            $sellers->getCollection()->transform(function ($seller) {
                $sellerData = $seller->toArray();

                // Get document URLs
                $documents = [];
                $documentFields = [
                    'identity_document_front',
                    'identity_document_back',
                    'business_registration_document',
                    'tax_registration_document'
                ];

                foreach ($documentFields as $field) {
                    if (!empty($seller->$field)) {
                        $documents[$field] = $seller->getDocumentUrl($field);
                    }
                }

                $sellerData['documents'] = $documents;
                $sellerData['additional_documents'] = $seller->getAdditionalDocuments();
                $sellerData['has_documents'] = !empty($documents);

                return $sellerData;
            });

            return response()->json([
                'success' => true,
                'data' => $sellers,
                'meta' => [
                    'current_page' => $sellers->currentPage(),
                    'per_page' => $sellers->perPage(),
                    'total' => $sellers->total(),
                    'last_page' => $sellers->lastPage(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get sellers with documents: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get sellers with documents'
            ], 500);
        }
    }

    /**
     * Get seller status (Admin)
     */
    public function getSellerStatus(Request $request, $id)
    {
        try {
            $admin = $request->user();

            if ($admin->type !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only admin can access seller status'
                ], 403);
            }

            $sellerProfile = SellerProfile::findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => [
                    'verification_status' => $sellerProfile->verification_status,
                    'document_status' => $sellerProfile->document_status,
                    'status' => $sellerProfile->status,
                    'verified_at' => $sellerProfile->verified_at,
                    'verified_by' => $sellerProfile->verified_by,
                    'verification_notes' => $sellerProfile->verification_notes,
                    'document_rejection_reason' => $sellerProfile->document_rejection_reason,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get seller status: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get seller status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verify seller (Admin)
     */
    public function verifySeller(Request $request, $id)
    {
        try {
            $admin = $request->user();

            if ($admin->type !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only admin can verify sellers'
                ], 403);
            }

            $sellerProfile = SellerProfile::findOrFail($id);

            // Check if profile is complete
            if (!$sellerProfile->hasCompleteProfile()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot verify seller with incomplete profile',
                    'missing_fields' => $sellerProfile->getMissingFields()
                ], 422);
            }

            // Check if required documents are uploaded
            if (!$sellerProfile->hasRequiredDocuments()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot verify seller with missing documents',
                    'missing_documents' => $sellerProfile->getMissingDocuments()
                ], 422);
            }

            $validator = Validator::make($request->all(), [
                'verification_level' => 'required|in:basic,verified,premium',
                'badge_type' => 'nullable|in:verified,premium,featured,top_rated',
                'notes' => 'nullable|string|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $validated = $validator->validated();

            // Verify seller
            $sellerProfile->verify(
                $admin->id,
                $validated['verification_level'],
                $validated['badge_type'] ?? 'verified',
                $validated['notes'] ?? null
            );

            // Log verification
            $this->logVerificationAction(
                $sellerProfile->id,
                $admin->id,
                'verified',
                'Seller verified by admin',
                $validated['notes'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => 'Seller verified successfully',
                'data' => $sellerProfile->fresh()
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to verify seller: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to verify seller: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject verification (Admin)
     */
    public function rejectVerification(Request $request, $id)
    {
        try {
            $admin = $request->user();

            if ($admin->type !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only admin can reject verification'
                ], 403);
            }

            $sellerProfile = SellerProfile::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'reason' => 'required|string|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $validated = $validator->validated();

            // Reject verification
            $sellerProfile->rejectVerification($validated['reason']);

            // Log rejection
            $this->logVerificationAction(
                $sellerProfile->id,
                $admin->id,
                'rejected',
                'Seller verification rejected',
                $validated['reason']
            );

            // Send notification to seller (you can implement this)
            // $sellerProfile->user->notify(new SellerRejectedNotification($sellerProfile, $validated['reason']));

            return response()->json([
                'success' => true,
                'message' => 'Seller verification rejected',
                'data' => $sellerProfile->fresh()
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to reject verification: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject verification: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get seller documents (Admin)
     */
    public function getSellerDocuments(Request $request, $id)
    {
        try {
            $admin = $request->user();

            if ($admin->type !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only admin can access seller documents'
                ], 403);
            }

            $sellerProfile = SellerProfile::findOrFail($id);

            $documents = [];

            // Get regular document fields
            $documentFields = [
                'business_registration_document',
                'tax_registration_document',
                'identity_document_front',
                'identity_document_back'
            ];

            foreach ($documentFields as $field) {
                if (!empty($sellerProfile->$field)) {
                    $documents[$field] = [
                        'name' => basename($sellerProfile->$field),
                        'url' => $sellerProfile->getDocumentUrl($field),
                        'type' => $field
                    ];
                }
            }

            // Get additional documents
            $additionalDocuments = $sellerProfile->getAdditionalDocuments();

            return response()->json([
                'success' => true,
                'data' => [
                    'seller' => [
                        'id' => $sellerProfile->id,
                        'store_name' => $sellerProfile->store_name,
                        'business_type' => $sellerProfile->business_type,
                        'user_id' => $sellerProfile->user_id,
                        'status' => $sellerProfile->status,
                        'verification_status' => $sellerProfile->verification_status
                    ],
                    'documents' => $documents,
                    'additional_documents' => $additionalDocuments,
                    'missing_documents' => $sellerProfile->getMissingDocuments()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get seller documents: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get seller documents'
            ], 500);
        }
    }

    /**
     * Get sellers with uploaded documents for verification review
     */
    public function getSellersForVerificationReview(Request $request)
    {
        try {
            $user = $request->user();

            if ($user->type !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only admin can access this endpoint'
                ], 403);
            }

            $perPage = $request->input('per_page', 15);
            $search = $request->input('search');

            // Get sellers who need verification review
            $query = SellerProfile::where(function ($q) {
                // Sellers with uploaded identity documents
                $q->whereNotNull('identity_document_front')
                    ->orWhereNotNull('business_registration_document');
            })
                ->where(function ($q) {
                    // And haven't been fully verified/rejected yet
                    $q->where('document_status', '!=', 'approved')
                        ->orWhereNull('document_status')
                        ->orWhere('verification_status', '!=', 'verified');
                })
                ->where('documents_submitted', true)
                ->with(['user:id,name,email,phone'])
                ->orderBy('documents_submitted_at', 'desc');

            // Apply search filter
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('store_name', 'like', '%' . $search . '%')
                        ->orWhere('store_id', 'like', '%' . $search . '%')
                        ->orWhere('contact_email', 'like', '%' . $search . '%')
                        ->orWhere('contact_phone', 'like', '%' . $search . '%')
                        ->orWhereHas('user', function ($userQuery) use ($search) {
                            $userQuery->where('name', 'like', '%' . $search . '%')
                                ->orWhere('email', 'like', '%' . $search . '%');
                        });
                });
            }

            $sellers = $query->paginate($perPage);

            // Transform data
            $sellers->getCollection()->transform(function ($seller) {
                $sellerData = $seller->toArray();

                // Get document URLs
                $documents = [];
                $documentFields = [
                    'identity_document_front' => 'Identity Front',
                    'identity_document_back' => 'Identity Back',
                    'business_registration_document' => 'Business Registration',
                    'tax_registration_document' => 'Tax Document',
                    'business_certificate' => 'Business Certificate'
                ];

                foreach ($documentFields as $field => $label) {
                    if (!empty($seller->$field)) {
                        $documents[$field] = [
                            'url' => $seller->getDocumentUrl($field),
                            'label' => $label,
                            'exists' => true
                        ];
                    }
                }

                $sellerData['documents'] = $documents;
                $sellerData['additional_documents'] = $seller->getAdditionalDocuments();

                // Calculate document completion
                $docCount = count(array_filter(array_column($documents, 'exists')));
                $sellerData['document_completion'] = $docCount;
                $sellerData['has_identity_docs'] = !empty($seller->identity_document_front);
                $sellerData['has_business_docs'] = !empty($seller->business_registration_document);

                return $sellerData;
            });

            return response()->json([
                'success' => true,
                'data' => $sellers,
                'stats' => [
                    'total' => $sellers->total(),
                    'with_identity_docs' => SellerProfile::whereNotNull('identity_document_front')->count(),
                    'with_business_docs' => SellerProfile::whereNotNull('business_registration_document')->count(),
                    'pending_verification' => SellerProfile::where('verification_status', 'pending')->count(),
                    'pending_documents' => SellerProfile::whereIn('document_status', ['pending', 'under_review'])->count()
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to get sellers for verification review: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get sellers for verification review'
            ], 500);
        }
    }

    /**
     * Update verification status (Admin)
     */
    public function updateVerificationStatus(Request $request, $id)
    {
        try {
            $admin = $request->user();

            if ($admin->type !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only admin can update verification status'
                ], 403);
            }

            $sellerProfile = SellerProfile::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'verification_status' => 'required|in:pending,under_review,verified,rejected',
                'document_status' => 'required|in:not_submitted,pending,under_review,approved,rejected',
                'notes' => 'nullable|string|max:1000',
                'reason' => 'nullable|string|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $validated = $validator->validated();

            $oldStatus = $sellerProfile->verification_status;
            $oldDocStatus = $sellerProfile->document_status;

            $sellerProfile->update([
                'onboarding_completed_at' => now(),
                'status' => SellerProfile::STATUS_PENDING,
                'verification_status' => SellerProfile::VERIFICATION_PENDING,
                'document_status' => $validated['document_status'],
                'verification_notes' => $validated['notes'] ?? null,
                'document_rejection_reason' => $validated['document_status'] === 'rejected' ? ($validated['reason'] ?? null) : null,
            ]);

            // Log status change
            $this->logVerificationAction(
                $sellerProfile->id,
                $admin->id,
                'status_updated',
                'Verification status updated',
                $validated['notes'] ?? null,
                $oldStatus,
                $validated['verification_status']
            );

            return response()->json([
                'success' => true,
                'message' => 'Verification status updated successfully',
                'data' => $sellerProfile->fresh()
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update verification status: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update verification status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update seller status (Admin)
     */
    public function updateStatus(Request $request, $id)
    {
        try {
            $admin = $request->user();

            if ($admin->type !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only admin can update seller status'
                ], 403);
            }

            $sellerProfile = SellerProfile::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'status' => 'required|in:setup_pending,pending,approved,active,rejected,suspended,closed',
                'reason' => 'nullable|string|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $validated = $validator->validated();

            // Store old status for logging
            $oldStatus = $sellerProfile->status;

            // Update status
            $sellerProfile->update([
                'status' => $validated['status'],
                'admin_notes' => $validated['reason'] ?? null,
            ]);

            // Log the status change
            if ($validated['reason']) {
                DB::table('seller_status_logs')->insert([
                    'seller_profile_id' => $sellerProfile->id,
                    'admin_id' => $admin->id,
                    'old_status' => $oldStatus,
                    'new_status' => $validated['status'],
                    'reason' => $validated['reason'],
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            Log::info('Seller status updated', [
                'seller_id' => $id,
                'admin_id' => $admin->id,
                'old_status' => $oldStatus,
                'new_status' => $validated['status'],
                'reason' => $validated['reason'] ?? null
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Seller status updated successfully',
                'data' => $sellerProfile->fresh()
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update seller status: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update seller status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
 * Get comprehensive sales summary with delivery stats (FIXED VERSION)
 */
    public function salesSummary(Request $request)
    {
        try {
            $user = $request->user();

            if (!isset($user->type) || $user->type !== 'seller') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only sellers can access this endpoint'
                ], 403);
            }

            // Get date range (default to last 30 days)
            $startDate = $request->input('start_date', Carbon::now()->subDays(30)->format('Y-m-d'));
            $endDate = $request->input('end_date', Carbon::now()->format('Y-m-d'));

            \Log::info('Sales summary request', [
                'user_id' => $user->id,
                'start_date' => $startDate,
                'end_date' => $endDate
            ]);

            // Total products with stock info
            $totalProducts = Product::where('seller_id', $user->id)->count();
            $activeProducts = Product::where('seller_id', $user->id)
                ->where('is_active', true)
                ->count();
            $lowStockProducts = Product::where('seller_id', $user->id)
                ->where('quantity', '<=', 5)
                ->count();

            // Sales data
            $salesData = DB::table('orders')
                ->where('seller_id', $user->id)
                ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->select(
                    DB::raw('COUNT(*) as total_orders'),
                    DB::raw('COALESCE(SUM(total_amount), 0) as total_revenue'),
                    DB::raw('COALESCE(AVG(total_amount), 0) as average_order_value')
                )
                ->first();

            // Total items sold
            $totalItemsSold = DB::table('order_items')
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->where('orders.seller_id', $user->id)
                ->whereBetween('orders.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->sum('order_items.quantity') ?? 0;

            // Order status counts
            $orderStatusCounts = DB::table('orders')
                ->where('seller_id', $user->id)
                ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->select(
                    'status',
                    DB::raw('COUNT(*) as count')
                )
                ->groupBy('status')
                ->get()
                ->pluck('count', 'status');

            \Log::info('Order status counts', $orderStatusCounts->toArray());

            // Delivery status counts
            try {
            $deliveryStatusCounts = DB::table('deliveries')
                ->where('supplier_id', $user->id)
                    ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->select(
                    'status',
                    DB::raw('COUNT(*) as count')
                )
                ->groupBy('status')
                ->get()
                ->pluck('count', 'status');
            } catch (\Exception $e) {
                \Log::warning('Deliveries table query failed', ['error' => $e->getMessage()]);
                $deliveryStatusCounts = collect([]);
            }

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

            \Log::info('Recent sales trend count', ['count' => $recentSalesTrend->count()]);

            // Customer statistics - FIXED: Use correct approach for repeat customers
            $totalCustomers = DB::table('orders')
                ->where('seller_id', $user->id)
                ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->distinct()
                ->count('buyer_id');

            // Repeat customers - FIXED: Use subquery to avoid GROUP BY issues
            try {
                // Get all buyers with their order counts
                $buyerOrders = DB::table('orders')
                ->where('seller_id', $user->id)
                    ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                    ->select('buyer_id')
                    ->get();

                // Count buyers with more than 1 order
                $buyerOrderCounts = [];
                foreach ($buyerOrders as $order) {
                    $buyerId = $order->buyer_id;
                    if (!isset($buyerOrderCounts[$buyerId])) {
                        $buyerOrderCounts[$buyerId] = 0;
                    }
                    $buyerOrderCounts[$buyerId]++;
                }

                $repeatCustomers = count(array_filter($buyerOrderCounts, function ($count) {
                    return $count > 1;
                }));

            } catch (\Exception $e) {
                \Log::error('Repeat customers calculation error', ['error' => $e->getMessage()]);
                $repeatCustomers = 0;
            }

            \Log::info('Customer stats', [
                'total_customers' => $totalCustomers,
                'repeat_customers' => $repeatCustomers
            ]);

            // Top selling products - FIXED: Use name_en instead of name
            try {
                // First check what columns exist in order_items
                $orderItemColumns = \Schema::getColumnListing('order_items');
                \Log::info('order_items columns', $orderItemColumns);

                // Check if subtotal column exists in order_items
                $hasSubtotal = in_array('subtotal', $orderItemColumns);

                $topProducts = DB::table('order_items')
                    ->join('orders', 'order_items.order_id', '=', 'orders.id')
                    ->join('products', 'order_items.product_id', '=', 'products.id')
                ->where('orders.seller_id', $user->id)
                    ->whereBetween('orders.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->select(
                    'products.id',
                        'products.name_en as name', // FIXED: Use name_en
                    'products.images',
                        DB::raw('COALESCE(SUM(order_items.quantity), 0) as total_sold'),
                        // Check if subtotal column exists, otherwise calculate it
                        $hasSubtotal
                        ? DB::raw('COALESCE(SUM(order_items.subtotal), 0) as total_revenue')
                        : DB::raw('COALESCE(SUM(order_items.price * order_items.quantity), 0) as total_revenue')
                )
                    ->groupBy('products.id', 'products.name_en', 'products.images')
                ->orderBy('total_sold', 'desc')
                ->limit(5)
                ->get();

            } catch (\Exception $e) {
                \Log::error('Top products query error', ['error' => $e->getMessage()]);
                $topProducts = collect([]);
            }

            // Format top products with images
            $formattedTopProducts = $topProducts->map(function ($product) {
                $images = json_decode($product->images, true) ?? [];
                $primaryImage = collect($images)->firstWhere('is_primary', true) ?? $images[0] ?? null;

                $imageUrl = null;
                if ($primaryImage && isset($primaryImage['url'])) {
                    $imageUrl = $primaryImage['url'];
                    // Convert to full URL if it's a storage path
                    if (!str_starts_with($imageUrl, 'http')) {
                        $imageUrl = url('storage/' . ltrim($imageUrl, '/'));
                    }
                }

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'image' => $imageUrl,
                    'total_sold' => (int) $product->total_sold,
                    'total_revenue' => (float) $product->total_revenue,
                ];
            });

            $summary = [
                'period' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'days' => Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate)) + 1
                ],
                'products' => [
                    'total' => $totalProducts,
                    'active' => $activeProducts,
                    'inactive' => $totalProducts - $activeProducts,
                    'low_stock' => $lowStockProducts
                ],
                'sales' => [
                    'total_orders' => $salesData->total_orders ?? 0,
                    'total_items_sold' => $totalItemsSold,
                    'total_revenue' => $salesData->total_revenue ?? 0,
                    'average_order_value' => $salesData->average_order_value ?? 0,
                    'revenue_formatted' => 'MMK ' . number_format($salesData->total_revenue ?? 0, 2)
                ],
                'customers' => [
                    'total' => $totalCustomers,
                    'repeat_customers' => $repeatCustomers,
                    'repeat_rate' => $totalCustomers > 0 ? round(($repeatCustomers / $totalCustomers) * 100, 2) : 0
                ],
                'orders_by_status' => $orderStatusCounts,
                'delivery_stats' => [
                    'total' => array_sum($deliveryStatusCounts->toArray()),
                    'by_status' => $deliveryStatusCounts
                ],
                'recent_trend' => $recentSalesTrend,
                'top_products' => $formattedTopProducts
            ];

            \Log::info('Sales summary generated successfully', [
                'user_id' => $user->id,
                'summary' => $summary
            ]);

            return response()->json([
                'success' => true,
                'data' => $summary
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in seller salesSummary: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch sales summary',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null,
                'trace' => env('APP_DEBUG') ? $e->getTraceAsString() : null
            ], 500);
        }
    }

    /**
     * Get delivery statistics (FIXED VERSION)
     */
    public function deliveryStats(Request $request)
    {
        try {
            $user = $request->user();

            if (!isset($user->type) || $user->type !== 'seller') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only sellers can access this endpoint'
                ], 403);
            }

            $startDate = $request->input('start_date', Carbon::now()->subDays(30)->format('Y-m-d'));
            $endDate = $request->input('end_date', Carbon::now()->format('Y-m-d'));

            // Delivery status counts - FIXED: Proper grouping
            $deliveryStats = DB::table('deliveries')
                ->where('supplier_id', $user->id)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->select(
                    'status',
                    DB::raw('COUNT(*) as count')
                )
                ->groupBy('status')
                ->get();

            // Calculate average delivery time separately to avoid complex grouping
            $avgDeliveryTime = DB::table('deliveries')
                ->where('supplier_id', $user->id)
                ->where('status', 'delivered')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->whereNotNull('delivered_at')
                ->select(
                    DB::raw('AVG(TIMESTAMPDIFF(HOUR, created_at, delivered_at)) as avg_hours')
                )
                ->first();

            // Delivery method distribution - FIXED: Proper grouping
            $deliveryMethodStats = DB::table('deliveries')
                ->where('supplier_id', $user->id)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->select(
                    'delivery_method',
                    DB::raw('COUNT(*) as count')
                )
                ->groupBy('delivery_method')
                ->get();

            $stats = [
                'total_deliveries' => $deliveryStats->sum('count'),
                'by_status' => $deliveryStats->pluck('count', 'status'),
                'by_method' => $deliveryMethodStats->pluck('count', 'delivery_method'),
                'average_delivery_time_hours' => $avgDeliveryTime->avg_hours ?? 0
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error('Error in seller deliveryStats: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch delivery stats: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get recent orders
     */
    public function recentOrders(Request $request)
    {
        try {
            $user = $request->user();

            if (!isset($user->type) || $user->type !== 'seller') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only sellers can access this endpoint'
                ], 403);
            }

            $limit = $request->input('limit', 10);

            // Get recent orders with order items and buyer info - FIXED: Use proper Eloquent
            $recentOrders = Order::where('seller_id', $user->id)
                ->with(['buyer:id,name,email', 'items.product:id,name'])
                ->select([
                    'id',
                    'order_number',
                    'status',
                    'total_amount',
                    'created_at',
                    'buyer_id',
                    'payment_status',
                    'payment_method'
                ])
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get()
                ->map(function ($order) {
                    return [
                        'id' => $order->id,
                        'order_number' => $order->order_number,
                        'status' => $order->status,
                        'total_amount' => $order->total_amount,
                        'total_amount_formatted' => 'MMK ' . number_format($order->total_amount, 2),
                        'payment_status' => $order->payment_status,
                        'payment_method' => $order->payment_method,
                        'created_at' => $order->created_at->format('M j, Y g:i A'),
                        'buyer' => $order->buyer ? [
                            'name' => $order->buyer->name,
                            'email' => $order->buyer->email
                        ] : null,
                        'items_count' => $order->items->count(),
                        'products' => $order->items->take(2)->map(function ($item) {
                            return [
                                'name' => $item->product->name ?? $item->product_name,
                                'quantity' => $item->quantity,
                                'price' => $item->price
                            ];
                        })
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $recentOrders
            ]);

        } catch (\Exception $e) {
            Log::error('Error in seller recentOrders: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch recent orders: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get top products (role-based)
     */
    public function topProducts(Request $request)
    {
        try {
            $user = $request->user();

            if (!isset($user->type) || $user->type !== 'seller') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only sellers can access this endpoint'
                ], 403);
            }

            $limit = $request->input('limit', 5);
            $days = $request->input('days', 30);

            $topProducts = DB::table('order_items')
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->join('products', 'order_items.product_id', '=', 'products.id')
                ->where('orders.seller_id', $user->id)
                ->where('orders.created_at', '>=', Carbon::now()->subDays($days))
                ->select(
                    'products.id',
                    'products.name_en as name', // FIXED: Changed from 'products.name' to 'products.name_en'
                    'products.images',
                    DB::raw('COALESCE(SUM(order_items.quantity), 0) as total_sold'),
                    DB::raw('COALESCE(SUM(order_items.price * order_items.quantity), 0) as total_revenue')
                )
                ->groupBy('products.id', 'products.name_en', 'products.images') // FIXED: Group by name_en
                ->orderBy('total_sold', 'desc')
                ->limit($limit)
                ->get()
                ->map(function ($product) {
                    $images = json_decode($product->images, true) ?? [];
                    $primaryImage = collect($images)->firstWhere('is_primary', true) ?? $images[0] ?? null;

                    return [
                        'id' => $product->id,
                        'name' => $product->name,
                        'image' => $primaryImage ? (str_starts_with($primaryImage['url'] ?? '', 'http') ? $primaryImage['url'] : url('storage/' . ltrim($primaryImage['url'], '/'))) : null,
                        'total_sold' => (int) $product->total_sold,
                        'total_revenue' => (float) $product->total_revenue,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $topProducts
            ]);

        } catch (\Exception $e) {
            Log::error('Error in seller topProducts: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch top products: ' . $e->getMessage()
            ], 500);
        }
    }


    /**
     * Get performance metrics (role-based)
     */
    public function performanceMetrics(Request $request)
    {
        try {
            $user = $request->user();

            if (!isset($user->type) || $user->type !== 'seller') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only sellers can access this endpoint'
                ], 403);
            }

            $days = $request->input('days', 30);

            // Overall metrics
            $totalProducts = Product::where('seller_id', $user->id)->count();
            $totalOrders = Order::where('seller_id', $user->id)->count();
            $totalRevenue = Order::where('seller_id', $user->id)
                ->where('status', 'delivered')
                ->sum('total_amount');

            // Recent performance (last 30 days)
            $recentOrders = Order::where('seller_id', $user->id)
                ->where('created_at', '>=', Carbon::now()->subDays($days))
                ->count();

            $recentRevenue = Order::where('seller_id', $user->id)
                ->where('status', 'delivered')
                ->where('created_at', '>=', Carbon::now()->subDays($days))
                ->sum('total_amount');

            // Rating and reviews
            $averageRating = DB::table('reviews')
                ->join('products', 'reviews.product_id', '=', 'products.id')
                ->where('products.seller_id', $user->id)
                ->avg('reviews.rating');

            $totalReviews = DB::table('reviews')
                ->join('products', 'reviews.product_id', '=', 'products.id')
                ->where('products.seller_id', $user->id)
                ->count();

            $metrics = [
                'overall' => [
                    'total_products' => $totalProducts,
                    'total_orders' => $totalOrders,
                    'total_revenue' => $totalRevenue,
                    'total_revenue_formatted' => 'MMK ' . number_format($totalRevenue, 2)
                ],
                'recent' => [
                    'period_days' => $days,
                    'orders_count' => $recentOrders,
                    'revenue' => $recentRevenue,
                    'revenue_formatted' => 'MMK ' . number_format($recentRevenue, 2)
                ],
                'ratings' => [
                    'average_rating' => $averageRating ? round($averageRating, 2) : 0,
                    'total_reviews' => $totalReviews
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $metrics
            ]);

        } catch (\Exception $e) {
            Log::error('Error in seller performanceMetrics: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch performance metrics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Log verification action for audit trail
     */
    private function logVerificationAction(
        $sellerProfileId,
        $adminId,
        $action,
        $notes = null,
        $reason = null,
        $previousStatus = null,
        $newStatus = null
    ) {
        try {
            DB::table('verification_logs')->insert([
                'seller_profile_id' => $sellerProfileId,
                'performed_by' => $adminId,
                'action' => $action,
                'notes' => $notes,
                'reason' => $reason,
                'previous_status' => $previousStatus,
                'new_status' => $newStatus,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            Log::info('Verification action logged', [
                'seller_profile_id' => $sellerProfileId,
                'admin_id' => $adminId,
                'action' => $action,
                'notes' => $notes
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to log verification action: ' . $e->getMessage());
        }
    }

    /**
     * Helper method to calculate product performance
     */
    private function calculatePerformance($totalSold)
    {
        if ($totalSold >= 100)
            return 'excellent';
        if ($totalSold >= 50)
            return 'good';
        if ($totalSold >= 20)
            return 'average';
        return 'low';
    }

    /**
     * Helper method to get orders by status
     */
    protected function getOrdersByStatus($orders)
    {
        return $orders->groupBy('status')
            ->map(function ($statusOrders, $status) use ($orders) {
                return [
                    'count' => $statusOrders->count(),
                    'revenue' => $statusOrders->sum('total_amount'),
                    'percentage' => $orders->count() > 0 ?
                        round(($statusOrders->count() / $orders->count()) * 100, 2) : 0
                ];
            });
    }

    /**
     * Get sales by day with role-based filtering
     */
    protected function getSalesByDay($start, $end, $user)
    {
        $salesQuery = Order::whereBetween('created_at', [$start, $end])
            ->where('status', 'delivered');

        // Role-based filtering
        if (isset($user->type) && $user->type === 'seller') {
            $salesQuery->where('seller_id', $user->id);
        } elseif (isset($user->type) && $user->type === 'buyer') {
            $salesQuery->where('buyer_id', $user->id);
        }

        $sales = $salesQuery->selectRaw('DATE(created_at) as date, count(*) as count, sum(total_amount) as revenue')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Fill missing days with zero values
        $results = [];
        $current = $start->copy();
        while ($current <= $end) {
            $date = $current->format('Y-m-d');
            $sale = $sales->firstWhere('date', $date);

            $results[] = [
                'date' => $date,
                'count' => $sale ? $sale->count : 0,
                'revenue' => $sale ? floatval($sale->revenue) : 0
            ];

            $current->addDay();
        }

        return $results;
    }

    /**
     * Get seller setup requirements
     */
    public function getSetupRequirements(Request $request)
    {
        try {
            $user = $request->user();
            $sellerProfile = SellerProfile::where('user_id', $user->id)->first();

            if (!$sellerProfile) {
                return response()->json([
                    'success' => true,
                    'needs_setup' => true,
                    'requirements' => [
                        'create_profile' => true
                    ]
                ]);
            }

            $requirements = [
                'store_basic' => !empty($sellerProfile->store_name) &&
                    !empty($sellerProfile->business_type_id) &&
                    !empty($sellerProfile->contact_email) &&
                    !empty($sellerProfile->contact_phone),
                'store_logo' => !empty($sellerProfile->store_logo),
                'store_banner' => !empty($sellerProfile->store_banner),
                'business_details' => $sellerProfile->business_type === 'individual' ?
                    true : (!empty($sellerProfile->business_registration_number) &&
                        !empty($sellerProfile->tax_id)),
                'address' => !empty($sellerProfile->address) &&
                    !empty($sellerProfile->city) &&
                    !empty($sellerProfile->state) &&
                    !empty($sellerProfile->country),
                'documents' => $sellerProfile->documents_submitted,
                'verification' => $sellerProfile->verification_status === 'verified',
            ];

            $completed = array_filter($requirements, function ($item) {
                return $item === true;
            });

            $total = count($requirements);
            $completedCount = count($completed);
            $progress = $total > 0 ? ($completedCount / $total) * 100 : 0;

            return response()->json([
                'success' => true,
                'needs_setup' => $completedCount < $total,
                'progress' => $progress,
                'requirements' => $requirements,
                'next_step' => $this->getNextSetupStep($requirements)
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get setup requirements: ' . $e->getMessage());
            return response()->json(['success' => false], 500);
        }
    }

    private function getNextSetupStep($requirements)
    {
        $steps = [
            'store_basic' => 'store-basic',
            'store_logo' => 'my-store',
            'business_details' => 'business-details',
            'address' => 'address',
            'documents' => 'documents',
            'verification' => 'verification'
        ];

        foreach ($steps as $key => $step) {
            if (!$requirements[$key]) {
                return $step;
            }
        }

        return 'complete';
    }

    /**
     * Get shipping settings for authenticated seller
     */
    public function getShippingSettings(Request $request)
    {
        try {
            $user = $request->user();

            if (!isset($user->type) || $user->type !== 'seller') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only sellers can access shipping settings'
                ], 403);
            }

            $sellerProfile = SellerProfile::where('user_id', $user->id)->first();

            if (!$sellerProfile) {
                return response()->json([
                    'success' => false,
                    'message' => 'Seller profile not found'
                ], 404);
            }

            $shippingSetting = ShippingSetting::where('seller_profile_id', $sellerProfile->id)->first();

            if (!$shippingSetting) {
                // Create default settings if not exists
                $defaultSettings = ShippingSetting::getDefaultSettings();
                $shippingSetting = ShippingSetting::create(array_merge($defaultSettings, [
                    'seller_profile_id' => $sellerProfile->id
                ]));
            }

            return response()->json([
                'success' => true,
                'data' => $shippingSetting
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get shipping settings: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get shipping settings'
            ], 500);
        }
    }

    /**
     * Update shipping settings for authenticated seller
     */
    public function updateShippingSettings(Request $request)
    {
        try {
            $user = $request->user();

            if (!isset($user->type) || $user->type !== 'seller') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only sellers can update shipping settings'
                ], 403);
            }

            $sellerProfile = SellerProfile::where('user_id', $user->id)->first();

            if (!$sellerProfile) {
                return response()->json([
                    'success' => false,
                    'message' => 'Seller profile not found'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'enabled' => 'sometimes|boolean',
                'processing_time' => 'sometimes|in:same_day,1_2_days,3_5_days,5_7_days,custom',
                'custom_processing_time' => 'nullable|string|max:255',
                'free_shipping_threshold' => 'nullable|numeric|min:0',
                'free_shipping_enabled' => 'sometimes|boolean',
                'shipping_methods' => 'sometimes|array',
                'shipping_methods.*' => 'in:standard,express,next_day,free',
                'delivery_areas' => 'sometimes|array',
                'delivery_areas.*.city' => 'required|string|max:100',
                'delivery_areas.*.state' => 'required|string|max:100',
                'delivery_areas.*.country' => 'required|string|max:100',
                'delivery_areas.*.delivery_time' => 'required|string|max:50',
                'delivery_areas.*.rate' => 'required|numeric|min:0',
                'shipping_rates' => 'sometimes|array',
                'international_shipping' => 'sometimes|boolean',
                'international_rates' => 'nullable|array',
                'package_weight_unit' => 'sometimes|in:kg,g,lb,oz',
                'default_package_weight' => 'sometimes|numeric|min:0.01',
                'shipping_policy' => 'nullable|string|max:2000',
                'return_policy' => 'nullable|string|max:2000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $validated = $validator->validated();

            // Find or create shipping settings
            $shippingSetting = ShippingSetting::where('seller_profile_id', $sellerProfile->id)->first();

            if (!$shippingSetting) {
                $defaultSettings = ShippingSetting::getDefaultSettings();
                $shippingSetting = ShippingSetting::create(array_merge($defaultSettings, [
                    'seller_profile_id' => $sellerProfile->id
                ]));
            }

            // Update shipping settings
            $shippingSetting->update($validated);

            // Update seller profile shipping_enabled flag
            if (isset($validated['enabled'])) {
                $sellerProfile->update(['shipping_enabled' => $validated['enabled']]);
            }

            Log::info('Shipping settings updated', [
                'seller_profile_id' => $sellerProfile->id,
                'updated_fields' => array_keys($validated)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Shipping settings updated successfully',
                'data' => $shippingSetting->fresh()
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update shipping settings: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update shipping settings: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get shipping calculation for a product/cart
     */
    public function calculateShipping(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'seller_id' => 'required|exists:seller_profiles,user_id',
                'total_amount' => 'required|numeric|min:0',
                'items_count' => 'required|integer|min:1',
                'delivery_city' => 'required|string|max:100',
                'delivery_state' => 'required|string|max:100',
                'delivery_country' => 'required|string|max:100',
                'shipping_method' => 'sometimes|in:standard,express,next_day'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $validated = $validator->validated();

            $sellerProfile = SellerProfile::where('user_id', $validated['seller_id'])->first();

            if (!$sellerProfile) {
                return response()->json([
                    'success' => false,
                    'message' => 'Seller not found'
                ], 404);
            }

            $shippingSetting = ShippingSetting::where('seller_profile_id', $sellerProfile->id)->first();

            if (!$shippingSetting || !$shippingSetting->enabled) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'shipping_available' => false,
                        'message' => 'Shipping not available from this seller'
                    ]
                ]);
            }

            // Check if free shipping applies
            $isFreeShipping = false;
            if ($shippingSetting->free_shipping_enabled && $shippingSetting->free_shipping_threshold) {
                if ($validated['total_amount'] >= $shippingSetting->free_shipping_threshold) {
                    $isFreeShipping = true;
                }
            }

            // Calculate shipping cost
            $shippingCost = 0;
            $shippingMethod = $validated['shipping_method'] ?? 'standard';

            if (!$isFreeShipping && isset($shippingSetting->shipping_rates[$shippingMethod])) {
                $rate = $shippingSetting->shipping_rates[$shippingMethod];

                if ($rate['type'] === 'flat_rate') {
                    $shippingCost = $rate['amount'];
                    // Add per additional item cost
                    if ($validated['items_count'] > 1 && isset($rate['per_additional_item'])) {
                        $additionalItems = $validated['items_count'] - 1;
                        $shippingCost += ($additionalItems * $rate['per_additional_item']);
                    }
                } elseif ($rate['type'] === 'weight_based') {
                    // This would require weight calculation
                    $shippingCost = $rate['base_rate'];
                }
            }

            // Check delivery area
            $deliveryAvailable = $this->checkDeliveryArea(
                $shippingSetting,
                $validated['delivery_city'],
                $validated['delivery_state'],
                $validated['delivery_country']
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'shipping_available' => $deliveryAvailable,
                    'is_free_shipping' => $isFreeShipping,
                    'shipping_cost' => $shippingCost,
                    'shipping_method' => $shippingMethod,
                    'estimated_delivery' => $this->getEstimatedDelivery($shippingSetting, $shippingMethod),
                    'currency' => 'MMK'
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to calculate shipping: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to calculate shipping'
            ], 500);
        }
    }

    /**
     * Check if delivery is available to a specific area
     */
    private function checkDeliveryArea($shippingSetting, $city, $state, $country)
    {
        if (empty($shippingSetting->delivery_areas)) {
            // If no delivery areas specified, assume delivery is available nationwide
            return true;
        }

        foreach ($shippingSetting->delivery_areas as $area) {
            if (
                strtolower($area['city']) === strtolower($city) &&
                strtolower($area['state']) === strtolower($state) &&
                strtolower($area['country']) === strtolower($country)
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get estimated delivery date
     */
    private function getEstimatedDelivery($shippingSetting, $shippingMethod)
    {
        $processingDays = 3; // Default 3-5 days

        switch ($shippingSetting->processing_time) {
            case 'same_day':
                $processingDays = 0;
                break;
            case '1_2_days':
                $processingDays = 1;
                break;
            case '3_5_days':
                $processingDays = 3;
                break;
            case '5_7_days':
                $processingDays = 5;
                break;
        }

        // Add shipping method time
        switch ($shippingMethod) {
            case 'express':
                $processingDays += 1;
                break;
            case 'next_day':
                $processingDays = 1;
                break;
            default:
                $processingDays += 2; // Standard shipping
        }

        $deliveryDate = Carbon::now()->addWeekdays($processingDays);

        return [
            'date' => $deliveryDate->format('Y-m-d'),
            'days' => $processingDays,
            'formatted' => $deliveryDate->format('F j, Y')
        ];
    }

    /**
     * Get seller settings
     */
    public function getSettings(Request $request)
    {
        try {
            $user = $request->user();

            if (!isset($user->type) || $user->type !== 'seller') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only sellers can access settings'
                ], 403);
            }

            $sellerProfile = SellerProfile::where('user_id', $user->id)->first();

            if (!$sellerProfile) {
                return response()->json([
                    'success' => false,
                    'message' => 'Seller profile not found'
                ], 404);
            }

            // Get shipping settings
            $shippingSettings = ShippingSetting::where('seller_profile_id', $sellerProfile->id)->first();

            // Get user preferences
            $userSettings = $user->settings ?? [];

            $settings = [
                // Store Policies
                'return_policy' => $sellerProfile->return_policy,
                'shipping_policy' => $sellerProfile->shipping_policy,
                'warranty_policy' => $sellerProfile->warranty_policy,
                'privacy_policy' => $sellerProfile->privacy_policy,
                'terms_of_service' => $sellerProfile->terms_of_service,

                // Notification Settings
                'email_notifications' => $userSettings['email_notifications'] ?? true,
                'order_notifications' => $userSettings['order_notifications'] ?? true,
                'inventory_alerts' => $userSettings['inventory_alerts'] ?? true,
                'review_notifications' => $userSettings['review_notifications'] ?? true,

                // Payment Settings
                'commission_rate' => $sellerProfile->commission_rate ?? 10,
                'auto_withdrawal' => $sellerProfile->auto_withdrawal ?? false,
                'withdrawal_threshold' => $sellerProfile->withdrawal_threshold ?? 100000,
                'preferred_payment_method' => $sellerProfile->preferred_payment_method ?? 'bank_transfer',

                // Store Status
                'is_active' => $sellerProfile->is_active ?? true,
                'vacation_mode' => $sellerProfile->vacation_mode ?? false,
                'vacation_message' => $sellerProfile->vacation_message,
                'vacation_start_date' => $sellerProfile->vacation_start_date,
                'vacation_end_date' => $sellerProfile->vacation_end_date,

                // Security Settings
                'two_factor_auth' => $userSettings['two_factor_auth'] ?? false,
                'login_notifications' => $userSettings['login_notifications'] ?? true,

                // Display Settings
                'show_sold_out' => $userSettings['show_sold_out'] ?? true,
                'show_reviews' => $userSettings['show_reviews'] ?? true,
                'show_inventory_count' => $userSettings['show_inventory_count'] ?? false,
                'currency' => $sellerProfile->currency ?? 'MMK',

                // Business Hours
                'business_hours_enabled' => $sellerProfile->business_hours_enabled ?? false,
                'business_hours' => $sellerProfile->business_hours ?? [],

                // Shipping Settings
                'shipping_settings' => $shippingSettings,
            ];

            return response()->json([
                'success' => true,
                'data' => $settings
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get seller settings: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get seller settings'
            ], 500);
        }
    }

    /**
     * Update seller settings
     */
    public function updateSettings(Request $request)
    {
        try {
            $user = $request->user();

            if (!isset($user->type) || $user->type !== 'seller') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only sellers can update settings'
                ], 403);
            }

            $sellerProfile = SellerProfile::where('user_id', $user->id)->first();

            if (!$sellerProfile) {
                return response()->json([
                    'success' => false,
                    'message' => 'Seller profile not found'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                // Store Policies
                'return_policy' => 'nullable|string|max:2000',
                'shipping_policy' => 'nullable|string|max:2000',
                'warranty_policy' => 'nullable|string|max:2000',
                'privacy_policy' => 'nullable|string|max:2000',
                'terms_of_service' => 'nullable|string|max:2000',

                // Notification Settings
                'email_notifications' => 'sometimes|boolean',
                'order_notifications' => 'sometimes|boolean',
                'inventory_alerts' => 'sometimes|boolean',
                'review_notifications' => 'sometimes|boolean',

                // Payment Settings
                'commission_rate' => 'sometimes|numeric|min:0|max:30',
                'auto_withdrawal' => 'sometimes|boolean',
                'withdrawal_threshold' => 'nullable|numeric|min:0',
                'preferred_payment_method' => 'sometimes|in:bank_transfer,wave_money,kbz_pay,mpu,visa_master',

                // Store Status
                'is_active' => 'sometimes|boolean',
                'vacation_mode' => 'sometimes|boolean',
                'vacation_message' => 'nullable|string|max:1000',
                'vacation_start_date' => 'nullable|date',
                'vacation_end_date' => 'nullable|date|after_or_equal:vacation_start_date',

                // Security Settings
                'two_factor_auth' => 'sometimes|boolean',
                'login_notifications' => 'sometimes|boolean',

                // Display Settings
                'show_sold_out' => 'sometimes|boolean',
                'show_reviews' => 'sometimes|boolean',
                'show_inventory_count' => 'sometimes|boolean',
                'currency' => 'sometimes|in:MMK,USD,EUR,THB',

                // Business Hours
                'business_hours_enabled' => 'sometimes|boolean',
                'business_hours' => 'sometimes|array',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $validated = $validator->validated();

            // Update seller profile
            $sellerProfile->update($validated);

            // Update user settings
            $userSettings = $user->settings ?? [];

            // Update notification settings
            if (isset($validated['email_notifications'])) {
                $userSettings['email_notifications'] = $validated['email_notifications'];
            }
            if (isset($validated['order_notifications'])) {
                $userSettings['order_notifications'] = $validated['order_notifications'];
            }
            if (isset($validated['inventory_alerts'])) {
                $userSettings['inventory_alerts'] = $validated['inventory_alerts'];
            }
            if (isset($validated['review_notifications'])) {
                $userSettings['review_notifications'] = $validated['review_notifications'];
            }
            if (isset($validated['two_factor_auth'])) {
                $userSettings['two_factor_auth'] = $validated['two_factor_auth'];
            }
            if (isset($validated['login_notifications'])) {
                $userSettings['login_notifications'] = $validated['login_notifications'];
            }
            if (isset($validated['show_sold_out'])) {
                $userSettings['show_sold_out'] = $validated['show_sold_out'];
            }
            if (isset($validated['show_reviews'])) {
                $userSettings['show_reviews'] = $validated['show_reviews'];
            }
            if (isset($validated['show_inventory_count'])) {
                $userSettings['show_inventory_count'] = $validated['show_inventory_count'];
            }

            // Save user settings
            $user->settings = $userSettings;
            $user->save();

            Log::info('Seller settings updated', [
                'user_id' => $user->id,
                'seller_profile_id' => $sellerProfile->id,
                'updated_fields' => array_keys($validated)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Settings updated successfully',
                'data' => $sellerProfile->fresh()
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update seller settings: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update settings'
            ], 500);
        }
    }

    /**
     * Get store statistics
     */
    public function getStoreStats(Request $request)
    {
        try {
            $user = $request->user();

            if (!isset($user->type) || $user->type !== 'seller') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only sellers can access store stats'
                ], 403);
            }

            $sellerProfile = SellerProfile::where('user_id', $user->id)->first();

            if (!$sellerProfile) {
                return response()->json([
                    'success' => false,
                    'message' => 'Seller profile not found'
                ], 404);
            }

            $thirtyDaysAgo = Carbon::now()->subDays(30);

            // Calculate stats
            $totalProducts = Product::where('seller_id', $user->id)->count();
            $totalOrders = Order::where('seller_id', $user->id)->count();
            $totalRevenue = Order::where('seller_id', $user->id)
                ->where('status', 'delivered')
                ->sum('total_amount');

            $recentOrders = Order::where('seller_id', $user->id)
                ->where('created_at', '>=', $thirtyDaysAgo)
                ->count();

            $pendingOrders = Order::where('seller_id', $user->id)
                ->whereIn('status', ['pending', 'processing'])
                ->count();

            $stats = [
                'overall' => [
                    'total_products' => $totalProducts,
                    'total_orders' => $totalOrders,
                    'total_revenue' => $totalRevenue,
                    'total_revenue_formatted' => 'MMK ' . number_format($totalRevenue, 2)
                ],
                'recent' => [
                    'orders_last_30_days' => $recentOrders,
                    'pending_orders' => $pendingOrders
                ],
                'performance' => [
                    'average_rating' => $sellerProfile->averageRating(),
                    'total_reviews' => $sellerProfile->totalReviews(),
                    'customer_satisfaction' => $sellerProfile->reviews()->where('rating', '>=', 4)->count() / max($sellerProfile->totalReviews(), 1) * 100
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get store stats: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get store stats'
            ], 500);
        }
    }

    /**
     * Update business hours
     */
    public function updateBusinessHours(Request $request)
    {
        try {
            $user = $request->user();
            $sellerProfile = SellerProfile::where('user_id', $user->id)->first();

            if (!$sellerProfile) {
                return response()->json([
                    'success' => false,
                    'message' => 'Seller profile not found'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'business_hours_enabled' => 'sometimes|boolean',
                'business_hours' => 'required|array',
                'business_hours.*.open' => 'required|date_format:H:i',
                'business_hours.*.close' => 'required|date_format:H:i',
                'business_hours.*.closed' => 'required|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $sellerProfile->update($validator->validated());

            return response()->json([
                'success' => true,
                'message' => 'Business hours updated successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update business hours: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update business hours'
            ], 500);
        }
    }

    /**
     * Update store policies
     */
    public function updatePolicies(Request $request)
    {
        try {
            $user = $request->user();
            $sellerProfile = SellerProfile::where('user_id', $user->id)->first();

            if (!$sellerProfile) {
                return response()->json([
                    'success' => false,
                    'message' => 'Seller profile not found'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'return_policy' => 'nullable|string|max:2000',
                'shipping_policy' => 'nullable|string|max:2000',
                'warranty_policy' => 'nullable|string|max:2000',
                'privacy_policy' => 'nullable|string|max:2000',
                'terms_of_service' => 'nullable|string|max:2000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $sellerProfile->update($validator->validated());

            return response()->json([
                'success' => true,
                'message' => 'Store policies updated successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update store policies: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update store policies'
            ], 500);
        }
    }

}
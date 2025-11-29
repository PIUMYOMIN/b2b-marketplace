<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\SellerReviewController;
use App\Http\Controllers\Api\WishlistController;
use App\Http\Controllers\Api\SellerController;
use App\Http\Controllers\Api\DeliveryController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\FollowController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::group([
    'prefix' => 'v1',
    'middleware' => 'api'
], function () {

    // --------------------
    // Authentication
    // --------------------
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    Route::prefix('auth')->group(function () {
        Route::post('/refresh-token', [AuthController::class, 'refreshToken']);

        Route::middleware('auth:sanctum')->group(function () {
            Route::get('/me', [AuthController::class, 'me']);
            Route::post('/logout', [AuthController::class, 'logout']);
        });
    });

    // --------------------
    // Public Routes
    // --------------------

    // Business Type (Public)
    Route::get('/business-types', [SellerController::class, 'getBusinessTypes']);

    // Seller Routes (Public)
    Route::prefix('sellers')->group(function () {
        Route::get('/', [SellerController::class, 'index']);
        Route::get('/{seller}', [SellerController::class, 'show']);
        Route::get('/{seller}/products', [SellerController::class, 'sellerProducts']);
        Route::get('/{seller}/reviews', [SellerController::class, 'sellerReviews']);
    });

    // Public Routes For Products
    Route::prefix('products')->group(function () {
        Route::get('/', [ProductController::class, 'indexPublic']);
        Route::get('/{product}', [ProductController::class, 'showPublic']);
        Route::get('/search/public', [ProductController::class, 'searchPublic']);
        Route::get('/category/{categoryId}', [ProductController::class, 'categoryProducts']);
    });

    // Categories
    Route::prefix('categories')->group(function () {
        Route::get('/', [CategoryController::class, 'index']);
        Route::get('/{category}', [CategoryController::class, 'show']);
    });
    
    // Reviews
    Route::prefix('reviews')->group(function () {
        Route::get('/products/{product}', [ReviewController::class, 'productReviews']);
    });
    
    // --------------------
    // Authenticated Routes
    // --------------------
    Route::middleware(['auth:sanctum'])->group(function () {

        Route::prefix('seller')->middleware('role:seller')->group(function () {
            Route::get('/onboarding/status', [SellerController::class, 'getOnboardingStatus']);
            Route::post('/onboarding/complete', [SellerController::class, 'completeOnboarding']);
            Route::post('/onboarding/store-basic', [SellerController::class, 'updateStoreBasic']);
            Route::post('/onboarding/business-details', [SellerController::class, 'updateBusinessDetails']);
            Route::post('/onboarding/address', [SellerController::class, 'updateAddress']);
        });

        // Dashboard
        Route::prefix('dashboard')->group(function () {
            Route::get('/', [DashboardController::class, 'index']);
            Route::get('/sellers', [DashboardController::class, 'getSellers']);
            Route::post('/{seller}/approve', [DashboardController::class, 'adminApprove'])->middleware('role:admin');
            Route::post('/{seller}/reject', [DashboardController::class, 'adminReject'])->middleware('role:admin');
            Route::get('/stats', [DashboardController::class, 'stats']);
            Route::get('/sales-report', [DashboardController::class, 'salesReport']);
            Route::get('/top-sellers', [DashboardController::class, 'topSellers']);
            Route::get('/user-registrations', [DashboardController::class, 'userRegistrationsOverTime']);
            Route::get('/order-status-summary', [DashboardController::class, 'orderStatusSummary']);
            Route::get('/monthly-revenue', [DashboardController::class, 'monthlyRevenueTrend']);
            Route::get('/recent-orders', [DashboardController::class, 'recentOrders']);
            Route::get('/commission-summary', [DashboardController::class, 'commissionSummary']);
            Route::get('/users-by-role', [DashboardController::class, 'usersCountByRole']);
            Route::get('/recent-users', [DashboardController::class, 'recentUsers']);
            Route::get('/active-inactive-users', [DashboardController::class, 'activeInactiveUsers']);
            Route::get('/user-growth', [DashboardController::class, 'userGrowthLast30Days']);

            // Admin seller management
            Route::prefix('seller')->group(function () {
                Route::get('/my-store', [SellerController::class, 'myStore'])->middleware('role:seller');
                Route::get('/dashboard', [SellerController::class, 'dashboard']);
                Route::get('/sales-summary', [SellerController::class, 'salesSummary']);
                Route::get('/top-products', [SellerController::class, 'topProducts']);
                Route::get('/recent-orders', [SellerController::class, 'recentOrders']);
                Route::get('/performance-metrics', [SellerController::class, 'performanceMetrics']);
                Route::get('/delivery-stats', [SellerController::class, 'deliveryStats'])->middleware('role:seller');
                Route::get('/products/my-products', [ProductController::class, 'myProducts'])->middleware('role:seller');
            });


            Route::prefix('deliveries')->group(function () {
                Route::get('/', [DeliveryController::class, 'index']);
                Route::get('/{delivery}/tracking', [DeliveryController::class, 'getTrackingUpdates']);
                Route::post('/{delivery}/status', [DeliveryController::class, 'updateStatus']);
                Route::post('/{delivery}/proof', [DeliveryController::class, 'uploadDeliveryProof']);
                Route::post('/{delivery}/assign-courier', [DeliveryController::class, 'assignCourier']);
            });

            // Order delivery method selection
            Route::post('/orders/{order}/delivery-method', [DeliveryController::class, 'chooseDeliveryMethod']);

            // Admin/Seller review management
            Route::get('/reviews', [ReviewController::class, 'index'])->middleware('role:admin|seller');

            // Seller-specific dashboard
            Route::get('/seller-sales-summary', [DashboardController::class, 'sellerSalesSummary'])->middleware('role:seller');
            Route::get('/seller-top-products', [DashboardController::class, 'sellerTopProducts'])->middleware('role:seller');
            Route::get('/seller-recent-orders', [DashboardController::class, 'sellerRecentOrders'])->middleware('role:seller');
        });

        // ✅ SELLER MANAGEMENT ROUTES (Admin + Seller)
        Route::prefix('sellers')->group(function () {

            Route::middleware('role:seller')->group(function () {
                Route::put('/my-store/update', [SellerController::class, 'updateMyStore']);
                Route::get('/my-store', [SellerController::class, 'myStore']);
            });

            // Admin only routes
            Route::middleware('role:admin|seller')->group(function () {
                Route::put('/{seller}', [SellerController::class, 'update']);
                Route::delete('/{seller}', [SellerController::class, 'destroy']);
            });

            // Seller reviews (buyers can review sellers)
            Route::post('/{seller}/reviews', [SellerReviewController::class, 'store'])->middleware('role:buyer|admin');
            Route::get('/my-reviews', [SellerReviewController::class, 'myReviews'])->middleware('role:buyer|admin');
            Route::put('/{review}', [SellerReviewController::class, 'update'])->middleware('role:buyer|admin');
            Route::delete('/{review}', [SellerReviewController::class, 'destroy'])->middleware('role:buyer|admin');
        });

        // Users
        Route::prefix('users')->group(function () {
            Route::get('/', [UserController::class, 'index'])->middleware('role:admin');
            Route::get('/{user}', [UserController::class, 'show']);
            Route::put('/{user}', [UserController::class, 'update']);
            Route::delete('/{user}', [UserController::class, 'destroy'])->middleware('role:admin');

            Route::post('/{user}/assign-roles', [UserController::class, 'assignRoles'])->middleware('role:admin');
            Route::get('/roles/list', [UserController::class, 'getRoles'])->middleware('role:admin');

            // Profile
            Route::prefix('profile')->group(function () {
                Route::put('/', [UserController::class, 'updateProfile']);
                Route::put('/password', [UserController::class, 'changePassword']);
            });
        });

        // Products
        Route::prefix('products')->group(function () {
            Route::get('/search', [ProductController::class, 'search']);
            Route::post('/{product}/reviews', [ReviewController::class, 'store'])->middleware('role:buyer');
            
            // Image Management
            Route::post('/upload-image', [ProductController::class, 'uploadImage']);
            Route::post('/{product}/upload-image', [ProductController::class, 'uploadImageToProduct']);
            Route::delete('/{product}/images/{imageIndex}', [ProductController::class, 'deleteImage']);
            Route::post('/{product}/set-primary-image/{imageIndex}', [ProductController::class, 'setPrimaryImage']);

            // Seller/Admin management
            Route::middleware('role:seller|admin')->group(function () {
                Route::post('/', [ProductController::class, 'store']);
                Route::put('/{product}', [ProductController::class, 'update']);
                Route::delete('/{product}', [ProductController::class, 'destroy']);
                Route::get('/my-products', [ProductController::class, 'myProducts']);
            });
        });

        // Buyer Cart Management
        Route::prefix('cart')->group(function () {
            Route::get('/', [CartController::class, 'index']);
            Route::post('/', [CartController::class, 'store']);
            Route::put('/{cart}', [CartController::class, 'update']);
            Route::delete('/{cart}', [CartController::class, 'destroy']);
            Route::post('/clear', [CartController::class, 'clear']);
            Route::get('/count', [CartController::class, 'count']);
        });

        // Reviews
        Route::prefix('reviews')->group(function () {
            Route::get('/my-reviews', [ReviewController::class, 'myReviews']);

            Route::middleware('role:admin')->group(function () {
                Route::post('/{review}/approve', [ReviewController::class, 'approve']);
                Route::post('/{review}/reject', [ReviewController::class, 'reject']);
                Route::put('/{review}/status', [ReviewController::class, 'updateStatus']);
                Route::delete('/{review}', [ReviewController::class, 'destroy']);
            });
        });

        // Seller Product Reviews
        Route::prefix('seller')->middleware('role:seller')->group(function () {
            Route::get('/products/reviews', [ReviewController::class, 'sellerReviews']);
        });

        // Categories
        Route::prefix('categories')->group(function () {
            Route::post('/', [CategoryController::class, 'store'])->middleware('role:admin');
            Route::put('/{category}', [CategoryController::class, 'update'])->middleware('role:admin');
            Route::delete('/{category}', [CategoryController::class, 'destroy'])->middleware('role:admin');
        });

        // Orders
        Route::prefix('orders')->group(function () {
            Route::get('/', [OrderController::class, 'index']);
            Route::post('/', [OrderController::class, 'store'])->middleware('role:buyer|admin');
            Route::get('/{order}', [OrderController::class, 'show']);
            Route::post('/{order}/cancel', [OrderController::class, 'cancel']);
            Route::patch('/{order}/payment', [OrderController::class, 'updatePayment']);
        
            // Seller order management
            Route::middleware('role:seller|admin')->group(function () {
                Route::post('/{order}/confirm', [OrderController::class, 'confirm']);
                Route::post('/{order}/process', [OrderController::class, 'process']);
                Route::post('/{order}/ship', [OrderController::class, 'ship']);
            });
        
            // Buyer order management
            Route::middleware('role:buyer|admin')->group(function () {
                Route::post('/{order}/confirm-delivery', [OrderController::class, 'confirmDelivery']);
            });
        });

        // In api.php
        Route::prefix('wishlist')->middleware('auth:sanctum')->group(function () {
            Route::get('/', [WishlistController::class, 'index']);
            Route::post('/', [WishlistController::class, 'store']);
            Route::get('/count', [WishlistController::class, 'count']);
            Route::get('/check/{productId}', [WishlistController::class, 'check']);
            Route::delete('/{productId}', [WishlistController::class, 'destroy']);
        });

        // Follow routes
        Route::prefix('follow')->middleware('auth:sanctum')->group(function () {
            Route::post('/seller/{seller}', [FollowController::class, 'followSeller']);
            Route::delete('/seller/{seller}', [FollowController::class, 'unfollowSeller']);
            Route::post('/seller/{seller}/toggle', [FollowController::class, 'toggleFollow']);
            Route::get('/seller/{seller}/status', [FollowController::class, 'checkFollowStatus']);
            Route::get('/my-sellers', [FollowController::class, 'getFollowedSellers']);
            Route::get('/seller/{seller}/followers', [FollowController::class, 'getSellerFollowers']);
        });

        // Payments
        Route::prefix('payments')->group(function () {
            Route::post('/initiate', [PaymentController::class, 'initiate']);
            Route::post('/verify', [PaymentController::class, 'verify']);
            Route::get('/history', [PaymentController::class, 'history']);
        });
    });

    // --------------------
    // Webhooks (no auth)
    // --------------------
    Route::prefix('webhooks')->group(function () {
        Route::post('/mmqr', [PaymentController::class, 'handleMMQRWebhook']);
        Route::post('/kbzpay', [PaymentController::class, 'handleKBZPayWebhook']);
    });

    // --------------------
    // Fallback
    // --------------------
    Route::fallback(function () {
        return response()->json([
            'success' => false,
            'message' => 'API endpoint not found'
        ], 404);
    });

    Route::get('/test-cors', function() {
        return response()->json(['message' => 'CORS is working']);
    });
});
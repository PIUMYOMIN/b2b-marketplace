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
use App\Http\Controllers\Api\BusinessTypeController;
use App\Http\Controllers\Api\DiscountController;
use App\Http\Controllers\Api\DeliveryAreaController;

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
    Route::get('/business-types', [BusinessTypeController::class, 'index']);
    Route::get('/business-types/{slug}', [BusinessTypeController::class, 'show']);

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
        Route::get('/homepage', [CategoryController::class, 'homepage']);
        Route::get('/tree', [CategoryController::class, 'tree']);
        Route::get('/for-filter', [CategoryController::class, 'forFilter']);
        Route::get('/{category}/descendants', [CategoryController::class, 'descendants']);
        Route::get('/categories-with-products', [CategoryController::class, 'indexWithProductCounts']);
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

            Route::prefix('onboarding')->group(function () {
                // Onboarding Status
                Route::get('/status', [SellerController::class, 'getOnboardingStatus']);
                Route::get('/check-status', [SellerController::class, 'checkProfileStatus']);

                // Onboarding Steps
                // Store logo and banner upload endpoints
                Route::post('/storeLogo', [SellerController::class, 'uploadStoreLogo']);
                Route::post('/storeBanner', [SellerController::class, 'uploadStoreBanner']);
                // Basic store info save endpoint
                Route::post('/store-basic', [SellerController::class, 'updateStoreBasic']);
                //Business save endpoint
                Route::post('/business-details', [SellerController::class, 'updateBusinessDetails']);
                // Address save endpoint
                Route::post('/address', [SellerController::class, 'updateAddress']);
                // Document upload endpoint
                Route::post('/documents', [SellerController::class, 'uploadDocument']);
                // Document completion endpoint
                Route::post('/mark-documents-complete', [SellerController::class, 'markDocumentsComplete']);

                Route::post('/step/{step}', [SellerController::class, 'saveStep']);

                // Get current onboarding data
                Route::get('/data', [SellerController::class, 'getOnboardingData']);

                // Final submission endpoint
                Route::post('/submit', [SellerController::class, 'submitOnboarding']);
                Route::post('/upload-document', [SellerController::class, 'uploadDocument']);
            });

            // Document Upload & Management
            Route::get('/document-requirements', [SellerController::class, 'getDocumentRequirements']);
            Route::get('/documents', [SellerController::class, 'getUploadedDocuments']);
            Route::delete('/documents/{documentId}', [SellerController::class, 'deleteDocument']);

            // Complete Onboarding
            Route::post('/complete-onboarding', [SellerController::class, 'completeOnboardingWithDocuments']);

            // Verification Status
            Route::get('/verification-status', [SellerController::class, 'getVerificationStatus']);
            Route::get('/verification-history', [SellerController::class, 'getVerificationHistory']);

            // Seller Dashboard
            Route::get('/my-store', [SellerController::class, 'myStore']);
            Route::get('/dashboard', [SellerController::class, 'dashboard']);
            Route::get('/sales-summary', [SellerController::class, 'salesSummary']);
            Route::get('/top-products', [SellerController::class, 'topProducts']);
            Route::get('/recent-orders', [SellerController::class, 'recentOrders']);
            Route::get('/performance-metrics', [SellerController::class, 'performanceMetrics']);
            Route::get('/delivery-stats', [SellerController::class, 'deliveryStats']);
            Route::get('/products/my-products', [ProductController::class, 'myProducts']);

            //Product
            Route::prefix('products')->group(function () {
                Route::put('/{product}', [ProductController::class, 'update']);
            });

            // Seller Product Reviews
            Route::get('/products/reviews', [ReviewController::class, 'sellerReviews']);

            Route::prefix('discounts')->group(function () {
                Route::get('/', [DiscountController::class, 'index']);
                Route::post('/', [DiscountController::class, 'store']);
                Route::get('/{discount}', [DiscountController::class, 'show']);
                Route::put('/{discount}', [DiscountController::class, 'update']);
                Route::delete('/{discount}', [DiscountController::class, 'destroy']);
                Route::post('/validate', [DiscountController::class, 'validateDiscount']);
                Route::get('/product/{productId}', [DiscountController::class, 'getProductDiscounts']);
                Route::put('/{discount}/toggle-status', [DiscountController::class, 'toggleStatus']);
            });

            // In routes/api.php, within seller prefix
            Route::prefix('delivery-areas')->group(function () {
                Route::get('/', [DeliveryAreaController::class, 'index']);
                Route::post('/', [DeliveryAreaController::class, 'store']);
                Route::get('/location-options', [DeliveryAreaController::class, 'getLocationOptions']);
                Route::post('/check-shipping-fee', [DeliveryAreaController::class, 'checkShippingFee']);
                Route::put('/{id}', [DeliveryAreaController::class, 'update']);
                Route::delete('/{id}', [DeliveryAreaController::class, 'destroy']);
            });

            Route::prefix('shipping')->group(function () {
                Route::get('/settings', [SellerController::class, 'getShippingSettings']);
                Route::put('/settings', [SellerController::class, 'updateShippingSettings']);
                Route::post('/calculate', [SellerController::class, 'calculateShipping']);
            });

            // Settings routes
            Route::get('/settings', [SellerController::class, 'getSettings']);
            Route::put('/settings', [SellerController::class, 'updateSettings']);
            Route::get('/store-stats', [SellerController::class, 'getStoreStats']);

            // Shipping settings (separate from general settings)
            Route::prefix('shipping')->group(function () {
                Route::get('/settings', [SellerController::class, 'getShippingSettings']);
                Route::put('/settings', [SellerController::class, 'updateShippingSettings']);
            });

            // Business hours
            Route::put('/business-hours', [SellerController::class, 'updateBusinessHours']);

            // Store policies
            Route::put('/policies', [SellerController::class, 'updatePolicies']);
        });

        // Dashboard
        Route::prefix('admin')->group(function () {
            Route::get('/', [DashboardController::class, 'index']);
            Route::post('/{seller}/reject', [DashboardController::class, 'adminReject']);
            Route::get('/sellers', [DashboardController::class, 'getSellers']);
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
            Route::get('/seller-sales-summary', [DashboardController::class, 'sellerSalesSummary']);
            Route::get('/seller-top-products', [DashboardController::class, 'sellerTopProducts']);
            Route::get('/seller-recent-orders', [DashboardController::class, 'sellerRecentOrders']);

            // Seller Verification Routes
            Route::prefix('seller')->group(function () {
                Route::put('/{id}/status', [SellerController::class, 'update']);
                Route::put('/{id}/status', [SellerController::class, 'updateStatus']);
                Route::put('/{id}', [SellerController::class, 'update']);
                Route::get('/{id}/status', [SellerController::class, 'getSellerStatus']);
                Route::put('/{id}/status', [SellerController::class, 'sellerApprove']);
                // Get sellers for verification review
                Route::get('/verification-review', [SellerController::class, 'getSellersForVerificationReview']);
                Route::get('/pending-verification', [SellerController::class, 'getPendingVerification']);
                Route::get('/sellers-with-documents', [SellerController::class, 'getSellersWithDocuments']);

                // Individual seller verification
                Route::get('/{id}/verification-status', [SellerController::class, 'getSellerStatus']);
                Route::get('/{id}/documents', [SellerController::class, 'getSellerDocuments']);

                // Verification actions
                Route::post('/{id}/verify', [SellerController::class, 'verifySeller']);
                Route::post('/{id}/reject', [SellerController::class, 'rejectVerification']);
                Route::put('/{id}/verification-status', [SellerController::class, 'updateVerificationStatus']);

                // Seller status management
                Route::put('/{id}/status', [SellerController::class, 'updateStatus']);
                Route::get('/{id}/status', [SellerController::class, 'getSellerStatus']);

                Route::get('/seller-verification', [SellerController::class, 'getAllSellerVerifications']);
                Route::get('/seller-verification/{verification}', [SellerController::class, 'getSellerVerificationDetails']);
                Route::post('/seller-verification/{verification}/approve', [SellerController::class, 'approveSellerVerification']);
                Route::post('/{verification}/reject', [SellerController::class, 'rejectSellerVerification']);
                Route::post('/{seller}/approve', [DashboardController::class, 'adminApprove']);
            });

            Route::prefix('/business-types')->middleware('role:admin')->group(function () {
                Route::post('/', [BusinessTypeController::class, 'store']);
                Route::put('/{id}', [BusinessTypeController::class, 'update']);
                Route::delete('/{id}', [BusinessTypeController::class, 'destroy']);
            });

        });

        // Delivery Management
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

                Route::delete('/{product}', [ProductController::class, 'destroy']);
                Route::get('/my-products', [ProductController::class, 'myProducts']);
            });
        });

        Route::prefix('products')->group(function () {
            Route::post('/{product}/apply-discount', [ProductController::class, 'applyDiscount']);
            Route::post('/{product}/remove-discount', [ProductController::class, 'removeDiscount']);
            Route::get('/{product}/discounts', [ProductController::class, 'productDiscounts']);
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
});

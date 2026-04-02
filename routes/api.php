<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\CommissionRuleController;
use App\Http\Controllers\Api\NewsletterController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\VerificationController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ContactMessageController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProductReviewController;
use App\Http\Controllers\Api\SellerReviewController;
use App\Http\Controllers\Api\WishlistController;
use App\Http\Controllers\Api\SellerController;
use App\Http\Controllers\Api\DeliveryController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\FollowController;
use App\Http\Controllers\Api\BusinessTypeController;
use App\Http\Controllers\Api\DiscountController;
use App\Http\Controllers\Api\CouponController;
use App\Http\Controllers\Api\DeliveryAreaController;
use App\Http\Controllers\Api\OrderTrackingController;
use App\Http\Controllers\Api\RevenueExportController;

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

    Route::prefix('email')->group(function () {
        Route::get('/verify/{id}/{hash}', [VerificationController::class, 'verify'])
            ->name('verification.verify')
            ->withoutMiddleware(['auth', 'auth:sanctum', 'verified']);
        Route::post('/resend', [VerificationController::class, 'resend'])->middleware('auth:sanctum');
        Route::post('/verify-code', [VerificationController::class, 'verifyCode'])->middleware('auth:sanctum');
    });

    // Password Reset
    Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset']);

    //Contact
    Route::post('/contact', [ContactMessageController::class, 'submit']);

    // Newsletter (public)
    Route::post('/newsletter/subscribe', [NewsletterController::class, 'subscribe']);
    Route::get('/newsletter/confirm', [NewsletterController::class, 'confirm']);
    Route::get('/newsletter/unsubscribe', [NewsletterController::class, 'unsubscribe']);

    //Order Tracking
    Route::get('/track/{orderNumber}', [OrderTrackingController::class, 'track'])
        ->where('orderNumber', '[A-Za-z0-9\-]+');

    // --------------------
    // Public Routes
    // --------------------

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
        Route::get('/{slugOrId}', [ProductController::class, 'showPublic']);
        Route::get('/search/public', [ProductController::class, 'searchPublic']);
        Route::get('/category/{categoryId}', [ProductController::class, 'categoryProducts']);
    });

    // Categories
    Route::prefix('categories')->group(function () {
        Route::get('/', [CategoryController::class, 'index']);
        Route::get('/for-filter', [CategoryController::class, 'forFilter']);
        Route::get('/{category}/descendants', [CategoryController::class, 'descendants']);
        Route::get('/{category}', [CategoryController::class, 'show']);
    });

    // Reviews
    Route::prefix('reviews')->group(function () {
        Route::get('/products/{product}', [ProductReviewController::class, 'productReviews']);
        Route::get('/sellers/{slug}', [SellerReviewController::class, 'sellerReviews']);
    });

    // --------------------
    // Authenticated Routes
    // --------------------
    Route::middleware(['auth:sanctum'])->group(function () {

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
            Route::get('/revenue/export', [RevenueExportController::class, 'adminExport']);
            Route::get('/categories', [CategoryController::class, 'indexAdmin'])->middleware('role:admin');
            Route::get('/users-by-role', [DashboardController::class, 'usersCountByRole']);
            Route::get('/recent-users', [DashboardController::class, 'recentUsers']);
            Route::get('/active-inactive-users', [DashboardController::class, 'activeInactiveUsers']);

            // Admin newsletter + campaigns
            Route::prefix('newsletter')->middleware('role:admin')->group(function () {
                Route::get('/subscribers', [NewsletterController::class, 'subscribers']);
                Route::get('/campaigns', [NewsletterController::class, 'campaigns']);
                Route::post('/campaigns', [NewsletterController::class, 'createCampaign']);
                Route::put('/campaigns/{id}', [NewsletterController::class, 'updateCampaign']);
                Route::post('/campaigns/{id}/send', [NewsletterController::class, 'sendCampaign']);
                Route::get('/campaigns/{id}/preview', [NewsletterController::class, 'previewCampaign']);
            });

            // Commission Rules (admin CRUD)
            Route::prefix('commission-rules')->group(function () {
                Route::get('/', [CommissionRuleController::class, 'index']);
                Route::post('/', [CommissionRuleController::class, 'store']);
                Route::put('/{id}', [CommissionRuleController::class, 'update']);
                Route::delete('/{id}', [CommissionRuleController::class, 'destroy']);
            });
            Route::get('/user-growth', [DashboardController::class, 'userGrowthLast30Days']);
            Route::get('/seller-sales-summary', [DashboardController::class, 'sellerSalesSummary']);
            Route::get('/seller-top-products', [DashboardController::class, 'sellerTopProducts']);
            Route::get('/seller-recent-orders', [DashboardController::class, 'sellerRecentOrders']);

            // ── Admin Seller Management ─────────────────────────────────────────
            Route::prefix('sellers')->group(function () {
                // List all sellers (paginated, filterable by status/search)
                Route::get('/', [DashboardController::class, 'getSellers']);
            });

            Route::prefix('seller')->group(function () {
                // Verification queue
                Route::get('/verification-review', [SellerController::class, 'getSellersForVerificationReview']);
                Route::get('/pending-verification', [SellerController::class, 'getPendingVerification']);
                Route::get('/sellers-with-documents', [SellerController::class, 'getSellersWithDocuments']);

                // Full detail view (profile + documents + history)
                Route::get('/{id}/detail', [SellerController::class, 'getSellerDetail']);

                // Documents & status reads
                Route::get('/{id}/documents', [SellerController::class, 'getSellerDocuments']);
                Route::get('/{id}/status', [SellerController::class, 'getSellerStatus']);
                Route::get('/{id}/verification-status', [SellerController::class, 'getSellerStatus']);

                // Verification actions
                Route::post('/{id}/verify', [SellerController::class, 'verifySeller']);
                Route::post('/{id}/reject', [SellerController::class, 'rejectVerification']);
                Route::put('/{id}/verification-status', [SellerController::class, 'updateVerificationStatus']);

                // Store status management
                Route::put('/{id}/status', [SellerController::class, 'updateStatus']);
                Route::put('/{id}/approve', [SellerController::class, 'sellerApprove']);
                Route::post('/{id}/suspend', [SellerController::class, 'suspendSeller']);
                Route::post('/{id}/reactivate', [SellerController::class, 'reactivateSeller']);
            });

            Route::prefix('/business-types')->middleware('role:admin')->group(function () {
                Route::post('/', [BusinessTypeController::class, 'store']);
                Route::put('/{id}', [BusinessTypeController::class, 'update']);
                Route::delete('/{id}', [BusinessTypeController::class, 'destroy']);
            });


            //Review management by admin
            Route::prefix('reviews')->group(function () {
                Route::get('/', [ProductReviewController::class, 'index']);
                Route::get('/pending', [ProductReviewController::class, 'pendingReviews']);
                Route::post('/{review}/approve', [ProductReviewController::class, 'approve']);
                Route::post('/{review}/reject', [ProductReviewController::class, 'reject']);
                Route::put('/{review}/status', [ProductReviewController::class, 'updateStatus']);
                Route::delete('/{review}', [ProductReviewController::class, 'destroy']);
            });

            Route::prefix('seller-reviews')->group(function () {
                Route::get('/', [SellerReviewController::class, 'adminIndex']);
                Route::get('/pending', [SellerReviewController::class, 'pendingReviews']);
                Route::post('/{review}/approve', [SellerReviewController::class, 'approve']);
                Route::post('/{review}/reject', [SellerReviewController::class, 'reject']);
                Route::put('/{review}/status', [SellerReviewController::class, 'updateStatus']);
                Route::delete('/{review}', [SellerReviewController::class, 'destroy']);
            });


            //Product management by admin
            Route::prefix('products')->group(function () {
                Route::get('/', [ProductController::class, 'adminIndex']);
                Route::post('/{product}/approve', [ProductController::class, 'approve'])
                    ->whereNumber('product');
                Route::post('/{product}/reject', [ProductController::class, 'reject'])
                    ->whereNumber('product');
                Route::patch('/{product}/toggle-status', [ProductController::class, 'toggleStatus'])
                    ->whereNumber('product');
            });

            Route::prefix('contact-messages')->group(function () {
                Route::get('/', [ContactMessageController::class, 'index']);
                Route::get('/{id}', [ContactMessageController::class, 'show']);
                Route::put('/{id}/read', [ContactMessageController::class, 'markAsRead']);
                Route::delete('/{id}', [ContactMessageController::class, 'destroy']);
            });
        });

        Route::prefix('seller')->middleware('role:seller')->group(function () {

            // Logo & Banner endpoints (outside onboarding)
            Route::post('/logo', [SellerController::class, 'updateLogo']);
            Route::delete('/logo', [SellerController::class, 'removeLogo']);
            Route::post('/banner', [SellerController::class, 'updateBanner']);
            Route::delete('/banner', [SellerController::class, 'removeBanner']);

            Route::post('/init-profile', [SellerController::class, 'initProfile']);

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
            Route::get('/customers', [SellerController::class, 'customers']);

            // Revenue export
            Route::get('/revenue/seller-export', [RevenueExportController::class, 'sellerExport']);

            Route::put('/my-store/update', [SellerController::class, 'updateMyStore']);

            //Product
            Route::prefix('products')->group(function () {

                // Seller Product Management
                Route::post('/', [ProductController::class, 'store']);
                Route::get('/', [ProductController::class, 'myProducts']);
                Route::get('/{id}/edit', [ProductController::class, 'getProductForEdit']);
                Route::put('/{slugOrId}', [ProductController::class, 'update']);
                Route::delete('/{product}', [ProductController::class, 'destroy']);
                Route::get('/search', [ProductController::class, 'search']);

                // Image Management
                Route::post('/upload-image', [ProductController::class, 'uploadImage']);
                Route::post('/{product}/upload-image', [ProductController::class, 'uploadImageToProduct']);
                Route::delete('/{product}/images/{imageIndex}', [ProductController::class, 'deleteImage']);
                Route::post('/{product}/set-primary-image/{imageIndex}', [ProductController::class, 'setPrimaryImage']);
                Route::post('/{id}/apply-discount', [ProductController::class, 'applyProductDiscount']);
                Route::post('/{id}/remove-discount', [ProductController::class, 'removeDiscount']);
                Route::get('/{id}/discounts', [ProductController::class, 'productDiscounts']);
                Route::get('/reviews', [ProductReviewController::class, 'sellerReviews']);
            });

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

            // Seller coupon management (buyer-entered codes at checkout)
            Route::prefix('coupons')->group(function () {
                Route::get('/', [CouponController::class, 'index']);
                Route::post('/', [CouponController::class, 'store']);
                Route::get('/{coupon}', [CouponController::class, 'show']);
                Route::put('/{coupon}', [CouponController::class, 'update']);
                Route::delete('/{coupon}', [CouponController::class, 'destroy']);
                Route::patch('/{coupon}/toggle-status', [CouponController::class, 'toggleStatus']);
            });

            //Delivery management
            Route::prefix('delivery')->group(function () {
                // Order delivery method selection
                Route::post('/{order}/delivery-method', [DeliveryController::class, 'chooseDeliveryMethod']);
            });

            // Delivery Areas
            Route::prefix('delivery-areas')->group(function () {
                Route::get('/', [DeliveryAreaController::class, 'index']);
                Route::post('/', [DeliveryAreaController::class, 'store']);
                Route::post('/sync', [DeliveryAreaController::class, 'sync']);
                Route::get('/location-options', [DeliveryAreaController::class, 'getLocationOptions']);
                Route::post('/check-shipping-fee', [DeliveryAreaController::class, 'checkShippingFee']);
                Route::put('/{id}', [DeliveryAreaController::class, 'update']);
                Route::delete('/{id}', [DeliveryAreaController::class, 'destroy']);
            });

            // Shipping settings (separate from general settings)
            Route::prefix('shipping')->group(function () {
                Route::get('/settings', [SellerController::class, 'getShippingSettings']);
                Route::put('/settings', [SellerController::class, 'updateShippingSettings']);
                Route::post('/calculate', [SellerController::class, 'calculateShipping']);
            });

            // Settings routes
            Route::prefix('settings')->group(function () {
                Route::get('/', [SellerController::class, 'getSettings']);
                Route::put('/', [SellerController::class, 'updateSettings']);
                Route::get('/store-stats', [SellerController::class, 'getStoreStats']);
            });

            // Business hours
            Route::put('/business-hours', [SellerController::class, 'updateBusinessHours']);

            // Store policies
            Route::put('/policies', [SellerController::class, 'updatePolicies']);
        });

        Route::prefix('buyer')->middleware('role:buyer')->group(function () {

            // ✅ Product reviews – buyers can submit
            Route::prefix('reviews')->group(function () {
                Route::prefix('product')->group(function () {
                    Route::post('/{product}', [ProductReviewController::class, 'store']);
                    Route::put('/{review}', [ProductReviewController::class, 'update']);
                    Route::delete('/{review}', [ProductReviewController::class, 'destroy']);
                });
            });

            // ✅ Seller reviews – buyers can submit
            Route::prefix('seller-reviews')->group(function () {
                Route::post('/{seller}', [SellerReviewController::class, 'store']);
                Route::put('/{review}', [SellerReviewController::class, 'update']);
                Route::delete('/{review}', [SellerReviewController::class, 'destroy']);
            });

            // ✅ Wishlist management
            Route::prefix('wishlist')->group(function () {
                Route::get('/', [WishlistController::class, 'index']);
                Route::post('/', [WishlistController::class, 'store']);
                Route::get('/count', [WishlistController::class, 'count']);
                Route::get('/check/{productId}', [WishlistController::class, 'check']);
                Route::delete('/{productId}', [WishlistController::class, 'destroy']);
            });

            // Buyer Cart Management
            Route::prefix('cart')->group(function () {
                Route::get('/', [CartController::class, 'index']);
                Route::post('/', [CartController::class, 'store']);
                Route::put('/{id}', [CartController::class, 'update']);
                Route::delete('/{id}', [CartController::class, 'destroy']);
                Route::post('/clear', [CartController::class, 'clear']);
                Route::get('/count', [CartController::class, 'count']);
            });

            // Buyer coupon validation at checkout
            Route::prefix('coupons')->group(function () {
                Route::post('/validate', [CouponController::class, 'validate']);
            });
        });

        // Notification preferences (all auth users)
        Route::put('/notification-preferences', [UserController::class, 'updateNotificationPreferences']);

        // Business Types
        Route::group(['prefix' => 'business-types'], function () {
            Route::get('/', [BusinessTypeController::class, 'index']);
            Route::get('/{slug}', [BusinessTypeController::class, 'show']);
        });

        // Delivery Management
        Route::prefix('deliveries')->group(function () {
            Route::get('/', [DeliveryController::class, 'index']);
            Route::get('/{delivery}/tracking', [DeliveryController::class, 'getTrackingUpdates']);
            Route::post('/{delivery}/status', [DeliveryController::class, 'updateStatus']);
            Route::post('/{delivery}/proof', [DeliveryController::class, 'uploadDeliveryProof']);
            Route::post('/{delivery}/assign-courier', [DeliveryController::class, 'assignCourier']);
        });

        // ✅ SELLER MANAGEMENT ROUTES (Admin + Seller)
        Route::prefix('sellers')->group(function () {

            // Admin only routes
            Route::middleware('role:admin|seller')->group(function () {
                Route::put('/{seller}', [SellerController::class, 'update']);
                Route::delete('/{seller}', [SellerController::class, 'destroy']);
            });

            // Seller reviews (buyers can review sellers)
            Route::post('/{seller:store_slug}/reviews', [SellerReviewController::class, 'store'])->middleware('role:buyer|admin');
            Route::get('/my-reviews', [SellerReviewController::class, 'myReviews'])->middleware('role:buyer|admin');
            Route::put('/{review}', [SellerReviewController::class, 'update'])->middleware('role:buyer|admin');
            Route::delete('/{review}', [SellerReviewController::class, 'destroy'])->middleware('role:buyer|admin');
        });

        // Users
        Route::prefix('users')->group(function () {
            Route::get('/', [UserController::class, 'index'])->middleware('role:admin');

            // ── Static routes must come before /{user} wildcard ──────────────
            // Profile (authenticated user — no {user} param needed)
            Route::put('/newsletter/preferences', [NewsletterController::class, 'updatePreferences']);

            Route::prefix('profile')->group(function () {
                Route::get('/', [UserController::class, 'showProfile']);
                Route::put('/', [UserController::class, 'updateProfile']);
                Route::put('/password', [UserController::class, 'changePassword']);
                Route::post('/photo', [UserController::class, 'uploadProfilePhoto']);
                Route::delete('/photo', [UserController::class, 'deleteProfilePhoto']);
                Route::post('/identity', [UserController::class, 'uploadIdentityDocument']);
            });

            // Roles list
            Route::get('/roles/list', [UserController::class, 'getRoles'])->middleware('role:admin');

            // ── Wildcard routes last ──────────────────────────────────────────
            Route::get('/{user}', [UserController::class, 'show']);
            Route::put('/{user}', [UserController::class, 'update']);
            Route::delete('/{user}', [UserController::class, 'destroy'])->middleware('role:admin');
            Route::post('/{user}/assign-roles', [UserController::class, 'assignRoles'])->middleware('role:admin');
        });

        // Reviews
        Route::prefix('reviews')->group(function () {
            Route::get('/my-reviews', [ProductReviewController::class, 'myReviews']);

            Route::middleware('role:admin')->group(function () {
                Route::post('/{review}/approve', [ProductReviewController::class, 'approve']);
                Route::post('/{review}/reject', [ProductReviewController::class, 'reject']);
                Route::put('/{review}/status', [ProductReviewController::class, 'updateStatus']);
                Route::delete('/{review}', [ProductReviewController::class, 'destroy']);
            });
        });

        // Categories
        Route::prefix('categories')->group(function () {
            Route::post('/', [CategoryController::class, 'store'])->middleware('role:admin');
            Route::put('/{id}', [CategoryController::class, 'update'])->middleware('role:admin');
            Route::delete('/{category}', [CategoryController::class, 'destroy'])->middleware('role:admin');
        });

        // Orders
        Route::prefix('orders')->group(function () {
            Route::get('/', [OrderController::class, 'index']);
            // Static paths MUST come BEFORE /{order} wildcard
            Route::get('/checkout-fees', [OrderController::class, 'checkoutFees'])->middleware('role:buyer|admin');
            Route::post('/request-otp', [OrderController::class, 'requestOtp'])->middleware('role:buyer|admin');
            Route::post('/verify-otp',  [OrderController::class, 'verifyOtp'])->middleware('role:buyer|admin');
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
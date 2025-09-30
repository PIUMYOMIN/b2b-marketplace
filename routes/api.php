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
use App\Http\Controllers\Api\CartController;

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

    // Seller Routes
    Route::prefix('sellers')->group(function () {
        Route::get('/', [SellerController::class, 'indexPublic']);
        Route::get('/{identifier}', [SellerController::class, 'showPublic'])->where('identifier', '.*');
        Route::get('/{identifier}/products', [SellerController::class, 'sellerProducts'])->where('identifier', '.*');
        Route::get('/{identifier}/reviews', [SellerController::class, 'sellerReviews'])->where('identifier', '.*');
});

    // Products
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

        // Dashboard
        Route::prefix('dashboard')->group(function () {
            Route::get('/', [DashboardController::class, 'index']);
            Route::get('/sales-summary', [DashboardController::class, 'salesSummary']);
            Route::get('/top-products', [DashboardController::class, 'topProducts']);
            Route::get('/recent-orders', [DashboardController::class, 'recentOrders']);

            //Admin/Seller user management
            Route::prefix('sellers')->group(function () {
            Route::get('/', [SellerController::class, 'adminIndex'])->middleware('role:admin');
            Route::post('/{seller}/approve', [SellerController::class, 'adminApprove'])->middleware('role:admin');
            Route::post('/{seller}/reject', [SellerController::class, 'adminReject'])->middleware('role:admin');
            });
            // Admin/Seller product management
            Route::get('/products', [ProductController::class, 'index'])->middleware('role:admin|seller');

            //Admin/Seller review management
            Route::get('/reviews', [ReviewController::class, 'index'])->middleware('role:admin|seller');

            Route::get('/seller-sales-summary', [DashboardController::class, 'sellerSalesSummary'])->middleware('role:seller');
            Route::get('/seller-top-products', [DashboardController::class, 'sellerTopProducts'])->middleware('role:seller');
            Route::get('/seller-recent-orders', [DashboardController::class, 'sellerRecentOrders'])->middleware('role:seller');


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

        // Add to your authenticated routes section
        Route::prefix('cart')->middleware('role:buyer')->group(function () {
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

        // Sellers
        Route::prefix('sellers')->group(function () {
            Route::get('/my-store', [SellerController::class, 'myStore'])->middleware('role:seller');
            Route::post('/', [SellerController::class, 'store'])->middleware('role:seller|admin');
            Route::put('/{seller}', [SellerController::class, 'update'])->middleware('role:seller|admin');
            Route::delete('/{seller}', [SellerController::class, 'destroy'])->middleware('role:admin');

            //Seller profile reviews
            Route::post('/{seller}/reviews', [SellerReviewController::class, 'store'])->middleware('role:buyer|admin');
            Route::get('/my-reviews', [SellerReviewController::class, 'myReviews'])->middleware('role:buyer|admin');
            Route::put('/{review}', [SellerReviewController::class, 'update'])->middleware('role:buyer|admin');
            Route::delete('/{review}', [SellerReviewController::class, 'destroy'])->middleware('role:buyer|admin');
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

            Route::middleware('role:seller|admin')->group(function () {
                Route::post('/{order}/confirm', [OrderController::class, 'confirm']);
                Route::post('/{order}/ship', [OrderController::class, 'ship']);
            });

            Route::middleware('role:buyer|admin')->group(function () {
                Route::post('/{order}/confirm-delivery', [OrderController::class, 'confirmDelivery']);
            });
        });

        // Wishlist
        Route::prefix('wishlist')->middleware('role:buyer|admin')->group(function () {
            Route::get('/', [WishlistController::class, 'index']);
            Route::post('/add/{product}', [WishlistController::class, 'add']);
            Route::delete('/remove/{product}', [WishlistController::class, 'remove']);
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
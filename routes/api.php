<?php

use App\Http\Controllers\Api\V1\Admin\AuditLogController;
use App\Http\Controllers\Api\V1\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Api\V1\Admin\DashboardController;
use App\Http\Controllers\Api\V1\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Api\V1\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Api\V1\Admin\PromotionController;
use App\Http\Controllers\Api\V1\Admin\ReportController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\V1\Auth\ResetPasswordController;
use App\Http\Controllers\Api\V1\Cart\CartController;
use App\Http\Controllers\Api\V1\Catalog\CategoryController;
use App\Http\Controllers\Api\V1\Catalog\ProductController;
use App\Http\Controllers\Api\V1\Catalog\PromotionController as CatalogPromotionController;
use App\Http\Controllers\Api\V1\Checkout\CheckoutController;
use App\Http\Controllers\Api\V1\Checkout\PaymentOptionsController;
use App\Http\Controllers\Api\V1\Checkout\PromotionValidationController;
use App\Http\Controllers\Api\V1\Favorite\FavoriteController;
use App\Http\Controllers\Api\V1\Media\ImageUploadController;
use App\Http\Controllers\Api\V1\Notification\NotificationController;
use App\Http\Controllers\Api\V1\Order\OrderController;
use App\Http\Controllers\Api\V1\Order\PaymentReceiptController;
use App\Http\Controllers\Api\V1\Rating\RatingController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {

    /*
    |----------------------------------------------------------------------
    | Public
    |----------------------------------------------------------------------
    */
    Route::post('auth/register', [AuthController::class, 'register'])->middleware('throttle:register');
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('auth/forgot-password', [ForgotPasswordController::class, 'sendResetLink'])
        ->middleware('throttle:forgot-password');
    Route::post('auth/reset-password', [ResetPasswordController::class, 'reset']);

    Route::get('categories', [CategoryController::class, 'index']);
    Route::get('categories/{category}', [CategoryController::class, 'show']);

    Route::get('products', [ProductController::class, 'index']);
    Route::get('products/{product}', [ProductController::class, 'show']);
    Route::get('products/{product}/reviews', [RatingController::class, 'index']);
    Route::get('promotions', [CatalogPromotionController::class, 'index']);
    Route::get('payment-options', PaymentOptionsController::class);

    /*
    |----------------------------------------------------------------------
    | Authenticated
    |----------------------------------------------------------------------
    */
    Route::middleware('auth:sanctum')->group(function (): void {

        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::patch('auth/profile', [AuthController::class, 'updateProfile']);
        Route::post('media/images', ImageUploadController::class);

        Route::get('cart', [CartController::class, 'index']);
        Route::post('cart', [CartController::class, 'store']);
        Route::patch('cart/{cart}', [CartController::class, 'update']);
        Route::delete('cart/{cart}', [CartController::class, 'destroy']);
        Route::post('cart/apply-promotion', [CartController::class, 'applyPromotion']);

        Route::post('checkout', [CheckoutController::class, 'store'])->middleware('throttle:10,1');
        Route::post('promotions/validate', [PromotionValidationController::class, 'validate']);
        Route::post('orders/{order}/payment-receipt', [PaymentReceiptController::class, 'store']);

        Route::get('orders', [OrderController::class, 'index']);
        Route::get('orders/{order}', [OrderController::class, 'show']);
        Route::post('orders/{order}/cancel', [OrderController::class, 'cancel']);

        Route::get('favorites', [FavoriteController::class, 'index']);
        Route::post('favorites', [FavoriteController::class, 'store']);
        Route::delete('favorites/{product}', [FavoriteController::class, 'destroy']);

        Route::post('products/{product}/rating', [RatingController::class, 'store'])
            ->middleware('throttle:10,1');

        Route::get('notifications', [NotificationController::class, 'index']);
        Route::patch('notifications/read-all', [NotificationController::class, 'readAll']);
        Route::patch('notifications/{notification}/read', [NotificationController::class, 'markAsRead']);

        /*
        |------------------------------------------------------------------
        | Admin (BR-09: admin role only)
        |------------------------------------------------------------------
        */
        Route::prefix('admin')->name('admin.')->middleware('admin')->group(function (): void {
            Route::get('dashboard-stats', [DashboardController::class, 'index']);
            Route::get('reports/sales', [ReportController::class, 'sales']);

            Route::get('categories', [AdminCategoryController::class, 'index']);
            Route::post('categories', [AdminCategoryController::class, 'store']);
            Route::get('categories/{category}', [AdminCategoryController::class, 'show']);
            Route::put('categories/{category}', [AdminCategoryController::class, 'update']);
            Route::delete('categories/{category}', [AdminCategoryController::class, 'destroy']);

            Route::get('products', [AdminProductController::class, 'index']);
            Route::post('products', [AdminProductController::class, 'store']);
            Route::get('products/{product}', [AdminProductController::class, 'show']);
            Route::put('products/{product}', [AdminProductController::class, 'update']);
            Route::delete('products/{product}', [AdminProductController::class, 'destroy']);
            Route::post('products/{product}/stock', [AdminProductController::class, 'adjustStock']);

            Route::get('promotions', [PromotionController::class, 'adminIndex']);
            Route::post('promotions', [PromotionController::class, 'store']);
            Route::put('promotions/{promotion}', [PromotionController::class, 'update']);
            Route::delete('promotions/{promotion}', [PromotionController::class, 'destroy']);

            Route::get('orders', [AdminOrderController::class, 'index']);
            Route::get('orders/{order}', [AdminOrderController::class, 'show']);
            Route::get('orders/{order}/payment-receipt', [PaymentReceiptController::class, 'show']);
            Route::patch('orders/{order}/status', [AdminOrderController::class, 'updateStatus']);
            Route::patch('orders/{order}/payment', [AdminOrderController::class, 'updatePayment']);

            Route::get('audit-logs', [AuditLogController::class, 'index']);
        });
    });
});

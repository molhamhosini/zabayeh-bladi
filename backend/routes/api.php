<?php

use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\StripeWebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('auth/otp/request', [AuthController::class, 'requestOtp'])->middleware('throttle:5,10');
    Route::post('auth/otp/verify', [AuthController::class, 'verifyOtp'])->middleware('throttle:10,10');

    Route::get('emirates', [LocationController::class, 'emirates']);
    Route::get('location/resolve', [LocationController::class, 'resolve']);

    Route::get('home', [CatalogController::class, 'home']);
    Route::get('categories', [CatalogController::class, 'categories']);
    Route::get('products', [CatalogController::class, 'products']);
    Route::get('products/{product}', [CatalogController::class, 'product']);
    Route::get('branches', [CatalogController::class, 'branches']);
    Route::get('branches/{branch}/slots', [CatalogController::class, 'slots']);

    Route::post('webhooks/stripe', StripeWebhookController::class);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::patch('me', [AuthController::class, 'updateMe']);
        Route::post('auth/logout', [AuthController::class, 'logout']);

        Route::apiResource('addresses', AddressController::class)->except('show');

        Route::get('orders', [OrderController::class, 'index']);
        Route::post('orders', [OrderController::class, 'store']);
        Route::get('orders/{order}', [OrderController::class, 'show']);
        Route::post('orders/{order}/pay', [OrderController::class, 'pay']);
        Route::post('orders/{order}/cancel', [OrderController::class, 'cancel']);
    });
});

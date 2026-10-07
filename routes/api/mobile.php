<?php

use App\Http\Controllers\Api\Auth\GoogleAuthController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\OutletProfileController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\RegionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Mobile API (v1)
|--------------------------------------------------------------------------
|
| REST endpoints consumed by the Android customer application. All responses
| use the {"success": bool, ...} envelope; protected routes require a Sanctum
| bearer token issued by the Google sign-in endpoint.
|
*/

Route::prefix('v1')->group(function (): void {
    Route::post('auth/google', [GoogleAuthController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('api.v1.auth.google');

    Route::get('products', [ProductController::class, 'index'])
        ->name('api.v1.products.index');

    Route::prefix('regions')->name('api.v1.regions.')->group(function (): void {
        Route::get('provinces', [RegionController::class, 'provinces'])->name('provinces');
        Route::get('cities', [RegionController::class, 'cities'])->name('cities');
        Route::get('districts', [RegionController::class, 'districts'])->name('districts');
        Route::get('villages', [RegionController::class, 'villages'])->name('villages');
    });

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('profile', [OutletProfileController::class, 'show'])
            ->name('api.v1.profile.show');

        Route::put('profile', [OutletProfileController::class, 'update'])
            ->name('api.v1.profile.update');

        Route::post('orders', [OrderController::class, 'store'])
            ->middleware('throttle:30,1')
            ->name('api.v1.orders.store');

        Route::get('orders/history', [OrderController::class, 'history'])
            ->name('api.v1.orders.history');

        Route::get('orders/{orderNo}', [OrderController::class, 'show'])
            ->name('api.v1.orders.show');

        Route::post('orders/{orderNo}/cancel', [OrderController::class, 'cancel'])
            ->middleware('throttle:30,1')
            ->name('api.v1.orders.cancel');
    });
});

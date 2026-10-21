<?php

use App\Http\Controllers\Api\Auth\GoogleAuthController;
use App\Http\Controllers\Api\Auth\PhoneAuthController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\OutletProfileController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\PromoController;
use App\Http\Controllers\Api\RegionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Mobile API (v1)
|--------------------------------------------------------------------------
|
| REST endpoints consumed by the Android customer application. All responses
| use the {"success": bool, ...} envelope; protected routes require a Sanctum
| bearer token issued by the phone + password login endpoint.
|
*/

Route::prefix('v1')->group(function (): void {
    Route::post('auth/login', [PhoneAuthController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('api.v1.auth.login');

    // Deprecated: returns a 410 migration hint instead of a confusing 404.
    Route::post('auth/google', [GoogleAuthController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('api.v1.auth.google');

    Route::get('products', [ProductController::class, 'index'])
        ->name('api.v1.products.index');

    Route::get('banners', [PromoController::class, 'banners'])
        ->name('api.v1.banners.index');

    Route::get('promo-banners/{filename}', function (string $filename) {
        $path = \Illuminate\Support\Facades\Storage::disk('public')->path('promo-banners/' . basename($filename));
        if (!file_exists($path)) {
            abort(404);
        }
        $mime = mime_content_type($path) ?: 'image/jpeg';
        $content = file_get_contents($path);
        return response($content, 200, [
            'Content-Type' => $mime,
            'Content-Length' => strlen($content),
            'Cache-Control' => 'public, max-age=86400',
        ]);
    })->name('api.v1.banners.image');

    Route::get('promos', [PromoController::class, 'index'])
        ->name('api.v1.promos.index');

    Route::post('promos/validate', [PromoController::class, 'validate'])
        ->middleware('throttle:60,1')
        ->name('api.v1.promos.validate');

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

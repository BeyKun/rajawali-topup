<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\KabupatenAdminController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\OutletUserController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\PromoBannerController;
use App\Http\Controllers\Admin\PromoController;
use App\Http\Controllers\Admin\PromoVoucherController;
use App\Http\Controllers\Admin\RegionController;
use App\Http\Controllers\Admin\TelkomselAreaController;
use App\Http\Controllers\Admin\VoucherController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return redirect()->route('login');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function (Request $request) {
        $user = $request->user();

        if ($user !== null && $user->isAdminDashboardUser()) {
            return to_route('admin.dashboard');
        }

        return Inertia::render('Dashboard');
    })->name('dashboard');
});

Route::middleware(['auth', 'verified', 'admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function (): void {
        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

        Route::get('products', [ProductController::class, 'index'])->name('products.index');
        Route::put('products/{product}', [ProductController::class, 'update'])->name('products.update');

        Route::get('vouchers', [VoucherController::class, 'index'])->name('vouchers.index');
        Route::get('vouchers/create', [VoucherController::class, 'create'])->name('vouchers.create');
        Route::post('vouchers/check', [VoucherController::class, 'check'])->name('vouchers.check');
        Route::post('vouchers', [VoucherController::class, 'store'])->name('vouchers.store');
        Route::put('vouchers/{voucher}', [VoucherController::class, 'update'])->name('vouchers.update');
        Route::delete('vouchers/{voucher}', [VoucherController::class, 'destroy'])->name('vouchers.destroy');
        Route::get('vouchers/bulk', [VoucherController::class, 'bulk'])->name('vouchers.bulk');
        Route::post('vouchers/bulk', [VoucherController::class, 'bulkStore'])->name('vouchers.bulk.store');

        Route::get('outlets', [OutletUserController::class, 'index'])->name('outlets.index');
        Route::post('outlets', [OutletUserController::class, 'store'])->name('outlets.store');
        Route::put('outlets/{user}', [OutletUserController::class, 'update'])->name('outlets.update');
        Route::post('outlets/{user}/reset-password', [OutletUserController::class, 'resetPassword'])->name('outlets.reset-password');

        Route::get('promos', [PromoController::class, 'index'])->name('promos.index');

        Route::prefix('promos/banners')->name('promos.banners.')->group(function (): void {
            Route::post('/', [PromoBannerController::class, 'store'])->name('store');
            Route::post('{banner}', [PromoBannerController::class, 'update'])->name('update');
            Route::delete('{banner}', [PromoBannerController::class, 'destroy'])->name('destroy');
        });
        Route::prefix('promos/vouchers')->name('promos.vouchers.')->group(function (): void {
            Route::post('/', [PromoVoucherController::class, 'store'])->name('store');
            Route::put('{voucher}', [PromoVoucherController::class, 'update'])->name('update');
            Route::delete('{voucher}', [PromoVoucherController::class, 'destroy'])->name('destroy');
        });

        Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
        Route::post('orders/{order}/retry-redeem', [OrderController::class, 'retryRedeem'])->name('orders.retry-redeem');
        Route::post('orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
        Route::post('orders/{order}/mark-refunded', [OrderController::class, 'markRefunded'])->name('orders.mark-refunded');

        Route::prefix('api/regions')->name('regions.')->group(function (): void {
            Route::get('provinces', [RegionController::class, 'provinces'])->name('provinces');
            Route::get('cities', [RegionController::class, 'cities'])->name('cities');
            Route::get('districts', [RegionController::class, 'districts'])->name('districts');
            Route::get('villages', [RegionController::class, 'villages'])->name('villages');
        });

        Route::middleware('super_admin')->group(function (): void {
            Route::get('admins', [KabupatenAdminController::class, 'index'])->name('admins.index');
            Route::post('admins', [KabupatenAdminController::class, 'store'])->name('admins.store');
            Route::put('admins/{admin}', [KabupatenAdminController::class, 'update'])->name('admins.update');
            Route::delete('admins/{admin}', [KabupatenAdminController::class, 'destroy'])->name('admins.destroy');

            Route::get('areas', [TelkomselAreaController::class, 'index'])->name('areas.index');
            Route::post('areas', [TelkomselAreaController::class, 'store'])->name('areas.store');
            Route::put('areas/{area}', [TelkomselAreaController::class, 'update'])->name('areas.update');
            Route::delete('areas/{area}', [TelkomselAreaController::class, 'destroy'])->name('areas.destroy');
        });
    });

require __DIR__.'/settings.php';

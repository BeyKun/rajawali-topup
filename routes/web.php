<?php

use App\Enums\UserRole;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\ProductController;
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

        if ($user !== null && in_array($user->role, [UserRole::SuperAdmin, UserRole::Operator], true)) {
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
        Route::delete('vouchers/{voucher}', [VoucherController::class, 'destroy'])->name('vouchers.destroy');
        Route::get('vouchers/bulk', [VoucherController::class, 'bulk'])->name('vouchers.bulk');
        Route::post('vouchers/bulk', [VoucherController::class, 'bulkStore'])->name('vouchers.bulk.store');

        Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
        Route::post('orders/{order}/retry-redeem', [OrderController::class, 'retryRedeem'])->name('orders.retry-redeem');
        Route::post('orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
        Route::post('orders/{order}/mark-refunded', [OrderController::class, 'markRefunded'])->name('orders.mark-refunded');
    });

require __DIR__.'/settings.php';

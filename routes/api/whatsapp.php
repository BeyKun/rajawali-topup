<?php

use App\Http\Controllers\Api\WhatsApp\ChannelController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| WhatsApp Channel API (v1)
|--------------------------------------------------------------------------
|
| Consumed by the whatsapp-bot service, not by end users. Every route is
| protected by the `channel.token` middleware which checks a static service
| token, because the bot acts on behalf of many customers at once.
|
*/

Route::prefix('v1/wa')
    ->middleware('channel.token')
    ->group(function (): void {
        Route::post('customers/resolve', [ChannelController::class, 'resolveCustomer'])
            ->name('api.v1.wa.customers.resolve');

        Route::get('products', [ChannelController::class, 'products'])
            ->name('api.v1.wa.products.index');

        Route::post('orders', [ChannelController::class, 'storeOrder'])
            ->middleware('throttle:30,1')
            ->name('api.v1.wa.orders.store');

        Route::get('orders/history', [ChannelController::class, 'history'])
            ->name('api.v1.wa.orders.history');

        Route::get('orders/{orderNo}', [ChannelController::class, 'showOrder'])
            ->name('api.v1.wa.orders.show');

        Route::post('orders/{orderNo}/cancel', [ChannelController::class, 'cancelOrder'])
            ->middleware('throttle:30,1')
            ->name('api.v1.wa.orders.cancel');
    });

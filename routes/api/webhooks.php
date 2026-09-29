<?php

use App\Http\Controllers\Api\WebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Payment Gateway Webhooks
|--------------------------------------------------------------------------
|
| These endpoints are called server-to-server by the QRIS payment gateway.
| They cannot carry a Sanctum token, so authenticity is enforced through the
| payload signature verified inside the controller.
|
*/

Route::post('v1/webhooks/qris', [WebhookController::class, 'qris'])->name('api.webhooks.qris');
Route::post('v1/webhooks/midtrans', [WebhookController::class, 'qris'])->name('api.webhooks.midtrans');

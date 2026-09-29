<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Payment Provider
    |--------------------------------------------------------------------------
    |
    | Penyedia gateway pembayaran QRIS. Gunakan "mock" saat pengembangan
    | lokal sebelum mengganti ke provider asli (midtrans, xendit, tripay).
    |
    */

    'provider' => env('PAYMENT_PROVIDER', 'mock'),

    /*
    |--------------------------------------------------------------------------
    | Webhook Signature Secret
    |--------------------------------------------------------------------------
    |
    | Shared secret used to validate the HMAC-SHA256 signature every real
    | provider sends on its payment callback. Ignored while the "mock"
    | provider is active.
    |
    */

    'webhook_secret' => env('PAYMENT_WEBHOOK_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Mock Provider
    |--------------------------------------------------------------------------
    |
    | Deterministic values used by the mock QRIS provider during local
    | development and automated testing.
    |
    */

    'mock' => [
        'merchant_name' => env('PAYMENT_MOCK_MERCHANT_NAME', 'RAJAWALI TOPUP'),
        'merchant_city' => env('PAYMENT_MOCK_MERCHANT_CITY', 'JAKARTA'),
        'qris_base_url' => env('PAYMENT_MOCK_QRIS_BASE_URL', 'https://api.rajawalitopup.com/qris/render'),
    ],

    'midtrans' => [
        /*
        | Midtrans environment. `true` targets the production API, `false`
        | targets the sandbox API (https://api.sandbox.midtrans.com).
        */
        'is_production' => env('MIDTRANS_IS_PRODUCTION', false),

        /** Server key (used for Basic-auth on every API request). */
        'server_key' => env('MIDTRANS_SERVER_KEY'),

        /** Client key (reserved for future Snap/frontend usage). */
        'client_key' => env('MIDTRANS_CLIENT_KEY'),

        /** Merchant id shown in the Midtrans dashboard. */
        'merchant_id' => env('MIDTRANS_MERCHANT_ID'),

        /** Midtrans payment channel used for the topup orders. */
        'payment_type' => env('MIDTRANS_PAYMENT_TYPE', 'qris'),

        /** Acquiring bank for the QRIS charge (optional, e.g. "gopay", "bca"). */
        'acquirer' => env('MIDTRANS_ACQUIRER', 'gopay'),

        /**
         * Enable Midtrans 3-D Secure / fraud challenge notifications in logs.
         * Not directly used for QRIS but kept for diagnosability.
         */
        'sanitize' => env('MIDTRANS_SANITIZE', true),

        /** Seconds before an unreachable Midtrans request is aborted. */
        'timeout' => (int) env('MIDTRANS_TIMEOUT', 30),
    ],

    'xendit' => [
        'api_key' => env('XENDIT_API_KEY'),
        'callback_token' => env('XENDIT_CALLBACK_TOKEN'),
    ],

    'tripay' => [
        'api_key' => env('TRIPAY_API_KEY'),
        'private_key' => env('TRIPAY_PRIVATE_KEY'),
        'merchant_code' => env('TRIPAY_MERCHANT_CODE'),
    ],

];

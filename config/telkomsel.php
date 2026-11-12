<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Telkomsel Voucher API
    |--------------------------------------------------------------------------
    |
    | Konfigurasi untuk integrasi Telkomsel Voucher API (check & redeem).
    | Saat pengembangan lokal, aktifkan mode mock agar tidak membakar
    | voucher fisik asli.
    |
    */

    'base_url' => env('TELKOMSEL_BASE_URL', 'https://www.telkomsel.com/api/voucher'),

    'mock' => (bool) env('TELKOMSEL_MOCK', true),

    'timeout' => (int) env('TELKOMSEL_TIMEOUT', 15),

    /*
    |--------------------------------------------------------------------------
    | Anti-bot gateway (optional)
    |--------------------------------------------------------------------------
    |
    | When `gateway_url` is filled in, every check/redeem call is routed through
    | the `telkomsel-gateway` Python service instead of hitting Telkomsel
    | directly. That service enforces the three-requests-per-minute quota and
    | solves reCAPTCHA on demand. Leave empty to keep the direct HTTP client.
    |
    */

    'gateway_url' => env('TELKOMSEL_GATEWAY_URL'),

    'gateway_token' => env('TELKOMSEL_GATEWAY_TOKEN'),

    'gateway_timeout' => (int) env('TELKOMSEL_GATEWAY_TIMEOUT', 240),

];

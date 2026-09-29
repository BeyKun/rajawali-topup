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

];

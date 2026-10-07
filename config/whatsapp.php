<?php

return [

    /*
    |--------------------------------------------------------------------------
    | WhatsApp Channel
    |--------------------------------------------------------------------------
    |
    | Konfigurasi kanal WhatsApp (whatsapp-bot). Backend tetap menjadi sumber
    | kebenaran data; service bot memanggil Internal Channel API dan menerima
    | callback status order dari backend.
    |
    */

    'enabled' => (bool) env('WA_CHANNEL_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Service Token
    |--------------------------------------------------------------------------
    |
    | Token statis yang dipakai service bot untuk mengakses endpoint
    | /api/v1/wa/*. Dibandingkan secara constant-time oleh middleware.
    |
    */

    'channel_token' => env('WA_CHANNEL_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Bot Callback
    |--------------------------------------------------------------------------
    |
    | Alamat base service bot dan token internal yang dipakai backend untuk
    | mengirim notifikasi perubahan status order ke bot.
    |
    */

    'bot_base_url' => env('WA_BOT_BASE_URL', 'http://whatsapp-bot:3001'),

    'bot_token' => env('WA_BOT_INTERNAL_TOKEN'),

    'timeout' => (int) env('WA_BOT_TIMEOUT', 10),

];

<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Voucher Margin Percentage
    |--------------------------------------------------------------------------
    |
    | The default markup percentage automatically added to voucher base prices
    | (e.g. 2.0 = 2% profit margin). Can be overridden via .env.
    |
    */
    'default_margin' => (float) env('VOUCHER_DEFAULT_MARGIN_PERCENTAGE', 2.0),
];

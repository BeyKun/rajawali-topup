<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Outlet Default Password
    |--------------------------------------------------------------------------
    |
    | Password awal yang diberikan admin saat membuat user outlet baru. Admin
    | dapat mengganti nilai ini per-akun, dan fitur "Reset Password" memakai
    | nilai ini ketika admin tidak mengisi password baru secara eksplisit.
    |
    */

    'default_password' => env('OUTLET_DEFAULT_PASSWORD', 'rajawali123'),

];

<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * @deprecated Google sign-in has been replaced by phone + password login
 * ({@see PhoneAuthController}). The endpoint is kept only to return a clear
 * migration hint to older app versions instead of a confusing 404.
 */
class GoogleAuthController extends Controller
{
    /**
     * Reject Google sign-in and point the client at the phone login endpoint.
     */
    public function store(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Login dengan Google sudah tidak digunakan. Silakan masuk menggunakan Nomor HP dan password.',
        ], 410);
    }
}

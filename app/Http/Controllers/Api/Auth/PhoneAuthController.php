<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PhoneLoginRequest;
use App\Http\Requests\Api\ResetPasswordRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

/**
 * Exchanges an outlet's phone number + password for a Sanctum API token,
 * or resets password using owner phone & outlet name verification.
 */
class PhoneAuthController extends Controller
{
    /**
     * Authenticate an outlet user with their normalized phone number.
     */
    public function store(PhoneLoginRequest $request): JsonResponse
    {
        $phone = Phone::normalizeTo62((string) $request->validated('phone'));

        /** @var User|null $user */
        $user = User::query()->where('phone', $phone)->first();

        if ($user === null || $user->password === null || ! Hash::check((string) $request->validated('password'), $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Nomor HP atau password salah.',
            ], 401);
        }

        if (! $user->isActiveCustomer()) {
            return response()->json([
                'success' => false,
                'message' => 'Akun ini tidak memiliki akses ke aplikasi.',
            ], 403);
        }

        $user->loadMissing('outletProfile.province', 'outletProfile.city', 'outletProfile.district', 'outletProfile.village');

        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json([
            'success' => true,
            'token' => $token,
            'user' => (new UserResource($user))->resolve($request),
        ]);
    }

    /**
     * Reset outlet password by matching phone number and outlet name.
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $phone = Phone::normalizeTo62((string) $request->validated('phone'));

        /** @var User|null $user */
        $user = User::query()
            ->with('outletProfile')
            ->where('phone', $phone)
            ->first();

        if ($user === null || ! $user->isActiveCustomer()) {
            return response()->json([
                'success' => false,
                'message' => 'Akun outlet dengan nomor HP tersebut tidak ditemukan atau tidak aktif.',
            ], 404);
        }

        $inputOutletName = strtolower(trim((string) $request->validated('outlet_name')));
        $actualOutletName = strtolower(trim((string) ($user->outletProfile?->outlet_name ?? $user->name)));

        // Verifikasi kesamaan nama outlet (bisa sebagian atau persis)
        if (! str_contains($actualOutletName, $inputOutletName) && ! str_contains($inputOutletName, $actualOutletName)) {
            return response()->json([
                'success' => false,
                'message' => 'Nama outlet tidak sesuai dengan data terdaftar.',
            ], 422);
        }

        $newPassword = (string) $request->validated('new_password');

        $user->forceFill([
            'password' => Hash::make($newPassword),
        ])->save();

        // Cabut token login lama demi keamanan
        $user->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Password berhasil direset. Silakan masuk menggunakan password baru Anda.',
        ]);
    }
}

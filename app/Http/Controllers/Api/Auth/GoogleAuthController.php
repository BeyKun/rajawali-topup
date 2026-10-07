<?php

namespace App\Http\Controllers\Api\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\GoogleAuthRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\GoogleAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Exchanges a verified Google ID token for a Sanctum API token.
 */
class GoogleAuthController extends Controller
{
    public function __construct(private readonly GoogleAuthService $googleAuth) {}

    /**
     * Authenticate (or register) a customer from a Google ID token.
     */
    public function store(GoogleAuthRequest $request): JsonResponse
    {
        $identity = $this->googleAuth->verify((string) $request->validated('id_token'));

        if ($identity === null) {
            return response()->json([
                'success' => false,
                'message' => 'Token Google tidak valid.',
            ], 401);
        }

        $user = DB::transaction(fn (): User => $this->findOrCreateUser($identity));

        $user->loadMissing('outletProfile.province', 'outletProfile.city', 'outletProfile.district', 'outletProfile.village');

        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json([
            'success' => true,
            'token' => $token,
            'user' => (new UserResource($user))->resolve($request),
        ]);
    }

    /**
     * Find an existing user by Google id / email or create a new customer.
     *
     * @param  array{google_id: string, email: string, name: string, avatar_url: string|null}  $identity
     */
    protected function findOrCreateUser(array $identity): User
    {
        $user = User::query()
            ->where('google_id', $identity['google_id'])
            ->orWhere('email', $identity['email'])
            ->first();

        if ($user === null) {
            $user = User::query()->create([
                'name' => $identity['name'],
                'email' => $identity['email'],
                'google_id' => $identity['google_id'],
                'avatar_url' => $identity['avatar_url'],
                'role' => UserRole::Customer,
            ]);

            $user->forceFill(['email_verified_at' => now()])->save();

            return $user;
        }

        $user->forceFill([
            'google_id' => $identity['google_id'],
            'avatar_url' => $identity['avatar_url'],
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        return $user;
    }
}

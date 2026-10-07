<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateOutletProfileRequest;
use App\Models\OutletProfile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Outlet profile endpoints for the mobile application.
 *
 * Every mobile login is treated as an outlet; the profile (outlet name,
 * WhatsApp number and full administrative address) is mandatory before the
 * customer can browse or order.
 */
class OutletProfileController extends Controller
{
    /**
     * Return the authenticated user's outlet profile, if any.
     */
    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => $this->present($user->outletProfile, $user),
        ]);
    }

    /**
     * Create or update the authenticated user's outlet profile.
     */
    public function update(UpdateOutletProfileRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $profile = OutletProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            $request->validated(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Profil outlet berhasil disimpan.',
            'data' => $this->present($profile, $user),
        ]);
    }

    /**
     * Shape an outlet profile (or lack thereof) for the mobile client.
     *
     * @return array<string, mixed>
     */
    protected function present(?OutletProfile $profile, User $user): array
    {
        if ($profile === null) {
            return [
                'profile_completed' => false,
                'outlet_name' => null,
                'whatsapp' => null,
                'province_id' => null,
                'province' => null,
                'city_id' => null,
                'city' => null,
                'district_id' => null,
                'district' => null,
                'village_id' => null,
                'village' => null,
                'address' => null,
            ];
        }

        $profile->loadMissing(['province', 'city', 'district', 'village']);

        return [
            'profile_completed' => true,
            'outlet_name' => $profile->outlet_name,
            'whatsapp' => $profile->whatsapp,
            'province_id' => $profile->province_id,
            'province' => $profile->province?->name,
            'city_id' => $profile->city_id,
            'city' => $profile->city?->name,
            'district_id' => $profile->district_id,
            'district' => $profile->district?->name,
            'village_id' => $profile->village_id,
            'village' => $profile->village?->name,
            'address' => $profile->address,
        ];
    }
}

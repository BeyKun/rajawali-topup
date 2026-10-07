<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $outlet = $this->outletProfile;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'avatar_url' => $this->avatar_url,
            'profile_completed' => $outlet !== null,
            'outlet_name' => $outlet?->outlet_name,
            'whatsapp' => $outlet?->whatsapp,
            'province' => $outlet?->province?->name,
            'city' => $outlet?->city?->name,
            'district' => $outlet?->district?->name,
            'village' => $outlet?->village?->name,
            'address' => $outlet?->address,
        ];
    }
}

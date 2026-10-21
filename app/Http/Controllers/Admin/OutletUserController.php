<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OutletPasswordResetRequest;
use App\Http\Requests\Admin\OutletUserRequest;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin management of outlet (mobile-app customer) accounts.
 *
 * Outlet users are provisioned by an admin with only a name and phone number;
 * the phone number (normalized to `628xxx`) is their login identifier, and a
 * default password is assigned which the admin can reset at any time.
 */
class OutletUserController extends Controller
{
    /**
     * List outlet users with optional name/phone/city filtering.
     */
    public function index(Request $request): Response
    {
        $filters = [
            'search' => $request->string('search')->toString() ?: null,
            'city_id' => $request->integer('city_id') ?: null,
        ];

        $outlets = User::query()
            ->where('role', UserRole::Customer)
            ->with(['outletProfile.city.province:id,name'])
            ->when($filters['search'], function (Builder $query, string $term): void {
                $query->where(function (Builder $inner) use ($term): void {
                    $inner->where('name', 'like', "%{$term}%")
                        ->orWhere('phone', 'like', "%{$term}%")
                        ->orWhereHas('outletProfile', fn (Builder $profile) => $profile->where('outlet_name', 'like', "%{$term}%"));
                });
            })
            ->when($filters['city_id'], function (Builder $query, int $cityId): void {
                $query->whereHas('outletProfile', fn (Builder $profile) => $profile->where('city_id', $cityId));
            })
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (User $user): array => $this->presentRow($user));

        return Inertia::render('admin/outlets/Index', [
            'outlets' => $outlets,
            'filters' => $filters,
            'defaultPassword' => (string) config('outlet.default_password'),
        ]);
    }

    /**
     * Create a new outlet user (account + profile) with a default password.
     */
    public function store(OutletUserRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated): void {
            $user = User::query()->create([
                'name' => $validated['name'],
                'phone' => $validated['phone'],
                'password' => Hash::make((string) config('outlet.default_password')),
                'role' => UserRole::Customer,
                'email_verified_at' => now(),
            ]);

            $user->outletProfile()->create($this->profileAttributes($validated));
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => sprintf('User outlet %s berhasil dibuat dengan password default.', $validated['outlet_name']),
        ]);

        return to_route('admin.outlets.index');
    }

    /**
     * Update an outlet user's account and profile details.
     */
    public function update(OutletUserRequest $request, User $user): RedirectResponse
    {
        abort_unless($user->role === UserRole::Customer, 404);

        $validated = $request->validated();

        DB::transaction(function () use ($user, $validated): void {
            $user->update([
                'name' => $validated['name'],
                'phone' => $validated['phone'],
            ]);

            $user->outletProfile()->updateOrCreate(
                ['user_id' => $user->id],
                $this->profileAttributes($validated),
            );
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Data outlet berhasil diperbarui.']);

        return to_route('admin.outlets.index');
    }

    /**
     * Reset an outlet user's password to a new value or the platform default.
     */
    public function resetPassword(OutletPasswordResetRequest $request, User $user): RedirectResponse
    {
        abort_unless($user->role === UserRole::Customer, 404);

        $newPassword = $request->string('password')->toString() ?: (string) config('outlet.default_password');

        $user->forceFill(['password' => Hash::make($newPassword)])->save();

        $user->tokens()->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => sprintf('Password %s berhasil direset. Password baru: %s', $user->name, $newPassword),
        ]);

        return to_route('admin.outlets.index');
    }

    /**
     * Shape an outlet user row for the admin table.
     *
     * @return array<string, mixed>
     */
    protected function presentRow(User $user): array
    {
        $profile = $user->outletProfile;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'phone' => $user->phone,
            'outlet_name' => $profile?->outlet_name,
            'whatsapp' => $profile?->whatsapp,
            'province_id' => $profile?->province_id,
            'province_name' => $profile?->city?->province?->name,
            'city_id' => $profile?->city_id,
            'city_name' => $profile?->city?->name,
            'district_id' => $profile?->district_id,
            'village_id' => $profile?->village_id,
            'address' => $profile?->address,
            'profile_completed' => $profile !== null,
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }

    /**
     * Extract the outlet profile payload from validated input.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function profileAttributes(array $validated): array
    {
        return [
            'outlet_name' => $validated['outlet_name'],
            'whatsapp' => $validated['whatsapp'] ?? $validated['phone'],
            'province_id' => $validated['province_id'],
            'city_id' => $validated['city_id'],
            'district_id' => $validated['district_id'],
            'village_id' => $validated['village_id'],
            'address' => $validated['address'],
        ];
    }
}

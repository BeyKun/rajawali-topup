<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Super-admin management of kabupaten admin accounts.
 *
 * A kabupaten admin is a dashboard user scoped to one or multiple provinces + cities;
 * the vouchers they input are automatically attributed to their cities, and the
 * dashboard queries they run are filtered to them.
 */
class KabupatenAdminController extends Controller
{
    /**
     * List every kabupaten admin account with its assigned regions.
     */
    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString() ?: null;

        $admins = User::query()
            ->where('role', UserRole::KabupatenAdmin)
            ->with(['city.province:id,name', 'cities.province:id,name'])
            ->when($search, function ($query, string $term): void {
                $query->where(function ($inner) use ($term): void {
                    $inner->where('name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%");
                });
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(function (User $admin): array {
                $managedCities = $admin->cities->isNotEmpty()
                    ? $admin->cities
                    : ($admin->city ? collect([$admin->city]) : collect());

                return [
                    'id' => $admin->id,
                    'name' => $admin->name,
                    'email' => $admin->email,
                    'province_id' => $admin->province_id,
                    'province_name' => $admin->city?->province?->name ?? $managedCities->first()?->province?->name,
                    'city_id' => $admin->city_id,
                    'city_name' => $admin->city?->name,
                    'city_ids' => $managedCities->pluck('id')->map(fn ($id) => (int) $id)->all(),
                    'city_names' => $managedCities->pluck('name')->join(', '),
                    'created_at' => $admin->created_at?->toIso8601String(),
                ];
            });

        $cities = City::query()
            ->with('province:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (City $c): array => [
                'id' => $c->id,
                'name' => $c->name,
                'province_name' => $c->province?->name,
            ])
            ->all();

        return Inertia::render('admin/admins/Index', [
            'admins' => $admins,
            'filters' => ['search' => $search],
            'cities' => $cities,
            'is_super_admin' => (bool) $request->user()?->isSuperAdmin(),
        ]);
    }

    /**
     * Create a new kabupaten admin account.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', Password::defaults()],
            'province_id' => ['nullable', 'integer', Rule::exists('provinces', 'id')],
            'city_ids' => ['nullable', 'array'],
            'city_ids.*' => ['integer', Rule::exists('cities', 'id')],
            'city_id' => ['nullable', 'integer', Rule::exists('cities', 'id')],
        ]);

        $cityIds = ! empty($validated['city_ids'])
            ? array_values(array_unique(array_map('intval', $validated['city_ids'])))
            : (! empty($validated['city_id']) ? [(int) $validated['city_id']] : []);

        if (empty($cityIds)) {
            throw ValidationException::withMessages([
                'city_ids' => 'Pilih minimal satu wilayah kabupaten/kota.',
            ]);
        }

        $primaryCity = City::query()->find($cityIds[0]);
        $provinceId = $validated['province_id'] ?? $primaryCity?->province_id;

        $admin = User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => UserRole::KabupatenAdmin,
            'province_id' => $provinceId,
            'city_id' => $cityIds[0],
            'email_verified_at' => now(),
        ]);

        $admin->cities()->sync($cityIds);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Admin wilayah berhasil dibuat.']);

        return back();
    }

    /**
     * Update a kabupaten admin account and its assigned regions.
     */
    public function update(Request $request, User $admin): RedirectResponse
    {
        abort_unless($admin->role === UserRole::KabupatenAdmin, 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($admin->id)],
            'password' => ['nullable', 'string', Password::defaults()],
            'province_id' => ['nullable', 'integer', Rule::exists('provinces', 'id')],
            'city_ids' => ['nullable', 'array'],
            'city_ids.*' => ['integer', Rule::exists('cities', 'id')],
            'city_id' => ['nullable', 'integer', Rule::exists('cities', 'id')],
        ]);

        $cityIds = ! empty($validated['city_ids'])
            ? array_values(array_unique(array_map('intval', $validated['city_ids'])))
            : (! empty($validated['city_id']) ? [(int) $validated['city_id']] : []);

        if (empty($cityIds)) {
            throw ValidationException::withMessages([
                'city_ids' => 'Pilih minimal satu wilayah kabupaten/kota.',
            ]);
        }

        $primaryCity = City::query()->find($cityIds[0]);
        $provinceId = $validated['province_id'] ?? $primaryCity?->province_id ?? $admin->province_id;

        $admin->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'province_id' => $provinceId,
            'city_id' => $cityIds[0],
        ]);

        if (! empty($validated['password'])) {
            $admin->password = Hash::make($validated['password']);
        }

        $admin->save();
        $admin->cities()->sync($cityIds);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Admin wilayah berhasil diperbarui.']);

        return back();
    }

    /**
     * Delete a kabupaten admin account.
     */
    public function destroy(User $admin): RedirectResponse
    {
        abort_unless($admin->role === UserRole::KabupatenAdmin, 404);

        $admin->cities()->detach();
        $admin->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Admin wilayah berhasil dihapus.']);

        return back();
    }
}

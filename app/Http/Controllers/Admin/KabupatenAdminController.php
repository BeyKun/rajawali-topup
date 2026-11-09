<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Mail\KabupatenAdminCredentialsMail;
use App\Models\City;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

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
                $city = $admin->city ?? $admin->cities->first();

                return [
                    'id' => $admin->id,
                    'name' => $admin->name,
                    'email' => $admin->email,
                    'province_id' => $admin->province_id ?? $city?->province_id,
                    'province_name' => $city?->province?->name,
                    'city_id' => $city?->id,
                    'city_name' => $city?->name,
                    'city_ids' => $city ? [(int) $city->id] : [],
                    'city_names' => $city?->name ?? '',
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
     * Create a new kabupaten admin account (1 admin = 1 kabupaten/kota).
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'province_id' => ['nullable', 'integer', Rule::exists('provinces', 'id')],
            'city_id' => ['nullable', 'integer', Rule::exists('cities', 'id')],
            'city_ids' => ['nullable', 'array', 'max:1'],
            'city_ids.*' => ['integer', Rule::exists('cities', 'id')],
        ], [
            'city_ids.max' => 'Satu admin wilayah hanya dapat mengelola satu kabupaten/kota.',
        ]);

        $cityId = ! empty($validated['city_id'])
            ? (int) $validated['city_id']
            : (! empty($validated['city_ids'][0]) ? (int) $validated['city_ids'][0] : null);

        if ($cityId === null) {
            throw ValidationException::withMessages([
                'city_id' => 'Pilih satu wilayah kabupaten/kota yang dikelola.',
            ]);
        }

        $primaryCity = City::query()->find($cityId);
        $provinceId = $validated['province_id'] ?? $primaryCity?->province_id;
        $defaultPassword = 'Rajawali'.now()->timestamp;

        DB::beginTransaction();

        try {
            $admin = User::query()->create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($defaultPassword),
                'role' => UserRole::KabupatenAdmin,
                'province_id' => $provinceId,
                'city_id' => $cityId,
                'email_verified_at' => now(),
            ]);

            $admin->cities()->sync([$cityId]);

            $cityName = $primaryCity?->name ?? 'Wilayah';
            $sent = Mail::to($admin->email)->send(
                new KabupatenAdminCredentialsMail($admin, $defaultPassword, $cityName)
            );

            if ($sent === null && ! app()->environment('testing')) {
                throw new \RuntimeException('Gagal mengirim email kredensial.');
            }

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();

            Log::error('Gagal membuat admin wilayah atau mengirim email kredensial: '.$e->getMessage(), [
                'email' => $validated['email'],
                'exception' => $e,
            ]);

            throw ValidationException::withMessages([
                'email' => 'Gagal mengirim email kredensial ke alamat tersebut. Pastikan alamat email benar dan aktif.',
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "Admin wilayah {$admin->name} berhasil dibuat dan password telah dikirim ke email."]);

        return back();
    }

    /**
     * Update a kabupaten admin account and its assigned single region.
     */
    public function update(Request $request, User $admin): RedirectResponse
    {
        abort_unless($admin->role === UserRole::KabupatenAdmin, 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($admin->id)],
            'province_id' => ['nullable', 'integer', Rule::exists('provinces', 'id')],
            'city_id' => ['nullable', 'integer', Rule::exists('cities', 'id')],
            'city_ids' => ['nullable', 'array', 'max:1'],
            'city_ids.*' => ['integer', Rule::exists('cities', 'id')],
        ], [
            'city_ids.max' => 'Satu admin wilayah hanya dapat mengelola satu kabupaten/kota.',
        ]);

        $cityId = ! empty($validated['city_id'])
            ? (int) $validated['city_id']
            : (! empty($validated['city_ids'][0]) ? (int) $validated['city_ids'][0] : null);

        if ($cityId === null) {
            throw ValidationException::withMessages([
                'city_id' => 'Pilih satu wilayah kabupaten/kota yang dikelola.',
            ]);
        }

        $primaryCity = City::query()->find($cityId);
        $provinceId = $validated['province_id'] ?? $primaryCity?->province_id ?? $admin->province_id;

        $admin->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'province_id' => $provinceId,
            'city_id' => $cityId,
        ]);

        $admin->save();
        $admin->cities()->sync([$cityId]);

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

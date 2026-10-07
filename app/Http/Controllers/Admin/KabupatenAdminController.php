<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Super-admin management of kabupaten admin accounts.
 *
 * A kabupaten admin is a dashboard user scoped to a single province + city;
 * the vouchers they input are automatically attributed to that city, and the
 * dashboard queries they run are filtered to it.
 */
class KabupatenAdminController extends Controller
{
    /**
     * List every kabupaten admin account with its assigned region.
     */
    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString() ?: null;

        $admins = User::query()
            ->where('role', UserRole::KabupatenAdmin)
            ->with(['city.province:id,name'])
            ->when($search, function ($query, string $term): void {
                $query->where(function ($inner) use ($term): void {
                    $inner->where('name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%");
                });
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (User $admin): array => [
                'id' => $admin->id,
                'name' => $admin->name,
                'email' => $admin->email,
                'province_id' => $admin->province_id,
                'province_name' => $admin->city?->province?->name,
                'city_id' => $admin->city_id,
                'city_name' => $admin->city?->name,
                'created_at' => $admin->created_at?->toIso8601String(),
            ]);

        return Inertia::render('admin/admins/Index', [
            'admins' => $admins,
            'filters' => ['search' => $search],
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
            'province_id' => ['required', 'integer', Rule::exists('provinces', 'id')],
            'city_id' => ['required', 'integer', Rule::exists('cities', 'id')],
        ]);

        User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => UserRole::KabupatenAdmin,
            'province_id' => $validated['province_id'],
            'city_id' => $validated['city_id'],
            'email_verified_at' => now(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Admin wilayah berhasil dibuat.']);

        return back();
    }

    /**
     * Update a kabupaten admin account and its assigned region.
     */
    public function update(Request $request, User $admin): RedirectResponse
    {
        abort_unless($admin->role === UserRole::KabupatenAdmin, 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($admin->id)],
            'password' => ['nullable', 'string', Password::defaults()],
            'province_id' => ['required', 'integer', Rule::exists('provinces', 'id')],
            'city_id' => ['required', 'integer', Rule::exists('cities', 'id')],
        ]);

        $admin->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'province_id' => $validated['province_id'],
            'city_id' => $validated['city_id'],
        ]);

        if (! empty($validated['password'])) {
            $admin->password = Hash::make($validated['password']);
        }

        $admin->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Admin wilayah berhasil diperbarui.']);

        return back();
    }

    /**
     * Delete a kabupaten admin account.
     */
    public function destroy(User $admin): RedirectResponse
    {
        abort_unless($admin->role === UserRole::KabupatenAdmin, 404);

        $admin->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Admin wilayah berhasil dihapus.']);

        return back();
    }
}

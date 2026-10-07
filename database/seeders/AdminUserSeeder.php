<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the default admin dashboard accounts (super admin & operator).
     *
     * Credentials can be overridden through the environment so production
     * deployments never ship with the documented development passwords.
     */
    public function run(): void
    {
        $accounts = [
            [
                'email' => env('SEED_SUPER_ADMIN_EMAIL', 'admin@rajawalitopup.com'),
                'name' => env('SEED_SUPER_ADMIN_NAME', 'Super Admin'),
                'password' => env('SEED_SUPER_ADMIN_PASSWORD', '@R4jaWal1!'),
                'role' => UserRole::SuperAdmin,
            ],
        ];

        foreach ($accounts as $account) {
            $user = User::query()->firstOrNew(['email' => $account['email']]);

            $user->name = $account['name'];
            $user->role = $account['role'];
            $user->email_verified_at = $user->email_verified_at ?? now();

            // Only set the password when the account is created. Re-running the
            // seeder must never silently reset a password that was changed by
            // the operator, which would lock them out of the dashboard.
            if (! $user->exists) {
                $user->password = $account['password'];
            }

            $user->save();
        }
    }
}

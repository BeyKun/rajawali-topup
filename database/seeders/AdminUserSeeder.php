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
                'password' => env('SEED_SUPER_ADMIN_PASSWORD', 'password'),
                'role' => UserRole::SuperAdmin,
            ],
            [
                'email' => env('SEED_OPERATOR_EMAIL', 'operator@rajawalitopup.com'),
                'name' => env('SEED_OPERATOR_NAME', 'Operator Kasir'),
                'password' => env('SEED_OPERATOR_PASSWORD', 'password'),
                'role' => UserRole::Operator,
            ],
        ];

        foreach ($accounts as $account) {
            User::updateOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'password' => $account['password'],
                    'role' => $account['role'],
                    'email_verified_at' => now(),
                ],
            );
        }
    }
}

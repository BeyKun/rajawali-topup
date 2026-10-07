<?php

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Support\Facades\Hash;

test('the admin seeder creates the super admin with the configured password', function () {
    config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);

    $this->seed(AdminUserSeeder::class);

    $admin = User::query()->where('email', 'admin@rajawalitopup.com')->first();

    expect($admin)->not->toBeNull()
        ->and($admin->role)->toBe(UserRole::SuperAdmin)
        ->and($admin->email_verified_at)->not->toBeNull();
});

test('re-running the admin seeder never resets a changed password', function () {
    $this->seed(AdminUserSeeder::class);

    $admin = User::query()->where('email', 'admin@rajawalitopup.com')->firstOrFail();
    $admin->password = 'a-brand-new-secret';
    $admin->save();

    $this->seed(AdminUserSeeder::class);

    $fresh = $admin->fresh();

    expect(Hash::check('a-brand-new-secret', $fresh->password))->toBeTrue();
});

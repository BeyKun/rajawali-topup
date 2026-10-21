<?php

use App\Enums\UserRole;
use App\Models\User;

test('an outlet user can sign in with their phone number and password', function () {
    $user = User::factory()->customer()->create([
        'phone' => '6281234567890',
        'password' => 'rajawali123',
    ]);

    $this->postJson('/api/v1/auth/login', [
        'phone' => '081234567890',
        'password' => 'rajawali123',
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('user.id', $user->id)
        ->assertJsonPath('user.phone', '6281234567890')
        ->assertJsonStructure(['success', 'token', 'user' => ['id', 'name', 'phone', 'profile_completed']]);

    expect($user->tokens()->count())->toBe(1);
});

test('the phone number is normalized before it is matched', function () {
    User::factory()->customer()->create([
        'phone' => '6281234567890',
        'password' => 'rajawali123',
    ]);

    foreach (['081234567890', '+62 812-3456-7890', '81234567890'] as $input) {
        $this->postJson('/api/v1/auth/login', [
            'phone' => $input,
            'password' => 'rajawali123',
        ])
            ->assertOk()
            ->assertJsonPath('success', true);
    }
});

test('a wrong password is rejected with 401', function () {
    User::factory()->customer()->create([
        'phone' => '6281234567890',
        'password' => 'rajawali123',
    ]);

    $this->postJson('/api/v1/auth/login', [
        'phone' => '081234567890',
        'password' => 'salah-sekali',
    ])
        ->assertStatus(401)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Nomor HP atau password salah.');
});

test('an unknown phone number is rejected with 401', function () {
    $this->postJson('/api/v1/auth/login', [
        'phone' => '081234567890',
        'password' => 'rajawali123',
    ])
        ->assertStatus(401)
        ->assertJsonPath('success', false);
});

test('a non-customer account cannot sign into the mobile app', function () {
    User::factory()->admin()->create([
        'phone' => '6281234567890',
        'password' => 'rajawali123',
    ]);

    $this->postJson('/api/v1/auth/login', [
        'phone' => '081234567890',
        'password' => 'rajawali123',
    ])
        ->assertStatus(403)
        ->assertJsonPath('success', false);
});

test('the login payload requires a valid phone number and password', function () {
    $this->postJson('/api/v1/auth/login', [])
        ->assertStatus(422)
        ->assertJsonPath('success', false);

    $this->postJson('/api/v1/auth/login', [
        'phone' => '021555111',
        'password' => 'rajawali123',
    ])
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

test('a newly created outlet can sign in with the default password', function () {
    $user = User::factory()->customer()->create([
        'phone' => '6281200000001',
        'password' => (string) config('outlet.default_password'),
    ]);

    expect($user->role)->toBe(UserRole::Customer);

    $this->postJson('/api/v1/auth/login', [
        'phone' => '081200000001',
        'password' => 'rajawali123',
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('user.id', $user->id);
});

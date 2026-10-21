<?php

use App\Http\Controllers\Api\Auth\PhoneAuthController;
use App\Models\User;

/**
 * Google sign-in has been replaced by phone + password authentication
 * ({@see PhoneAuthController}). The endpoint is
 * retained only to hand older app versions a clear migration hint, so these
 * tests now lock in that deprecation contract.
 */
test('the deprecated google endpoint returns a migration hint', function () {
    $this->postJson('/api/v1/auth/google', ['id_token' => 'valid-google-token'])
        ->assertStatus(410)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Login dengan Google sudah tidak digunakan. Silakan masuk menggunakan Nomor HP dan password.');
});

test('the deprecated google endpoint never creates a user', function () {
    $this->postJson('/api/v1/auth/google', ['id_token' => 'valid-google-token'])
        ->assertStatus(410);

    expect(User::query()->count())->toBe(0);
});

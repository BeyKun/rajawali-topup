<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.google.mock' => true]);
    Http::preventStrayRequests();
});

test('google auth creates a verified customer and returns a token', function () {
    $response = $this->postJson('/api/v1/auth/google', ['id_token' => 'valid-google-token']);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['success', 'token', 'user' => ['id', 'name', 'email', 'avatar_url']]);

    expect($response->json('token'))->toBeString()->not->toBeEmpty()
        ->and($response->json('user.id'))->toBeInt();

    $user = User::query()->sole();

    expect($user->role)->toBe(UserRole::Customer)
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->google_id)->not->toBeNull()
        ->and($user->avatar_url)->not->toBeNull();
});

test('the same google token reuses the same user', function () {
    $first = $this->postJson('/api/v1/auth/google', ['id_token' => 'repeatable-token']);
    $second = $this->postJson('/api/v1/auth/google', ['id_token' => 'repeatable-token']);

    $first->assertOk();
    $second->assertOk();

    expect($second->json('user.id'))->toBe($first->json('user.id'))
        ->and(User::query()->count())->toBe(1);
});

test('different tokens create different users', function () {
    $this->postJson('/api/v1/auth/google', ['id_token' => 'token-a'])->assertOk();
    $this->postJson('/api/v1/auth/google', ['id_token' => 'token-b'])->assertOk();

    expect(User::query()->count())->toBe(2);
});

test('an invalid token is rejected with 401', function () {
    $response = $this->postJson('/api/v1/auth/google', ['id_token' => '']);

    $response->assertStatus(422)
        ->assertJsonPath('success', false);
});

test('a missing token is rejected with the api envelope', function () {
    $this->postJson('/api/v1/auth/google', [])
        ->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Token Google wajib dikirim.');
});

test('a non-verifiable token is rejected with 401', function () {
    config(['services.google.mock' => false]);

    Http::fake([
        'https://oauth2.googleapis.com/*' => Http::response('invalid', 400),
    ]);

    $response = $this->postJson('/api/v1/auth/google', ['id_token' => 'not-a-real-jwt']);

    $response->assertStatus(401)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Token Google tidak valid.');
});

<?php

use App\Models\City;
use App\Models\District;
use App\Models\Province;
use App\Models\User;
use App\Models\Village;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

function resetTestRegion(): array
{
    $province = Province::create(['code' => '11', 'name' => 'Aceh']);
    $city = City::create(['province_id' => $province->id, 'province_code' => '11', 'code' => '11.01', 'name' => 'Aceh Selatan']);
    $district = District::create(['city_id' => $city->id, 'city_code' => '11.01', 'code' => '11.01.01', 'name' => 'Bakongan']);
    $village = Village::create(['district_id' => $district->id, 'district_code' => '11.01.01', 'code' => '11.01.01.2001', 'name' => 'Keude Bakongan']);

    return compact('province', 'city', 'district', 'village');
}

test('an outlet can reset their password with phone and outlet name verification', function () {
    $region = resetTestRegion();

    $user = User::factory()->customer()->create([
        'phone' => '628123456789',
        'password' => Hash::make('password_lama'),
    ]);

    $user->outletProfile()->create([
        'outlet_name' => 'Berkah Cell',
        'whatsapp' => '08123456789',
        'province_id' => $region['province']->id,
        'city_id' => $region['city']->id,
        'district_id' => $region['district']->id,
        'village_id' => $region['village']->id,
        'address' => 'Jl. Mawar No 10',
    ]);

    $response = $this->postJson('/api/v1/auth/reset-password', [
        'phone' => '08123456789',
        'outlet_name' => 'Berkah Cell',
        'new_password' => 'password_baru123',
        'new_password_confirmation' => 'password_baru123',
    ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
        ]);

    $user->refresh();
    expect(Hash::check('password_baru123', $user->password))->toBeTrue();
});

test('reset password fails if outlet name does not match', function () {
    $region = resetTestRegion();

    $user = User::factory()->customer()->create([
        'phone' => '628123456789',
        'password' => Hash::make('password_lama'),
    ]);

    $user->outletProfile()->create([
        'outlet_name' => 'Berkah Cell',
        'whatsapp' => '08123456789',
        'province_id' => $region['province']->id,
        'city_id' => $region['city']->id,
        'district_id' => $region['district']->id,
        'village_id' => $region['village']->id,
        'address' => 'Jl. Mawar No 10',
    ]);

    $response = $this->postJson('/api/v1/auth/reset-password', [
        'phone' => '08123456789',
        'outlet_name' => 'Toko Lain',
        'new_password' => 'password_baru123',
        'new_password_confirmation' => 'password_baru123',
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
            'message' => 'Nama outlet tidak sesuai dengan data terdaftar.',
        ]);
});

test('an authenticated outlet can change their password from profile', function () {
    $user = User::factory()->customer()->create([
        'phone' => '628123456789',
        'password' => Hash::make('password_lama123'),
    ]);

    Sanctum::actingAs($user);

    $response = $this->putJson('/api/v1/profile/password', [
        'current_password' => 'password_lama123',
        'new_password' => 'password_baru456',
        'new_password_confirmation' => 'password_baru456',
    ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Password berhasil diperbarui.',
        ]);

    $user->refresh();
    expect(Hash::check('password_baru456', $user->password))->toBeTrue();
});

test('change password fails if current password is wrong', function () {
    $user = User::factory()->customer()->create([
        'phone' => '628123456789',
        'password' => Hash::make('password_lama123'),
    ]);

    Sanctum::actingAs($user);

    $response = $this->putJson('/api/v1/profile/password', [
        'current_password' => 'password_salah',
        'new_password' => 'password_baru456',
        'new_password_confirmation' => 'password_baru456',
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
            'message' => 'Password saat ini tidak sesuai.',
        ]);
});

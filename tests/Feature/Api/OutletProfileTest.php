<?php

use App\Enums\VoucherStatus;
use App\Models\City;
use App\Models\District;
use App\Models\Product;
use App\Models\Province;
use App\Models\User;
use App\Models\Village;
use App\Models\Voucher;

/**
 * Build a full region chain and return the four ids needed by the profile form.
 *
 * @return array{province: Province, city: City, district: District, village: Village}
 */
function regionChain(): array
{
    $province = Province::create(['code' => '11', 'name' => 'Aceh']);
    $city = City::create(['province_id' => $province->id, 'province_code' => '11', 'code' => '11.01', 'name' => 'Aceh Selatan']);
    $district = District::create(['city_id' => $city->id, 'city_code' => '11.01', 'code' => '11.01.01', 'name' => 'Bakongan']);
    $village = Village::create(['district_id' => $district->id, 'district_code' => '11.01.01', 'code' => '11.01.01.2001', 'name' => 'Keude Bakongan']);

    return compact('province', 'city', 'district', 'village');
}

test('the profile endpoint reports an incomplete profile right after login', function () {
    $user = User::factory()->customer()->create();

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/profile')
        ->assertOk()
        ->assertJsonPath('data.profile_completed', false);
});

test('the profile endpoint stores all mandatory outlet fields', function () {
    $user = User::factory()->customer()->create();
    $region = regionChain();

    $this->actingAs($user, 'sanctum')
        ->putJson('/api/v1/profile', [
            'outlet_name' => 'Konter Rajawali',
            'whatsapp' => '081234567890',
            'province_id' => $region['province']->id,
            'city_id' => $region['city']->id,
            'district_id' => $region['district']->id,
            'village_id' => $region['village']->id,
            'address' => 'Jl. Merdeka No. 1',
        ])
        ->assertOk()
        ->assertJsonPath('data.profile_completed', true)
        ->assertJsonPath('data.outlet_name', 'Konter Rajawali')
        ->assertJsonPath('data.city', 'Aceh Selatan');

    $this->assertDatabaseHas('outlet_profiles', [
        'user_id' => $user->id,
        'outlet_name' => 'Konter Rajawali',
        'whatsapp' => '081234567890',
    ]);
});

test('the profile endpoint rejects missing fields', function () {
    $user = User::factory()->customer()->create();

    $this->actingAs($user, 'sanctum')
        ->putJson('/api/v1/profile', ['outlet_name' => 'Konter'])
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

test('orders are blocked until the outlet profile is complete', function () {
    $user = User::factory()->customer()->create();
    $product = Product::factory()->create(['is_active' => true]);
    Voucher::factory()->create(['product_id' => $product->id, 'status' => VoucherStatus::Available]);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/orders', [
            'product_id' => $product->id,
            'msisdn' => '082233456777',
        ])
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

<?php

use App\Models\City;
use App\Models\District;
use App\Models\Province;
use App\Models\User;
use App\Models\Village;

beforeEach(function () {
    $this->withoutVite();
});

test('guests cannot access region endpoints', function () {
    $this->getJson('/admin/api/regions/provinces')->assertUnauthorized();
});

test('provinces endpoint returns paginated, searchable results', function () {
    $admin = User::factory()->admin()->create();

    Province::create(['code' => '11', 'name' => 'Aceh']);
    Province::create(['code' => '31', 'name' => 'DKI Jakarta']);

    $response = $this->actingAs($admin)
        ->getJson('/admin/api/regions/provinces?search=aceh')
        ->assertOk();

    expect($response->json('total'))->toBe(1)
        ->and($response->json('data.0.name'))->toBe('Aceh')
        ->and($response->json('has_more'))->toBeFalse();
});

test('cities endpoint scopes results to the selected province', function () {
    $admin = User::factory()->admin()->create();

    $aceh = Province::create(['code' => '11', 'name' => 'Aceh']);
    $jakarta = Province::create(['code' => '31', 'name' => 'DKI Jakarta']);

    City::create(['province_id' => $aceh->id, 'province_code' => '11', 'code' => '11.01', 'name' => 'Aceh Selatan']);
    City::create(['province_id' => $jakarta->id, 'province_code' => '31', 'code' => '31.01', 'name' => 'Jakarta Pusat']);

    $response = $this->actingAs($admin)
        ->getJson('/admin/api/regions/cities?province='.$aceh->id)
        ->assertOk();

    expect($response->json('total'))->toBe(1)
        ->and($response->json('data.0.name'))->toBe('Aceh Selatan');
});

test('districts and villages endpoints scope to their parents', function () {
    $admin = User::factory()->admin()->create();

    $province = Province::create(['code' => '11', 'name' => 'Aceh']);
    $city = City::create(['province_id' => $province->id, 'province_code' => '11', 'code' => '11.01', 'name' => 'Aceh Selatan']);
    $district = District::create(['city_id' => $city->id, 'city_code' => '11.01', 'code' => '11.01.01', 'name' => 'Bakongan']);
    Village::create(['district_id' => $district->id, 'district_code' => '11.01.01', 'code' => '11.01.01.2001', 'name' => 'Keude Bakongan']);

    $this->actingAs($admin)
        ->getJson('/admin/api/regions/districts?city='.$city->id)
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.name', 'Bakongan');

    $this->actingAs($admin)
        ->getJson('/admin/api/regions/villages?district='.$district->id)
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.name', 'Keude Bakongan');
});

test('cities endpoint returns empty payload when province is missing', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->getJson('/admin/api/regions/cities')
        ->assertOk()
        ->assertJsonPath('total', 0)
        ->assertJsonPath('has_more', false);
});

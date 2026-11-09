<?php

use App\Enums\UserRole;
use App\Enums\VoucherStatus;
use App\Models\City;
use App\Models\Product;
use App\Models\Province;
use App\Models\User;
use App\Models\Voucher;

beforeEach(function () {
    $this->withoutVite();

    $province = Province::create(['code' => '11', 'name' => 'Aceh']);
    $this->cityA = City::create(['province_id' => $province->id, 'province_code' => '11', 'code' => '11.01', 'name' => 'Aceh Selatan']);
    $this->cityB = City::create(['province_id' => $province->id, 'province_code' => '11', 'code' => '11.02', 'name' => 'Aceh Singkil']);
    $this->province = $province;
});

test('only super admins can manage kabupaten admins', function () {
    $operator = User::factory()->operator()->create();
    $kabupaten = User::factory()->kabupatenAdmin($this->province->id, $this->cityA->id)->create();

    $this->actingAs($operator)->get('/admin/admins')->assertForbidden();
    $this->actingAs($kabupaten)->get('/admin/admins')->assertForbidden();

    $super = User::factory()->admin()->create();
    $this->actingAs($super)->get('/admin/admins')->assertOk();
});

test('a super admin can create a kabupaten admin scoped to a city', function () {
    $super = User::factory()->admin()->create();

    $this->actingAs($super)
        ->post('/admin/admins', [
            'name' => 'Admin Sikka',
            'email' => 'sikka@example.com',
            'password' => 'password123',
            'province_id' => $this->province->id,
            'city_id' => $this->cityA->id,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('users', [
        'email' => 'sikka@example.com',
        'role' => UserRole::KabupatenAdmin->value,
        'province_id' => $this->province->id,
        'city_id' => $this->cityA->id,
    ]);
});

test('operators can still access the catalog but not area mapping', function () {
    $operator = User::factory()->operator()->create();

    $this->actingAs($operator)->get('/admin/products')->assertOk();
    $this->actingAs($operator)->get('/admin/areas')->assertForbidden();
});

test('a kabupaten admin only sees vouchers in their own city and never sees vouchers without city_id', function () {
    $admin = User::factory()->kabupatenAdmin($this->province->id, $this->cityA->id)->create();

    $productA = Product::factory()->create(['city_id' => $this->cityA->id]);
    $productB = Product::factory()->create(['city_id' => $this->cityB->id]);
    $productUnscoped = Product::factory()->create(['city_id' => null]);

    Voucher::factory()->create(['product_id' => $productA->id, 'city_id' => $this->cityA->id, 'serial_number' => '111111111111', 'status' => VoucherStatus::Available]);
    Voucher::factory()->create(['product_id' => $productB->id, 'city_id' => $this->cityB->id, 'serial_number' => '222222222222', 'status' => VoucherStatus::Available]);
    Voucher::factory()->create(['product_id' => $productUnscoped->id, 'city_id' => null, 'serial_number' => '333333333333', 'status' => VoucherStatus::Available]);

    $response = $this->actingAs($admin)->get('/admin/vouchers')->assertOk();

    $serials = collect($response->viewData('page')['props']['vouchers']['data'])->pluck('serial_number');

    expect($serials)->toContain('111111111111')
        ->and($serials)->not->toContain('222222222222')
        ->and($serials)->not->toContain('333333333333');

    $productsResponse = $this->actingAs($admin)->get('/admin/products')->assertOk();
    $productIds = collect($productsResponse->viewData('page')['props']['products']['data'])->pluck('id');

    expect($productIds)->toContain($productA->id)
        ->and($productIds)->not->toContain($productB->id)
        ->and($productIds)->not->toContain($productUnscoped->id);
});

test('creating a kabupaten admin rejects multiple cities and enforces a single city', function () {
    $super = User::factory()->admin()->create();

    $this->actingAs($super)
        ->post('/admin/admins', [
            'name' => 'Admin Multi',
            'email' => 'multi@example.com',
            'password' => 'password123',
            'city_ids' => [$this->cityA->id, $this->cityB->id],
        ])
        ->assertSessionHasErrors('city_ids');
});

test('a kabupaten admin can sign into the dashboard', function () {
    $admin = User::factory()->kabupatenAdmin($this->province->id, $this->cityA->id)->create();

    $this->actingAs($admin)->get('/admin/dashboard')->assertOk();
});

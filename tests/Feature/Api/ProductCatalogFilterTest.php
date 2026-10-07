<?php

use App\Enums\VoucherStatus;
use App\Models\City;
use App\Models\Product;
use App\Models\Province;
use App\Models\TelkomselArea;
use App\Models\User;
use App\Models\Voucher;
use App\Services\VoucherInventoryService;

/**
 * Build two cities so catalog scoping can be asserted.
 *
 * @return array{province: Province, cityA: City, cityB: City}
 */
function twoCities(): array
{
    $province = Province::create(['code' => '11', 'name' => 'Aceh']);
    $cityA = City::create(['province_id' => $province->id, 'province_code' => '11', 'code' => '11.01', 'name' => 'Aceh Selatan']);
    $cityB = City::create(['province_id' => $province->id, 'province_code' => '11', 'code' => '11.02', 'name' => 'Aceh Singkil']);

    return compact('province', 'cityA', 'cityB');
}

test('outlets only see packages for their own city plus national packages', function () {
    ['cityA' => $cityA, 'cityB' => $cityB] = twoCities();

    $outlet = User::factory()->customer()->create(['city_id' => $cityA->id]);

    $local = Product::factory()->create(['is_active' => true, 'city_id' => $cityA->id, 'sort_order' => 1]);
    $other = Product::factory()->create(['is_active' => true, 'city_id' => $cityB->id, 'sort_order' => 2]);
    $national = Product::factory()->create(['is_active' => true, 'city_id' => null, 'sort_order' => 3]);

    foreach ([$local, $other, $national] as $product) {
        Voucher::factory()->create(['product_id' => $product->id, 'status' => VoucherStatus::Available]);
    }

    $response = $this->actingAs($outlet, 'sanctum')->getJson('/api/v1/products')->assertOk();

    $ids = collect($response->json('data'))->pluck('id');

    expect($ids)->toContain($local->id)
        ->and($ids)->toContain($national->id)
        ->and($ids)->not->toContain($other->id);
});

test('guests still see the full catalog', function () {
    ['cityA' => $cityA, 'cityB' => $cityB] = twoCities();

    $pA = Product::factory()->create(['is_active' => true, 'city_id' => $cityA->id]);
    $pB = Product::factory()->create(['is_active' => true, 'city_id' => $cityB->id]);

    foreach ([$pA, $pB] as $product) {
        Voucher::factory()->create(['product_id' => $product->id, 'status' => VoucherStatus::Available]);
    }

    $response = $this->getJson('/api/v1/products')->assertOk();

    expect($response->json('data'))->toHaveCount(2);
});

test('a mapped telkomsel zone attributes the voucher to that city', function () {
    ['cityA' => $cityA] = twoCities();

    TelkomselArea::create(['region' => 'SIKKA', 'city_id' => $cityA->id]);

    $service = app(VoucherInventoryService::class);
    $voucher = $service->addVoucher('300338120354', '71125613431848001');

    expect($voucher->city_id)->toBe($cityA->id)
        ->and($voucher->product->city_id)->toBe($cityA->id);
});

test('multi-city vouchers are visible to outlets in all selected cities and hidden from unselected cities', function () {
    ['province' => $province, 'cityA' => $cityA, 'cityB' => $cityB] = twoCities();
    $cityC = City::create(['province_id' => $province->id, 'province_code' => '11', 'code' => '11.03', 'name' => 'Aceh Barat']);

    $outletA = User::factory()->customer()->create(['city_id' => $cityA->id]);
    $outletB = User::factory()->customer()->create(['city_id' => $cityB->id]);
    $outletC = User::factory()->customer()->create(['city_id' => $cityC->id]);

    $service = app(VoucherInventoryService::class);
    $voucher = $service->addVoucher(
        '300338120354',
        '71125613431848001',
        sellPrice: 35000,
        cityIds: [$cityA->id, $cityB->id],
    );

    $productId = $voucher->product_id;

    // Outlet A sees the product
    $resA = $this->actingAs($outletA, 'sanctum')->getJson('/api/v1/products')->assertOk();
    expect(collect($resA->json('data'))->pluck('id'))->toContain($productId);

    // Outlet B sees the product
    $resB = $this->actingAs($outletB, 'sanctum')->getJson('/api/v1/products')->assertOk();
    expect(collect($resB->json('data'))->pluck('id'))->toContain($productId);

    // Outlet C does NOT see the product
    $resC = $this->actingAs($outletC, 'sanctum')->getJson('/api/v1/products')->assertOk();
    expect(collect($resC->json('data'))->pluck('id'))->not->toContain($productId);
});

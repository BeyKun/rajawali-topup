<?php

use App\Enums\PaymentStatus;
use App\Enums\RedeemStatus;
use App\Enums\UserRole;
use App\Enums\VoucherStatus;
use App\Models\City;
use App\Models\Order;
use App\Models\Product;
use App\Models\Province;
use App\Models\User;
use App\Models\Voucher;

beforeEach(function () {
    $this->withoutVite();
    $this->admin = User::factory()->admin()->create(['role' => UserRole::SuperAdmin]);
});

test('cannot delete product if it still has available stock', function () {
    $product = Product::factory()->create();
    Voucher::factory()->create([
        'product_id' => $product->id,
        'status' => VoucherStatus::Available,
    ]);

    $response = $this->actingAs($this->admin)
        ->delete("/admin/products/{$product->id}");

    $response->assertSessionHasErrors(['product']);
    expect(Product::find($product->id))->not->toBeNull();
});

test('permanently deletes product when it has 0 available stock and has never been purchased', function () {
    $product = Product::factory()->create();

    $response = $this->actingAs($this->admin)
        ->delete("/admin/products/{$product->id}");

    $response->assertSessionHasNoErrors();
    expect(Product::withTrashed()->find($product->id))->toBeNull();
});

test('soft deletes product when it has 0 available stock but has existing orders', function () {
    $product = Product::factory()->create();
    $outlet = User::factory()->create(['role' => UserRole::Customer]);

    $order = Order::factory()->create([
        'user_id' => $outlet->id,
        'product_id' => $product->id,
        'payment_status' => PaymentStatus::Paid,
        'redeem_status' => RedeemStatus::Success,
    ]);

    $response = $this->actingAs($this->admin)
        ->delete("/admin/products/{$product->id}");

    $response->assertSessionHasNoErrors();

    // Standard query must NOT find the product
    expect(Product::find($product->id))->toBeNull()
        // withTrashed MUST find the product
        ->and(Product::withTrashed()->find($product->id))->not->toBeNull()
        ->and(Product::withTrashed()->find($product->id)->deleted_at)->not->toBeNull();

    // Order relationship must still resolve the soft-deleted product
    $order->refresh();
    expect($order->product)->not->toBeNull()
        ->and($order->product->id)->toBe($product->id);
});

test('soft deletes product when it has 0 available stock but has redeemed vouchers', function () {
    $product = Product::factory()->create();

    $voucher = Voucher::factory()->create([
        'product_id' => $product->id,
        'status' => VoucherStatus::Redeemed,
    ]);

    $response = $this->actingAs($this->admin)
        ->delete("/admin/products/{$product->id}");

    $response->assertSessionHasNoErrors();

    expect(Product::find($product->id))->toBeNull()
        ->and(Product::withTrashed()->find($product->id))->not->toBeNull();

    $voucher->refresh();
    expect($voucher->product)->not->toBeNull()
        ->and($voucher->product->id)->toBe($product->id);
});

test('kabupaten admin cannot delete product outside their managed city', function () {
    $province = Province::create(['name' => 'Provinsi Test']);
    $city1 = City::create(['province_id' => $province->id, 'name' => 'Kota A']);
    $city2 = City::create(['province_id' => $province->id, 'name' => 'Kota B']);

    $kabupatenAdmin = User::factory()->admin()->create([
        'role' => UserRole::KabupatenAdmin,
        'city_id' => $city1->id,
    ]);

    $productInCity2 = Product::factory()->create(['city_id' => $city2->id]);

    $response = $this->actingAs($kabupatenAdmin)
        ->delete("/admin/products/{$productInCity2->id}");

    $response->assertForbidden();
    expect(Product::find($productInCity2->id))->not->toBeNull();
});

test('editing a voucher to a new catalog and then back to the original catalog leaves the new catalog intact with 0 vouchers', function () {
    $catalogA = Product::factory()->create([
        'name' => 'Paket Awal 10GB',
        'quota_description' => 'Kuota Utama 10GB',
        'hpp_price' => 20000,
        'sell_price' => 20400,
        'city_id' => null,
    ]);

    $voucher1 = Voucher::factory()->create([
        'product_id' => $catalogA->id,
        'serial_number' => '123456789012',
        'status' => VoucherStatus::Available,
        'city_id' => null,
    ]);

    $voucher2 = Voucher::factory()->create([
        'product_id' => $catalogA->id,
        'serial_number' => '123456789013',
        'status' => VoucherStatus::Available,
        'city_id' => null,
    ]);

    // Step 1: Edit voucher1 manually to a new title & description -> creates Catalog B
    $this->actingAs($this->admin)
        ->put("/admin/vouchers/{$voucher1->serial_number}", [
            'product_id' => null,
            'title' => 'Paket Spesial Baru',
            'description' => 'Deskripsi Baru 15GB',
            'serial_number' => $voucher1->serial_number,
            'status' => VoucherStatus::Available->value,
            'sell_price' => 20000,
            'margin_percentage' => 2,
            'city_ids' => [],
        ])
        ->assertRedirect();

    $voucher1->refresh();
    $catalogB = Product::find($voucher1->product_id);

    expect($catalogB)->not->toBeNull()
        ->and($catalogB->id)->not->toBe($catalogA->id)
        ->and($catalogB->name)->toBe('Paket Spesial Baru')
        ->and($catalogB->quota_description)->toBe('Deskripsi Baru 15GB')
        ->and($catalogB->vouchers()->count())->toBe(1);

    // Step 2: Edit voucher1 back to Catalog A from the dropdown
    $this->actingAs($this->admin)
        ->put("/admin/vouchers/{$voucher1->serial_number}", [
            'product_id' => $catalogA->id,
            'title' => $catalogA->name,
            'description' => $catalogA->quota_description,
            'serial_number' => $voucher1->serial_number,
            'status' => VoucherStatus::Available->value,
            'sell_price' => 20000,
            'margin_percentage' => 2,
            'city_ids' => [],
        ])
        ->assertRedirect();

    $voucher1->refresh();
    $catalogB->refresh();

    // Voucher 1 returned to Catalog A
    expect($voucher1->product_id)->toBe($catalogA->id)
        ->and($catalogA->vouchers()->count())->toBe(2)
        // Catalog B is NOT overwritten into a duplicate of Catalog A, and its total vouchers is 0
        ->and($catalogB->name)->toBe('Paket Spesial Baru')
        ->and($catalogB->quota_description)->toBe('Deskripsi Baru 15GB')
        ->and($catalogB->vouchers()->count())->toBe(0);
});

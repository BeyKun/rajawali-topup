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

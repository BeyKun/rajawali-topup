<?php

use App\Enums\VoucherStatus;
use App\Models\Product;
use App\Models\Voucher;

/**
 * Create an active product that owns a number of available vouchers.
 */
function catalogProduct(array $attributes = [], int $availableStock = 0): Product
{
    $product = Product::factory()->create(array_merge(['is_active' => true], $attributes));

    if ($availableStock > 0) {
        Voucher::factory()->count($availableStock)->create([
            'product_id' => $product->id,
            'status' => VoucherStatus::Available,
        ]);
    }

    return $product;
}

test('products endpoint returns active products with stock counts', function () {
    $product = catalogProduct([
        'name' => 'Telkomsel 7GB',
        'quota_description' => '7 GB Internet',
        'validity_days' => 5,
        'sell_price' => 25000,
        'sort_order' => 1,
    ], availableStock: 3);

    Voucher::factory()->create([
        'product_id' => $product->id,
        'status' => VoucherStatus::Redeemed,
    ]);

    $response = $this->getJson('/api/v1/products');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.0.id', $product->id)
        ->assertJsonPath('data.0.stock_count', 3)
        ->assertJsonPath('data.0.is_available', true)
        ->assertJsonPath('data.0.sell_price', 25000)
        ->assertJsonPath('data.0.validity_days', 5);

    expect($response->json('data.0'))->not->toHaveKey('hpp_price');
});

test('products endpoint hides inactive products', function () {
    catalogProduct(['is_active' => false], availableStock: 2);

    $this->getJson('/api/v1/products')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('products endpoint hides products with no available stock', function () {
    catalogProduct(['is_active' => true], availableStock: 0);

    $this->getJson('/api/v1/products')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('products are sorted by sort_order then id', function () {
    $second = catalogProduct(['sort_order' => 2], availableStock: 1);
    $first = catalogProduct(['sort_order' => 1], availableStock: 1);

    $response = $this->getJson('/api/v1/products')->assertOk();

    expect($response->json('data.0.id'))->toBe($first->id)
        ->and($response->json('data.1.id'))->toBe($second->id);
});

<?php

use App\Enums\VoucherStatus;
use App\Models\Product;
use App\Models\User;
use App\Models\Voucher;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->withoutVite();
    $this->admin = User::factory()->admin()->create();
});

/**
 * Create a product with a number of AVAILABLE and RESERVED vouchers.
 */
function catalogRow(int $available = 0, int $reserved = 0, array $attributes = []): Product
{
    $product = Product::factory()->create($attributes);

    if ($available > 0) {
        Voucher::factory()->count($available)->create([
            'product_id' => $product->id,
            'status' => VoucherStatus::Available,
        ]);
    }

    if ($reserved > 0) {
        Voucher::factory()->count($reserved)->create([
            'product_id' => $product->id,
            'status' => VoucherStatus::Reserved,
        ]);
    }

    return $product;
}

test('catalog index lists grouped packages with live stock counts', function () {
    catalogRow(available: 3, reserved: 2, attributes: ['name' => 'Internet 5 Hari', 'sort_order' => 1]);
    catalogRow(available: 0, reserved: 0, attributes: ['name' => 'Internet 7 Hari', 'sort_order' => 2]);

    $this->actingAs($this->admin)
        ->get('/admin/products')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/products/Index')
            ->has('products.data', 2)
            ->where('products.data.0.name', 'Internet 5 Hari')
            ->where('products.data.0.available_stock', 3)
            ->where('products.data.0.reserved_stock', 2)
            ->where('products.data.0.total_stock', 5)
            ->where('summary.total_products', 2)
            ->where('summary.available_stock', 3)
        );
});

test('catalog index can filter to low and empty stock', function () {
    catalogRow(available: 1, attributes: ['name' => 'Low Stock', 'sort_order' => 1]);
    catalogRow(available: 0, attributes: ['name' => 'Empty Stock', 'sort_order' => 2]);
    catalogRow(available: 9, attributes: ['name' => 'Healthy Stock', 'sort_order' => 3]);

    $this->actingAs($this->admin)
        ->get('/admin/products?stock=low')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('products.data', 2)
        );

    $this->actingAs($this->admin)
        ->get('/admin/products?stock=empty')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.name', 'Empty Stock')
        );
});

test('catalog index searches by name and quota', function () {
    catalogRow(available: 1, attributes: ['name' => 'Internet 5 Hari', 'quota_description' => '4 GB']);
    catalogRow(available: 1, attributes: ['name' => 'Combo Sakti', 'quota_description' => '20 GB']);

    $this->actingAs($this->admin)
        ->get('/admin/products?search=Sakti')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.name', 'Combo Sakti')
        );
});

test('an operator can update the sell price, status and sort order', function () {
    $product = catalogRow(available: 1, attributes: ['sell_price' => 25000, 'is_active' => true, 'sort_order' => 0]);

    $this->actingAs($this->admin)
        ->put("/admin/products/{$product->id}", [
            'sell_price' => 30000,
            'is_active' => false,
            'sort_order' => 5,
        ])
        ->assertRedirect();

    $product->refresh();

    expect((float) $product->sell_price)->toBe(30000.0)
        ->and($product->is_active)->toBeFalse()
        ->and($product->sort_order)->toBe(5);
});

test('updating a package validates its payload', function () {
    $product = Product::factory()->create();

    $this->actingAs($this->admin)
        ->put("/admin/products/{$product->id}", [
            'sell_price' => -1,
            'is_active' => true,
            'sort_order' => 0,
        ])
        ->assertSessionHasErrors('sell_price');
});

test('a non-admin cannot access the catalog admin screen', function () {
    $customer = User::factory()->customer()->create();

    $this->actingAs($customer)
        ->get('/admin/products')
        ->assertForbidden();
});

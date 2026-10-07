<?php

use App\Enums\PaymentStatus;
use App\Enums\RedeemStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Voucher;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->withoutVite();
});

test('dashboard page renders with analytics props', function () {
    $admin = User::factory()->admin()->create();
    $product = Product::factory()->create(['sell_price' => 15000]);

    Voucher::factory()->count(3)->create(['product_id' => $product->id]);

    Order::factory()->create([
        'product_id' => $product->id,
        'voucher_id' => null,
        'payment_status' => PaymentStatus::Paid,
        'redeem_status' => RedeemStatus::Success,
        'total_amount' => 15000,
        'paid_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get('/admin/dashboard')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/Dashboard')
            ->where('stats.total_orders', 1)
            ->where('stats.available_vouchers', 3)
            ->where('stats.redeemed_vouchers', 0)
            ->has('salesChart', 7)
            ->has('lowStock')
            ->has('recentOrders', 1)
        );
});

test('wilayah admin dashboard is scoped strictly to their city', function () {
    $province = \App\Models\Province::create(['code' => '11', 'name' => 'Aceh']);
    $cityA = \App\Models\City::create(['province_id' => $province->id, 'province_code' => '11', 'code' => '11.01', 'name' => 'Aceh Selatan']);
    $cityB = \App\Models\City::create(['province_id' => $province->id, 'province_code' => '11', 'code' => '11.02', 'name' => 'Aceh Singkil']);
    $wilayahAdmin = User::factory()->kabupatenAdmin($province->id, $cityA->id)->create();

    $productA = Product::factory()->create(['city_id' => $cityA->id, 'sell_price' => 15000]);
    $productB = Product::factory()->create(['city_id' => $cityB->id, 'sell_price' => 20000]);

    Voucher::factory()->create(['product_id' => $productA->id, 'city_id' => $cityA->id]);
    Voucher::factory()->count(5)->create(['product_id' => $productB->id, 'city_id' => $cityB->id]);

    Order::factory()->create([
        'product_id' => $productA->id,
        'payment_status' => PaymentStatus::Paid,
        'redeem_status' => RedeemStatus::Success,
        'total_amount' => 15000,
        'paid_at' => now(),
    ]);
    Order::factory()->count(3)->create([
        'product_id' => $productB->id,
        'payment_status' => PaymentStatus::Paid,
        'redeem_status' => RedeemStatus::Success,
        'total_amount' => 20000,
        'paid_at' => now(),
    ]);

    $this->actingAs($wilayahAdmin)
        ->get('/admin/dashboard')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/Dashboard')
            ->where('stats.total_orders', 1)
            ->where('stats.available_vouchers', 1)
            ->has('recentOrders', 1)
        );
});

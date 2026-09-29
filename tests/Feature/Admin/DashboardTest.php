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

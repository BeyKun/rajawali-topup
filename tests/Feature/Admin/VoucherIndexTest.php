<?php

use App\Enums\VoucherStatus;
use App\Models\Product;
use App\Models\User;
use App\Models\Voucher;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->withoutVite();
    $this->admin = User::factory()->admin()->create();
    $this->product = Product::factory()->create(['sell_price' => 25000]);
});

test('voucher index renders with status summary', function () {
    Voucher::factory()->count(2)->create([
        'product_id' => $this->product->id,
        'status' => VoucherStatus::Available,
    ]);
    Voucher::factory()->create([
        'product_id' => $this->product->id,
        'status' => VoucherStatus::Redeemed,
    ]);

    $this->actingAs($this->admin)
        ->get('/admin/vouchers')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/vouchers/Index')
            ->where('statusCounts.AVAILABLE', 2)
            ->where('statusCounts.REDEEMED', 1)
            ->has('vouchers.data', 3)
            ->where('vouchers.data.0.sell_price', 25000)
        );
});

test('voucher index filters by status', function () {
    Voucher::factory()->create([
        'product_id' => $this->product->id,
        'status' => VoucherStatus::Available,
    ]);
    Voucher::factory()->create([
        'product_id' => $this->product->id,
        'status' => VoucherStatus::Expired,
    ]);

    $this->actingAs($this->admin)
        ->get('/admin/vouchers?status=EXPIRED')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('vouchers.data', 1)
            ->where('vouchers.data.0.status', 'EXPIRED')
        );
});

test('voucher index searches by serial number', function () {
    Voucher::factory()->create([
        'product_id' => $this->product->id,
        'serial_number' => '300338120354',
    ]);
    Voucher::factory()->create([
        'product_id' => $this->product->id,
        'serial_number' => '999999999999',
    ]);

    $this->actingAs($this->admin)
        ->get('/admin/vouchers?search=300338')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('vouchers.data', 1)
            ->where('vouchers.data.0.serial_number', '300338120354')
        );
});

test('voucher index searches by redeemed msisdn', function () {
    Voucher::factory()->create([
        'product_id' => $this->product->id,
        'redeemed_msisdn' => '6282233456777',
    ]);
    Voucher::factory()->create([
        'product_id' => $this->product->id,
        'redeemed_msisdn' => null,
    ]);

    $this->actingAs($this->admin)
        ->get('/admin/vouchers?search=628223')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('vouchers.data', 1)
            ->where('vouchers.data.0.redeemed_msisdn', '6282233456777')
        );
});

test('voucher index never exposes the hrn', function () {
    Voucher::factory()->create([
        'product_id' => $this->product->id,
        'serial_number' => '300338120354',
        'hrn' => '71125613431848001',
    ]);

    $this->actingAs($this->admin)
        ->get('/admin/vouchers')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->missing('vouchers.data.0.hrn')
        );
});

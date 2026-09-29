<?php

use App\Enums\PaymentStatus;
use App\Enums\RedeemStatus;
use App\Enums\VoucherStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\Voucher;
use App\Services\OrderService;

beforeEach(function () {
    config([
        'payment.provider' => 'mock',
        'telkomsel.mock' => true,
    ]);
});

test('expired unpaid orders release their reserved voucher back to available', function () {
    $product = Product::factory()->create(['is_active' => true, 'sell_price' => 25000]);
    $voucher = Voucher::factory()->create([
        'product_id' => $product->id,
        'status' => VoucherStatus::Reserved,
        'reserved_at' => now()->subMinutes(30),
    ]);

    $order = Order::factory()->create([
        'product_id' => $product->id,
        'voucher_id' => $voucher->id,
        'payment_status' => PaymentStatus::Unpaid,
        'redeem_status' => RedeemStatus::Pending,
        'qris_expired_at' => now()->subMinutes(10),
    ]);

    $released = app(OrderService::class)->releaseExpiredOrders();

    expect($released)->toBe(1);

    $order->refresh();
    $voucher->refresh();

    expect($order->payment_status)->toBe(PaymentStatus::Expired)
        ->and($voucher->status)->toBe(VoucherStatus::Available)
        ->and($voucher->reserved_at)->toBeNull();
});

test('an order that has not expired yet keeps its voucher reserved', function () {
    $product = Product::factory()->create(['is_active' => true, 'sell_price' => 25000]);
    $voucher = Voucher::factory()->create([
        'product_id' => $product->id,
        'status' => VoucherStatus::Reserved,
        'reserved_at' => now(),
    ]);

    Order::factory()->create([
        'product_id' => $product->id,
        'voucher_id' => $voucher->id,
        'payment_status' => PaymentStatus::Unpaid,
        'redeem_status' => RedeemStatus::Pending,
        'qris_expired_at' => now()->addMinutes(5),
    ]);

    $released = app(OrderService::class)->releaseExpiredOrders();

    expect($released)->toBe(0)
        ->and($voucher->refresh()->status)->toBe(VoucherStatus::Reserved);
});

test('a paid order keeps its voucher reserved even past the qris window', function () {
    $product = Product::factory()->create(['is_active' => true, 'sell_price' => 25000]);
    $voucher = Voucher::factory()->create([
        'product_id' => $product->id,
        'status' => VoucherStatus::Reserved,
    ]);

    Order::factory()->create([
        'product_id' => $product->id,
        'voucher_id' => $voucher->id,
        'payment_status' => PaymentStatus::Paid,
        'redeem_status' => RedeemStatus::Success,
        'paid_at' => now()->subMinutes(30),
        'qris_expired_at' => now()->subMinutes(20),
    ]);

    $released = app(OrderService::class)->releaseExpiredOrders();

    expect($released)->toBe(0)
        ->and($voucher->refresh()->status)->toBe(VoucherStatus::Reserved);
});

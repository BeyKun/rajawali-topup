<?php

use App\Enums\PaymentStatus;
use App\Enums\RedeemStatus;
use App\Enums\VoucherStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;
use Laravel\Sanctum\Sanctum;

/**
 * Regression suite proving the secret voucher code (HRN) never leaks through
 * any public/consumer surface, the admin voucher index, or order responses.
 */
beforeEach(function () {
    config([
        'services.google.mock' => true,
        'payment.provider' => 'mock',
        'telkomsel.mock' => true,
        'inertia.ssr.enabled' => false,
    ]);

    Http::preventStrayRequests();
    $this->withoutVite();

    $this->hrn = '71125613431848001';
    $this->admin = User::factory()->admin()->create();
    $this->customer = User::factory()->customer()->create();
    $this->product = Product::factory()->create(['is_active' => true]);
});

test('products endpoint never exposes the hrn', function () {
    Voucher::factory()->create([
        'product_id' => $this->product->id,
        'serial_number' => '300338120354',
        'hrn' => $this->hrn,
        'status' => VoucherStatus::Available,
    ]);

    $response = $this->getJson('/api/v1/products');

    $response->assertOk()->assertJsonPath('success', true);

    expect($response->getContent())->not->toContain($this->hrn)
        ->and($response->json('data.0'))->not->toHaveKey('hrn');
});

test('order history endpoint never exposes the hrn', function () {
    Sanctum::actingAs($this->customer);

    Order::factory()->create([
        'user_id' => $this->customer->id,
        'product_id' => $this->product->id,
        'redeem_status' => RedeemStatus::Success,
        'redeem_response_raw' => [
            'status' => true,
            'hrn' => $this->hrn,
            'data' => ['code' => '00', 'sn' => 'SN-99887766'],
        ],
    ]);

    $response = $this->getJson('/api/v1/orders/history');

    $response->assertOk()->assertJsonPath('success', true);

    expect($response->getContent())->not->toContain($this->hrn)
        ->and($response->json('data.0'))->not->toHaveKey('hrn');
});

test('order detail endpoint never exposes the hrn', function () {
    Sanctum::actingAs($this->customer);

    $order = Order::factory()->create([
        'user_id' => $this->customer->id,
        'product_id' => $this->product->id,
        'payment_status' => PaymentStatus::Paid,
        'redeem_status' => RedeemStatus::Success,
        'redeem_response_raw' => [
            'status' => true,
            'hrn' => $this->hrn,
            'data' => ['code' => '00', 'sn' => 'SN-99887766'],
        ],
    ]);

    $response = $this->getJson("/api/v1/orders/{$order->order_no}");

    $response->assertOk()->assertJsonPath('success', true);

    expect($response->getContent())->not->toContain($this->hrn)
        ->and($response->json('data'))->not->toHaveKey('hrn');
});

test('webhook response and resulting order payload never expose the hrn', function () {
    $voucher = Voucher::factory()->create([
        'product_id' => $this->product->id,
        'serial_number' => '300338120354',
        'hrn' => $this->hrn,
        'status' => VoucherStatus::Reserved,
        'reserved_at' => now(),
    ]);

    $order = Order::factory()->create([
        'user_id' => $this->customer->id,
        'product_id' => $this->product->id,
        'voucher_id' => $voucher->id,
        'payment_status' => PaymentStatus::Unpaid,
    ]);

    $response = $this->postJson('/api/v1/webhooks/qris', ['order_no' => $order->order_no]);

    $response->assertOk()->assertJsonPath('success', true);

    expect($response->getContent())->not->toContain($this->hrn);

    $fresh = $order->fresh();

    expect($fresh->payment_status)->toBe(PaymentStatus::Paid)
        ->and($fresh->redeem_status)->toBe(RedeemStatus::Success)
        ->and(json_encode($fresh->redeem_response_raw))->not->toContain($this->hrn);
});

test('admin voucher index Inertia props never expose the hrn', function () {
    Voucher::factory()->create([
        'product_id' => $this->product->id,
        'serial_number' => '300338120354',
        'hrn' => $this->hrn,
        'status' => VoucherStatus::Available,
    ]);

    $response = $this->actingAs($this->admin)
        ->get('/admin/vouchers')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/vouchers/Index')
            ->has('vouchers.data', 1)
            ->where('vouchers.data.0.serial_number', '300338120354')
            ->missing('vouchers.data.0.hrn')
        );

    expect($response->getContent())->not->toContain($this->hrn);
});

test('voucher model hides the hrn and stores it encrypted', function () {
    $voucher = Voucher::factory()->create([
        'product_id' => $this->product->id,
        'hrn' => $this->hrn,
    ]);

    $serialized = $voucher->toArray();

    expect($serialized)->not->toHaveKey('hrn')
        ->and(json_encode($serialized))->not->toContain($this->hrn);

    $rawColumn = DB::table('vouchers')->where('id', $voucher->id)->value('hrn');

    expect($rawColumn)->not->toBe($this->hrn)
        ->and((string) $rawColumn)->not->toContain($this->hrn)
        ->and($voucher->refresh()->hrn)->toBe($this->hrn);
});

<?php

use App\Enums\PaymentStatus;
use App\Enums\RedeemStatus;
use App\Enums\VoucherStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Voucher;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    config([
        'services.google.mock' => true,
        'payment.provider' => 'mock',
        'telkomsel.mock' => true,
    ]);

    $this->user = User::factory()->customer()->create();
    completeOutletProfile($this->user);
});

/**
 * Create an active product that owns the given number of available vouchers.
 */
function sellableProduct(int $stock = 1): Product
{
    $product = Product::factory()->create([
        'is_active' => true,
        'sell_price' => 25000,
    ]);

    Voucher::factory()->count($stock)->create([
        'product_id' => $product->id,
        'status' => VoucherStatus::Available,
    ]);

    return $product;
}

test('a customer can create an order and a voucher becomes reserved', function () {
    Sanctum::actingAs($this->user);

    $product = sellableProduct();

    $response = $this->postJson('/api/v1/orders', [
        'product_id' => $product->id,
        'msisdn' => '082233456777',
    ]);

    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.order_no', fn ($orderNo) => str_starts_with((string) $orderNo, 'RJW-'))
        ->assertJsonPath('data.msisdn', '6282233456777')
        ->assertJsonPath('data.product_name', $product->name)
        ->assertJsonPath('data.total_amount', 25000)
        ->assertJsonPath('data.qris_string', fn ($qris) => is_string($qris) && $qris !== '')
        ->assertJsonStructure(['data' => ['order_no', 'msisdn', 'product_name', 'total_amount', 'qris_string', 'qris_url', 'expired_at']]);

    $order = Order::query()->sole();

    expect($order->user_id)->toBe($this->user->id)
        ->and($order->payment_status)->toBe(PaymentStatus::Unpaid);

    expect(Voucher::query()->where('product_id', $product->id)->sole()->status)->toBe(VoucherStatus::Reserved);
});

test('a non-telkomsel number is rejected with 422', function () {
    Sanctum::actingAs($this->user);

    $product = sellableProduct();

    $this->postJson('/api/v1/orders', [
        'product_id' => $product->id,
        'msisdn' => '089912345678',
    ])
        ->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Nomor tujuan harus nomor Telkomsel (0811, 0812, 0813, 0821, 0822, 0823, 0851, 0852, 0853).');
});

test('an order for a product without stock is rejected with 422', function () {
    Sanctum::actingAs($this->user);

    $product = Product::factory()->create(['is_active' => true]);

    $this->postJson('/api/v1/orders', [
        'product_id' => $product->id,
        'msisdn' => '082233456777',
    ])
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

test('an order for an inactive product is rejected with 422', function () {
    Sanctum::actingAs($this->user);

    $product = Product::factory()->create(['is_active' => false]);

    $this->postJson('/api/v1/orders', [
        'product_id' => $product->id,
        'msisdn' => '082233456777',
    ])
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

test('order detail exposes status and sn but never the hrn', function () {
    Sanctum::actingAs($this->user);

    $order = Order::factory()->create([
        'user_id' => $this->user->id,
        'msisdn' => '6282233456777',
        'payment_status' => PaymentStatus::Paid,
        'redeem_status' => RedeemStatus::Success,
        'redeem_response_raw' => [
            'status' => true,
            'hrn' => '71125613431848001',
            'data' => ['code' => '00', 'sn' => 'SN-99887766'],
        ],
    ]);

    $response = $this->getJson("/api/v1/orders/{$order->order_no}");

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.order_no', $order->order_no)
        ->assertJsonPath('data.payment_status', 'PAID')
        ->assertJsonPath('data.redeem_status', 'SUCCESS')
        ->assertJsonPath('data.sn', 'SN-99887766')
        ->assertJsonPath('data.message', 'Paket data berhasil diaktifkan ke 6282233456777');

    expect($response->getContent())->not->toContain('71125613431848001');
    expect(collect($response->json('data'))->keys()->contains('hrn'))->toBeFalse();
});

test('order detail reports the unpaid state message', function () {
    Sanctum::actingAs($this->user);

    $order = Order::factory()->create([
        'user_id' => $this->user->id,
        'payment_status' => PaymentStatus::Unpaid,
        'redeem_status' => RedeemStatus::Pending,
    ]);

    $this->getJson("/api/v1/orders/{$order->order_no}")
        ->assertOk()
        ->assertJsonPath('data.message', 'Menunggu pembayaran QRIS')
        ->assertJsonPath('data.sn', null);
});

test('order detail returns the qris payload so an unpaid order can be resumed', function () {
    Sanctum::actingAs($this->user);

    $order = Order::factory()->create([
        'user_id' => $this->user->id,
        'msisdn' => '6282233456777',
        'payment_status' => PaymentStatus::Unpaid,
        'redeem_status' => RedeemStatus::Pending,
        'qris_string' => '00020101021226610014ID.CO.QRIS.WWW',
        'qris_url' => 'https://example.test/qr.png',
        'qris_expired_at' => now()->addMinutes(10),
    ]);

    $this->getJson("/api/v1/orders/{$order->order_no}")
        ->assertOk()
        ->assertJsonPath('data.qris_string', '00020101021226610014ID.CO.QRIS.WWW')
        ->assertJsonPath('data.qris_url', 'https://example.test/qr.png')
        ->assertJsonPath('data.expired_at', fn ($value) => is_string($value) && $value !== '');
});

test('order detail hides the qris payload once the order is paid', function () {
    Sanctum::actingAs($this->user);

    $order = Order::factory()->create([
        'user_id' => $this->user->id,
        'payment_status' => PaymentStatus::Paid,
        'redeem_status' => RedeemStatus::Success,
        'qris_string' => '00020101021226610014ID.CO.QRIS.WWW',
        'qris_url' => 'https://example.test/qr.png',
    ]);

    $this->getJson("/api/v1/orders/{$order->order_no}")
        ->assertOk()
        ->assertJsonPath('data.qris_string', null)
        ->assertJsonPath('data.qris_url', null);
});

test('history exposes the expiry so the app can gate the resume button', function () {
    Sanctum::actingAs($this->user);

    $order = Order::factory()->create([
        'user_id' => $this->user->id,
        'payment_status' => PaymentStatus::Unpaid,
        'redeem_status' => RedeemStatus::Pending,
        'qris_expired_at' => now()->addMinutes(10),
    ]);

    $this->getJson('/api/v1/orders/history')
        ->assertOk()
        ->assertJsonPath('data.0.order_no', $order->order_no)
        ->assertJsonPath('data.0.expired_at', fn ($value) => is_string($value) && $value !== '');
});

test('another users order returns 404', function () {
    Sanctum::actingAs($this->user);

    $other = User::factory()->customer()->create();
    $order = Order::factory()->create(['user_id' => $other->id]);

    $this->getJson("/api/v1/orders/{$order->order_no}")
        ->assertStatus(404)
        ->assertJsonPath('success', false);
});

test('history returns only the authenticated users orders', function () {
    Sanctum::actingAs($this->user);

    $mine = Order::factory()->create(['user_id' => $this->user->id]);
    $other = User::factory()->customer()->create();
    Order::factory()->create(['user_id' => $other->id]);

    $response = $this->getJson('/api/v1/orders/history');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.order_no', $mine->order_no)
        ->assertJsonStructure(['data' => [['order_no', 'product_name', 'msisdn', 'total_amount', 'payment_status', 'redeem_status', 'sn', 'created_at']]]);
});

test('history is ordered newest first', function () {
    Sanctum::actingAs($this->user);

    $older = Order::factory()->create(['user_id' => $this->user->id]);
    $newer = Order::factory()->create(['user_id' => $this->user->id]);

    $response = $this->getJson('/api/v1/orders/history')->assertOk();

    expect($response->json('data.0.order_no'))->toBe($newer->order_no)
        ->and($response->json('data.1.order_no'))->toBe($older->order_no);
});

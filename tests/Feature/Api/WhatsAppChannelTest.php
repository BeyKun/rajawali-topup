<?php

use App\Enums\OrderChannel;
use App\Enums\PaymentStatus;
use App\Enums\RedeemStatus;
use App\Enums\UserRole;
use App\Enums\VoucherStatus;
use App\Enums\WhatsAppEvent;
use App\Jobs\NotifyWhatsAppChannelJob;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.google.mock' => true,
        'payment.provider' => 'mock',
        'telkomsel.mock' => true,
        'whatsapp.channel_token' => 'test-channel-token',
        'whatsapp.bot_base_url' => 'https://bot.test',
        'whatsapp.bot_token' => 'test-bot-token',
    ]);

    $this->headers = ['Authorization' => 'Bearer test-channel-token'];
});

/**
 * Create an active product backed by the given number of available vouchers.
 */
function waSellableProduct(int $stock = 1): Product
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

test('the channel api rejects requests without a valid service token', function () {
    $this->getJson('/api/v1/wa/products')
        ->assertStatus(401)
        ->assertJsonPath('success', false);

    $this->getJson('/api/v1/wa/products', ['Authorization' => 'Bearer wrong'])
        ->assertStatus(401);
});

test('resolving a whatsapp number creates a customer on first contact', function () {
    $first = $this->postJson('/api/v1/wa/customers/resolve', [
        'wa_id' => '6281234567890',
        'display_name' => 'Budi',
    ], $this->headers);

    $first->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.is_new', true)
        ->assertJsonPath('data.phone', '6281234567890')
        ->assertJsonPath('data.name', 'Budi');

    $user = User::query()->sole();

    expect($user->role)->toBe(UserRole::Customer)
        ->and($user->phone)->toBe('6281234567890');

    $this->postJson('/api/v1/wa/customers/resolve', [
        'wa_id' => '081234567890',
    ], $this->headers)
        ->assertOk()
        ->assertJsonPath('data.is_new', false)
        ->assertJsonPath('data.user_id', $user->id);

    expect(User::query()->count())->toBe(1);
});

test('the channel catalog lists only active products with available stock', function () {
    $sellable = waSellableProduct(2);

    $empty = Product::factory()->create(['is_active' => true]);
    Product::factory()->create(['is_active' => false]);

    $response = $this->getJson('/api/v1/wa/products', $this->headers);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $sellable->id)
        ->assertJsonPath('data.0.stock_count', 2);
});

test('a whatsapp order reserves a voucher and is tagged with the channel', function () {
    $product = waSellableProduct();

    $response = $this->postJson('/api/v1/wa/orders', [
        'wa_id' => '6281234567890',
        'product_id' => $product->id,
        'msisdn' => '082233456777',
    ], $this->headers);

    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.msisdn', '6282233456777')
        ->assertJsonPath('data.product_name', $product->name)
        ->assertJsonPath('data.total_amount', 25000)
        ->assertJsonStructure(['data' => ['order_no', 'msisdn', 'product_name', 'total_amount', 'qris_string', 'qris_url', 'expired_at']]);

    $order = Order::query()->sole();

    expect($order->channel)->toBe(OrderChannel::WhatsApp)
        ->and($order->channel_ref)->toBe('6281234567890')
        ->and($order->payment_status)->toBe(PaymentStatus::Unpaid)
        ->and(Voucher::query()->where('product_id', $product->id)->sole()->status)->toBe(VoucherStatus::Reserved);
});

test('a whatsapp order for a non-telkomsel number is rejected', function () {
    $product = waSellableProduct();

    $this->postJson('/api/v1/wa/orders', [
        'wa_id' => '6281234567890',
        'product_id' => $product->id,
        'msisdn' => '089912345678',
    ], $this->headers)
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

test('order status and history are scoped to the whatsapp number', function () {
    $product = waSellableProduct();
    $other = User::factory()->customer()->create(['phone' => '628999999999']);

    $order = Order::factory()->create([
        'user_id' => $other->id,
        'product_id' => $product->id,
        'channel' => OrderChannel::WhatsApp,
        'channel_ref' => '6281234567890',
        'payment_status' => PaymentStatus::Paid,
        'redeem_status' => RedeemStatus::Success,
    ]);

    $this->getJson("/api/v1/wa/orders/{$order->order_no}?wa_id=6281234567890", $this->headers)
        ->assertStatus(404);

    $owner = User::factory()->customer()->create(['phone' => '628111111111']);
    $mine = Order::factory()->create([
        'user_id' => $owner->id,
        'product_id' => $product->id,
        'channel' => OrderChannel::WhatsApp,
        'channel_ref' => '628111111111',
        'payment_status' => PaymentStatus::Unpaid,
    ]);

    $this->getJson("/api/v1/wa/orders/{$mine->order_no}?wa_id=628111111111", $this->headers)
        ->assertOk()
        ->assertJsonPath('data.order_no', $mine->order_no)
        ->assertJsonPath('data.payment_status', 'UNPAID');

    $this->getJson('/api/v1/wa/orders/history?wa_id=628111111111', $this->headers)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.order_no', $mine->order_no);
});

test('the whatsapp callback job posts a status event to the bot', function () {
    Http::fake();

    $product = Product::factory()->create(['is_active' => true, 'sell_price' => 25000]);
    $order = Order::factory()->create([
        'product_id' => $product->id,
        'channel' => OrderChannel::WhatsApp,
        'channel_ref' => '6281234567890',
        'msisdn' => '6282233456777',
        'total_amount' => 25000,
        'payment_status' => PaymentStatus::Paid,
        'redeem_status' => RedeemStatus::Success,
        'redeem_response_raw' => ['data' => ['code' => '00', 'sn' => 'SN-123']],
    ]);

    NotifyWhatsAppChannelJob::dispatchFor($order, WhatsAppEvent::Success);

    Http::assertSent(function ($request) use ($order) {
        return $request->url() === 'https://bot.test/internal/order-events'
            && $request->hasHeader('Authorization', 'Bearer test-bot-token')
            && $request['order_no'] === $order->order_no
            && $request['wa_id'] === '6281234567890'
            && $request['event_type'] === 'SUCCESS'
            && $request['sn'] === 'SN-123';
    });
});

test('the callback job ignores orders that did not come from whatsapp', function () {
    Http::fake();

    $order = Order::factory()->create([
        'channel' => OrderChannel::Mobile,
        'channel_ref' => null,
    ]);

    NotifyWhatsAppChannelJob::dispatchFor($order, WhatsAppEvent::Success);

    Http::assertNothingSent();
});

<?php

use App\Contracts\MidtransClient;
use App\Enums\PaymentStatus;
use App\Enums\RedeemStatus;
use App\Enums\VoucherStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Voucher;
use App\Services\OrderService;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;

/**
 * A fake Midtrans client that records cancellations.
 */
final class RecordingMidtransClient implements MidtransClient
{
    /** @var list<string> */
    public array $cancellations = [];

    public ?Throwable $cancelException = null;

    public function charge(array $payload): object
    {
        return (object) [];
    }

    public function createSnapTransaction(array $payload): object
    {
        return (object) [
            'token' => 'snap-token-' . ($payload['transaction_details']['order_id'] ?? 'test'),
            'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v4/redirection/snap-token-' . ($payload['transaction_details']['order_id'] ?? 'test'),
        ];
    }

    public function cancel(string $transactionId): void
    {
        $this->cancellations[] = $transactionId;

        if ($this->cancelException !== null) {
            throw $this->cancelException;
        }
    }
}

beforeEach(function () {
    config([
        'services.google.mock' => true,
        'payment.provider' => 'midtrans',
        'payment.midtrans.server_key' => 'SB-Mid-server-TESTKEY',
        'payment.midtrans.client_key' => 'SB-Mid-client-TESTKEY',
        'payment.midtrans.is_production' => false,
    ]);

    $this->fake = new RecordingMidtransClient;
    $this->app->instance(MidtransClient::class, $this->fake);

    $this->user = User::factory()->customer()->create();
});

/**
 * Create an unpaid order holding a RESERVED voucher.
 */
function cancellableOrder(User $user): Order
{
    $product = Product::factory()->create(['is_active' => true, 'sell_price' => 25000]);
    $voucher = Voucher::factory()->create([
        'product_id' => $product->id,
        'status' => VoucherStatus::Reserved,
        'reserved_at' => now(),
    ]);

    return Order::factory()->create([
        'user_id' => $user->id,
        'product_id' => $product->id,
        'voucher_id' => $voucher->id,
        'payment_status' => PaymentStatus::Unpaid,
        'redeem_status' => RedeemStatus::Pending,
        'payment_ref_id' => 'txn-'.$product->id,
        'qris_expired_at' => now()->addMinutes(10),
    ]);
}

test('a customer can cancel an unpaid order and the voucher returns to stock', function () {
    Sanctum::actingAs($this->user);

    $order = cancellableOrder($this->user);
    $voucherId = $order->voucher_id;

    $this->postJson("/api/v1/orders/{$order->order_no}/cancel")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.order_no', $order->order_no)
        ->assertJsonPath('data.payment_status', 'CANCELED')
        ->assertJsonPath('data.redeem_status', 'CANCELED')
        ->assertJsonPath('data.message', 'Pesanan dibatalkan atau kedaluwarsa. Silakan buat pesanan baru.');

    expect($order->fresh()->payment_status)->toBe(PaymentStatus::Canceled)
        ->and($order->fresh()->redeem_status)->toBe(RedeemStatus::Canceled)
        ->and(Voucher::query()->find($voucherId)->status)->toBe(VoucherStatus::Available);

    expect($this->fake->cancellations)->toBe([$order->payment_ref_id]);
});

test('a paid order cannot be cancelled', function () {
    Sanctum::actingAs($this->user);

    $order = cancellableOrder($this->user);
    $order->update(['payment_status' => PaymentStatus::Paid, 'paid_at' => now()]);

    $this->postJson("/api/v1/orders/{$order->order_no}/cancel")
        ->assertStatus(422)
        ->assertJsonPath('success', false);

    expect($order->fresh()->payment_status)->toBe(PaymentStatus::Paid);
});

test('an already expired order cannot be cancelled again', function () {
    Sanctum::actingAs($this->user);

    $order = cancellableOrder($this->user);
    $order->update(['payment_status' => PaymentStatus::Expired]);

    $this->postJson("/api/v1/orders/{$order->order_no}/cancel")
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

test('another users order cannot be cancelled', function () {
    Sanctum::actingAs($this->user);

    $other = User::factory()->customer()->create();
    $order = cancellableOrder($other);

    $this->postJson("/api/v1/orders/{$order->order_no}/cancel")
        ->assertStatus(404)
        ->assertJsonPath('success', false);

    expect($order->fresh()->payment_status)->toBe(PaymentStatus::Unpaid);
});

test('cancelling still succeeds locally when the gateway cancel fails', function () {
    Sanctum::actingAs($this->user);

    $this->fake->cancelException = new RuntimeException('gateway down');

    $order = cancellableOrder($this->user);

    $this->postJson("/api/v1/orders/{$order->order_no}/cancel")
        ->assertOk()
        ->assertJsonPath('success', true);

    expect($order->fresh()->payment_status)->toBe(PaymentStatus::Canceled);
});

test('the service refuses to cancel an order that is not awaiting payment', function () {
    $order = cancellableOrder($this->user);
    $order->update(['payment_status' => PaymentStatus::Paid, 'paid_at' => now()]);

    expect(fn () => app(OrderService::class)->cancelOrder($order))
        ->toThrow(ValidationException::class);

    expect($order->fresh()->payment_status)->toBe(PaymentStatus::Paid);
});

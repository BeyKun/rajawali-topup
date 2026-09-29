<?php

use App\Contracts\MidtransClient;
use App\Enums\PaymentStatus;
use App\Enums\RedeemStatus;
use App\Enums\VoucherStatus;
use App\Jobs\ProcessVoucherRedeemJob;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Voucher;
use App\Services\OrderService;
use App\Services\PaymentGatewayService;
use Illuminate\Support\Facades\Queue;

/**
 * A controllable fake for the Midtrans Core API charge call.
 */
final class FakeMidtransClient implements MidtransClient
{
    /** @var list<array<string, mixed>> */
    public array $charges = [];

    public ?object $response = null;

    public ?Throwable $exception = null;

    /** @var list<string> */
    public array $cancellations = [];

    public ?Throwable $cancelException = null;

    public function charge(array $payload): object
    {
        $this->charges[] = $payload;

        if ($this->exception !== null) {
            throw $this->exception;
        }

        return $this->response ?? (object) [];
    }

    public function createSnapTransaction(array $payload): object
    {
        $this->charges[] = $payload;

        if ($this->exception !== null) {
            throw $this->exception;
        }

        return $this->response ?? (object) [
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
        'payment.provider' => 'midtrans',
        'payment.midtrans.driver' => 'core',
        'payment.midtrans.server_key' => 'SB-Mid-server-TESTKEY',
        'payment.midtrans.client_key' => 'SB-Mid-client-TESTKEY',
        'payment.midtrans.is_production' => false,
        'payment.midtrans.acquirer' => 'gopay',
    ]);

    $this->fake = new FakeMidtransClient;
    $this->app->instance(MidtransClient::class, $this->fake);
});

/**
 * Build the canonical Midtrans QRIS charge response used by the tests.
 *
 * @return array<string, mixed>
 */
function midtransQrisResponse(string $orderNo, int $amount = 25000): array
{
    return [
        'status_code' => '201',
        'status_message' => 'QRIS transaction is created successfully',
        'transaction_id' => 'txn-'.$orderNo,
        'order_id' => $orderNo,
        'gross_amount' => number_format($amount, 2, '.', ''),
        'payment_type' => 'qris',
        'transaction_status' => 'pending',
        'fraud_status' => 'accept',
        'acquirer' => 'gopay',
        'expiry_time' => '2026-09-24 13:45:00',
        'actions' => [
            [
                'name' => 'generate-qr-code',
                'method' => 'GET',
                'url' => 'https://api.sandbox.midtrans.com/v2/qris/'.$orderNo.'/qr-code',
            ],
        ],
    ];
}

test('midtrans invoice creation posts a qris charge and maps the response', function () {
    $this->fake->response = (object) midtransQrisResponse('RJW-20260924-0001');

    $invoice = app(PaymentGatewayService::class)->createQrisInvoice([
        'order_no' => 'RJW-20260924-0001',
        'amount' => 25000,
        'customer_name' => 'Budi Santoso',
        'msisdn' => '6282233456777',
    ]);

    expect($this->fake->charges)->toHaveCount(1);

    $charge = $this->fake->charges[0];

    expect($charge['payment_type'])->toBe('qris')
        ->and($charge['transaction_details']['order_id'])->toBe('RJW-20260924-0001')
        ->and($charge['transaction_details']['gross_amount'])->toBe(25000)
        ->and($charge['qris']['acquirer'])->toBe('gopay')
        ->and($invoice['transaction_id'])->toBe('txn-RJW-20260924-0001')
        ->and($invoice['qris_url'])->toBe('https://api.sandbox.midtrans.com/v2/qris/RJW-20260924-0001/qr-code')
        ->and($invoice['qris_string'])->toBeNull()
        // Midtrans reports expiry in Jakarta time; it is normalized to app UTC.
        ->and($invoice['expired_at']->toDateTimeString())->toBe('2026-09-24 06:45:00');
});

test('midtrans invoice creation supports gopay payment type without qris key', function () {
    config()->set('payment.midtrans.payment_type', 'gopay');

    $this->fake->response = (object) midtransQrisResponse('RJW-20260924-0002');

    $invoice = app(PaymentGatewayService::class)->createQrisInvoice([
        'order_no' => 'RJW-20260924-0002',
        'amount' => 15000,
        'customer_name' => 'Budi Santoso',
        'msisdn' => '6282233456777',
    ]);

    expect($this->fake->charges)->toHaveCount(1);

    $charge = $this->fake->charges[0];

    expect($charge['payment_type'])->toBe('gopay')
        ->and($charge['transaction_details']['order_id'])->toBe('RJW-20260924-0002')
        ->and($charge['transaction_details']['gross_amount'])->toBe(15000)
        ->and(isset($charge['qris']))->toBeFalse()
        ->and($invoice['transaction_id'])->toBe('txn-RJW-20260924-0002');
});

test('midtrans snap driver creates snap transaction with token and redirect url', function () {
    config()->set('payment.midtrans.driver', 'snap');

    $this->fake->response = (object) [
        'token' => 'snap-token-12345',
        'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v4/redirection/snap-token-12345',
    ];

    $invoice = app(PaymentGatewayService::class)->createQrisInvoice([
        'order_no' => 'RJW-20260924-SNAP1',
        'amount' => 50000,
        'customer_name' => 'Budi Santoso',
        'msisdn' => '6281234567890',
    ]);

    expect($this->fake->charges)->toHaveCount(1);

    $charge = $this->fake->charges[0];

    expect($charge['transaction_details']['order_id'])->toBe('RJW-20260924-SNAP1')
        ->and($charge['transaction_details']['gross_amount'])->toBe(50000)
        ->and($charge['customer_details']['phone'])->toBe('6281234567890')
        ->and($invoice['transaction_id'])->toBe('snap-token-12345')
        ->and($invoice['qris_url'])->toBe('https://app.sandbox.midtrans.com/snap/v4/redirection/snap-token-12345')
        ->and($invoice['qris_string'])->toBeNull();
});

test('a gateway failure during checkout releases the reserved voucher', function () {
    $this->fake->exception = new RuntimeException('network down');

    $user = User::factory()->customer()->create();
    $product = Product::factory()->create(['is_active' => true, 'sell_price' => 25000]);
    $voucher = Voucher::factory()->create([
        'product_id' => $product->id,
        'status' => VoucherStatus::Available,
    ]);

    expect(fn () => app(OrderService::class)->createOrder(
        $user,
        $product->id,
        '082233456777',
    ))->toThrow(RuntimeException::class);

    // The reservation is rolled back so stock is not silently lost.
    expect($voucher->refresh()->status)->toBe(VoucherStatus::Available)
        ->and($voucher->reserved_at)->toBeNull()
        ->and(Order::query()->count())->toBe(0);
});

test('midtrans invoice creation throws when the gateway rejects the charge', function () {
    $this->fake->response = (object) [
        'status_code' => '400',
        'status_message' => 'Invalid transaction',
    ];

    expect(fn () => app(PaymentGatewayService::class)->createQrisInvoice([
        'order_no' => 'RJW-20260924-0002',
        'amount' => 25000,
    ]))->toThrow(RuntimeException::class);
});

test('midtrans invoice creation wraps sdk failures as runtime exceptions', function () {
    $this->fake->exception = new RuntimeException('network down');

    expect(fn () => app(PaymentGatewayService::class)->createQrisInvoice([
        'order_no' => 'RJW-20260924-0003',
        'amount' => 25000,
    ]))->toThrow(RuntimeException::class);
});

test('a valid midtrans signature is accepted and settles the order', function () {
    Queue::fake();

    $order = midtransTestOrder();

    $payload = midtransNotification($order->order_no, 'settlement');
    $payload['signature_key'] = midtransSignature($payload);

    $this->postJson('/api/v1/webhooks/qris', $payload)
        ->assertOk()
        ->assertJsonPath('success', true);

    $order->refresh();

    expect($order->payment_status)->toBe(PaymentStatus::Paid)
        ->and($order->paid_at)->not->toBeNull();

    Queue::assertPushed(ProcessVoucherRedeemJob::class);
});

test('a forged midtrans signature is rejected with 401', function () {
    $order = midtransTestOrder();

    $payload = midtransNotification($order->order_no, 'settlement');
    $payload['signature_key'] = str_repeat('a', 128);

    $this->postJson('/api/v1/webhooks/qris', $payload)
        ->assertStatus(401)
        ->assertJsonPath('success', false);

    expect($order->fresh()->payment_status)->toBe(PaymentStatus::Unpaid);
});

test('a midtrans expire notification leaves the order unfunded', function () {
    $order = midtransTestOrder();

    $payload = midtransNotification($order->order_no, 'expire');
    $payload['signature_key'] = midtransSignature($payload);

    $this->postJson('/api/v1/webhooks/qris', $payload)->assertOk();

    expect($order->fresh()->payment_status)->toBe(PaymentStatus::Failed);
});

test('a paid notification is idempotent and does not double dispatch', function () {
    Queue::fake();

    $order = midtransTestOrder();

    $payload = midtransNotification($order->order_no, 'settlement');
    $payload['signature_key'] = midtransSignature($payload);

    $this->postJson('/api/v1/webhooks/qris', $payload)->assertOk();
    $this->postJson('/api/v1/webhooks/qris', $payload)->assertOk();

    Queue::assertPushed(ProcessVoucherRedeemJob::class, 1);
});

/**
 * Create an unpaid order backed by a reserved voucher.
 */
function midtransTestOrder(): Order
{
    $user = User::factory()->customer()->create();
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
        'msisdn' => '6282233456777',
        'total_amount' => 25000,
        'payment_status' => PaymentStatus::Unpaid,
        'redeem_status' => RedeemStatus::Pending,
    ]);
}

/**
 * @return array<string, mixed>
 */
function midtransNotification(string $orderNo, string $status): array
{
    return [
        'transaction_id' => 'txn-'.$orderNo,
        'order_id' => $orderNo,
        'status_code' => '200',
        'gross_amount' => '25000.00',
        'payment_type' => 'qris',
        'transaction_status' => $status,
        'fraud_status' => 'accept',
    ];
}

/**
 * @param  array<string, mixed>  $payload
 */
function midtransSignature(array $payload): string
{
    return hash(
        'sha512',
        $payload['order_id'].$payload['status_code'].$payload['gross_amount'].config('payment.midtrans.server_key'),
    );
}

<?php

use App\Enums\PaymentStatus;
use App\Enums\RedeemStatus;
use App\Enums\UserRole;
use App\Enums\VoucherStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\TelkomselApiLog;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->withoutVite();

    config([
        'telkomsel.mock' => true,
        'payment.provider' => 'mock',
        'services.google.mock' => true,
    ]);

    Http::preventStrayRequests();
});

/**
 * The numbered comments below mirror the end-to-end business flow described in
 * TASK.md Fase 7.2 so the assertions read top-to-bottom as the real journey.
 */
test('a physical voucher flows from admin intake to telkomsel redemption end to end', function () {
    // 1. Admin inputs 5 physical vouchers via the dashboard (mock Telkomsel check).
    $admin = User::factory()->admin()->create();

    /** @var list<string> $hrns */
    $hrns = [];
    $this->actingAs($admin);

    foreach (range(1, 5) as $index) {
        $hrns[] = '7112561343184800'.$index;

        $this->post('/admin/vouchers', [
            'serial_number' => '30033812035'.$index,
            'hrn' => '7112561343184800'.$index,
            'sell_price' => 25000,
        ])->assertRedirect('/admin/vouchers');
    }

    expect(Voucher::query()->count())->toBe(5)
        ->and(Voucher::query()->where('status', VoucherStatus::Available)->count())->toBe(5);

    // The 5 vouchers of the same package collapse into a single auto-created product.
    $product = Product::query()->sole();

    expect(Voucher::query()->where('product_id', $product->id)->count())->toBe(5)
        ->and((float) $product->sell_price)->toBe(25000.0);

    // Drop the admin session so the Sanctum token drives the mobile requests.
    $this->app['auth']->forgetGuards();

    // 2. Customer authenticates with Google (mock) and receives a Sanctum token.
    $auth = $this->postJson('/api/v1/auth/google', ['id_token' => 'rajawali-e2e-google-token'])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['success', 'token', 'user' => ['id']]);

    $token = (string) $auth->json('token');
    $customerId = (int) $auth->json('user.id');

    completeOutletProfile(User::query()->findOrFail($customerId));

    expect($token)->not->toBeEmpty()
        ->and(User::query()->findOrFail($customerId)->role)->toBe(UserRole::Customer);

    // 3. Customer fetches the catalog and sees the five available vouchers.
    $catalog = $this->withToken($token)
        ->getJson('/api/v1/products')
        ->assertOk()
        ->assertJsonPath('success', true);

    $catalogEntry = collect($catalog->json('data'))->firstWhere('id', $product->id);

    expect($catalogEntry)->not->toBeNull()
        ->and($catalogEntry['stock_count'])->toBe(5)
        ->and($catalogEntry['is_available'])->toBeTrue();

    // 4. Customer creates an order: one voucher is RESERVED and a QRIS invoice is built.
    $created = $this->withToken($token)
        ->postJson('/api/v1/orders', [
            'product_id' => $product->id,
            'msisdn' => '082233456777',
        ])
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.msisdn', '6282233456777')
        ->assertJsonPath('data.product_name', $product->name)
        ->assertJsonPath('data.total_amount', 25000)
        ->assertJsonStructure(['data' => ['order_no', 'msisdn', 'product_name', 'total_amount', 'qris_string', 'qris_url', 'expired_at']]);

    $orderNo = (string) $created->json('data.order_no');
    $order = Order::query()->where('order_no', $orderNo)->sole();

    expect($order->payment_status)->toBe(PaymentStatus::Unpaid)
        ->and($order->redeem_status)->toBe(RedeemStatus::Pending)
        ->and($order->voucher_id)->not->toBeNull()
        ->and($order->payment_ref_id)->not->toBeNull()
        ->and($created->json('data.qris_string'))->toBeString()->not->toBeEmpty();

    $voucher = Voucher::query()->findOrFail($order->voucher_id);

    expect($voucher->status)->toBe(VoucherStatus::Reserved)
        ->and(Voucher::query()->where('status', VoucherStatus::Reserved)->count())->toBe(1)
        ->and(Voucher::query()->where('status', VoucherStatus::Available)->count())->toBe(4);

    // 5. The QRIS gateway reports a settled payment for the order.
    $this->postJson('/api/v1/webhooks/qris', [
        'payment_ref_id' => $order->payment_ref_id,
    ])
        ->assertOk()
        ->assertJsonPath('success', true);

    // 6. With `queue=sync` the redeem job runs inline against the mock Telkomsel API.
    $order->refresh();
    $voucher->refresh();

    expect($order->payment_status)->toBe(PaymentStatus::Paid)
        ->and($order->redeem_status)->toBe(RedeemStatus::Success)
        ->and($order->redeem_response_code)->toBe('00')
        ->and($order->paid_at)->not->toBeNull()
        ->and($voucher->status)->toBe(VoucherStatus::Redeemed)
        ->and($voucher->redeemed_msisdn)->toBe('6282233456777')
        ->and($voucher->redeemed_at)->not->toBeNull();

    expect(Voucher::query()->count())->toBe(5)
        ->and(Voucher::query()->where('status', VoucherStatus::Redeemed)->count())->toBe(1)
        ->and(Voucher::query()->where('status', VoucherStatus::Available)->count())->toBe(4);

    // 7. Customer polls the order status and sees the redemption result.
    $status = $this->withToken($token)
        ->getJson("/api/v1/orders/{$orderNo}")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.order_no', $orderNo)
        ->assertJsonPath('data.payment_status', PaymentStatus::Paid->value)
        ->assertJsonPath('data.redeem_status', RedeemStatus::Success->value)
        ->assertJsonPath('data.message', 'Paket data berhasil diaktifkan ke 6282233456777')
        ->assertJsonStructure(['data' => ['order_no', 'payment_status', 'redeem_status', 'message', 'sn']])
        ->assertJsonMissingPath('data.hrn');

    // The mock redeemer returns an empty SN which the resource maps to null.
    expect($status->json('data'))->toHaveKey('sn')
        ->and($status->json('data.sn'))->toBeNull();

    // 8. Customer history includes the completed order.
    $history = $this->withToken($token)
        ->getJson('/api/v1/orders/history')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.order_no', $orderNo)
        ->assertJsonPath('data.0.redeem_status', RedeemStatus::Success->value)
        ->assertJsonMissingPath('data.0.hrn');

    // The plaintext HRN must never leak through any public API response.
    foreach ($hrns as $secret) {
        expect($created->getContent())->not->toContain($secret)
            ->and($status->getContent())->not->toContain($secret)
            ->and($history->getContent())->not->toContain($secret);
    }

    // The audit trail records both Telkomsel interactions.
    expect(TelkomselApiLog::query()->where('endpoint', 'check')->count())->toBe(5)
        ->and(TelkomselApiLog::query()->where('endpoint', 'redeem')->count())->toBe(1);

    // 9. Edge case: the pool decrements and a second order uses a different voucher.
    $second = $this->withToken($token)
        ->postJson('/api/v1/orders', [
            'product_id' => $product->id,
            'msisdn' => '081234567890',
        ])
        ->assertCreated()
        ->assertJsonPath('success', true);

    $secondOrder = Order::query()->where('order_no', $second->json('data.order_no'))->sole();

    expect($secondOrder->voucher_id)->not->toBe($order->voucher_id)
        ->and($secondOrder->payment_status)->toBe(PaymentStatus::Unpaid)
        ->and(Voucher::query()->where('id', $secondOrder->voucher_id)->sole()->status)->toBe(VoucherStatus::Reserved)
        ->and(Voucher::query()->where('id', $order->voucher_id)->sole()->status)->toBe(VoucherStatus::Redeemed)
        ->and(Voucher::query()->where('status', VoucherStatus::Available)->count())->toBe(3);
});

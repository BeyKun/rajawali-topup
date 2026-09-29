<?php

use App\Contracts\MidtransClient;
use App\Enums\PaymentStatus;
use App\Enums\RedeemStatus;
use App\Enums\UserRole;
use App\Enums\VoucherStatus;
use App\Jobs\ProcessVoucherRedeemJob;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Voucher;
use App\Rules\TelkomselMsisdn;
use App\Services\PaymentGatewayService;
use App\Services\TelkomselVoucherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

test('TelkomselMsisdn rule accepts all official telkomsel prefixes and rejects other operators', function () {
    $validNumbers = [
        '081112345678', // Telkomsel Halo
        '081212345678', // simPATI / Halo
        '081312345678', // simPATI / Halo
        '082112345678', // simPATI
        '082212345678', // Loop / simPATI
        '082312345678', // Kartu As
        '085112345678', // by.U / Kartu As
        '085212345678', // Kartu As
        '085312345678', // Kartu As
        '6281212345678',
        '+6282112345678',
    ];

    foreach ($validNumbers as $number) {
        $validator = Validator::make(['msisdn' => $number], ['msisdn' => [new TelkomselMsisdn]]);
        expect($validator->passes())->toBeTrue("Expected {$number} to be valid Telkomsel prefix.");
    }

    $invalidNumbers = [
        '081712345678', // XL
        '081812345678', // XL
        '085612345678', // Indosat
        '085712345678', // Indosat
        '089612345678', // Tri
        '088112345678', // Smartfren
    ];

    foreach ($invalidNumbers as $number) {
        $validator = Validator::make(['msisdn' => $number], ['msisdn' => [new TelkomselMsisdn]]);
        expect($validator->fails())->toBeTrue("Expected {$number} to be rejected.");
        expect($validator->errors()->first('msisdn'))->toContain('Nomor tujuan harus nomor Telkomsel');
    }
});

test('ProcessVoucherRedeemJob automatically triggers refund and frees voucher on region mismatch failure', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['sell_price' => '25000.00']);
    $voucher = Voucher::factory()->create([
        'product_id' => $product->id,
        'status' => VoucherStatus::Reserved,
        'reserved_at' => now(),
    ]);

    $order = Order::factory()->create([
        'user_id' => $user->id,
        'product_id' => $product->id,
        'voucher_id' => $voucher->id,
        'msisdn' => '6281212345678',
        'amount' => '25000.00',
        'total_amount' => '25000.00',
        'payment_status' => PaymentStatus::Paid,
        'redeem_status' => RedeemStatus::Processing,
        'paid_at' => now(),
    ]);

    // Test with mock payment provider
    config(['payment.provider' => 'mock']);

    // Mock Telkomsel service returning permanent region mismatch failure
    $telkomselMock = Mockery::mock(TelkomselVoucherService::class);
    $telkomselMock->shouldReceive('redeemVoucher')
        ->once()
        ->andReturn([
            'success' => false,
            'code' => '4001',
            'description' => 'Zona nomor tidak sesuai dengan peruntukan voucher (Invalid Region)',
            'raw' => ['error' => 'Region mismatch'],
        ]);
    $telkomselMock->shouldReceive('isPermanentFailure')
        ->once()
        ->andReturn(true);

    $job = new ProcessVoucherRedeemJob($order);
    $job->handle($telkomselMock, app(PaymentGatewayService::class));

    $order->refresh();
    $voucher->refresh();

    // Order must be marked failed and refunded
    expect($order->redeem_status)->toBe(RedeemStatus::Failed)
        ->and($order->payment_status)->toBe(PaymentStatus::Refunded)
        ->and((float) $order->refund_amount)->toBe(25000.00)
        ->and($order->refund_ref_id)->not->toBeNull()
        ->and($order->refunded_at)->not->toBeNull()
        ->and($order->refund_reason)->toContain('wilayah');

    // Physical voucher must be freed back to Available because it is still valid for other customers
    expect($voucher->status)->toBe(VoucherStatus::Available)
        ->and($voucher->reserved_at)->toBeNull();
});

test('ProcessVoucherRedeemJob sets REFUND_PENDING when gateway direct refund fails', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['sell_price' => '50000.00']);
    $voucher = Voucher::factory()->create([
        'product_id' => $product->id,
        'status' => VoucherStatus::Reserved,
        'reserved_at' => now(),
    ]);

    $order = Order::factory()->create([
        'user_id' => $user->id,
        'product_id' => $product->id,
        'voucher_id' => $voucher->id,
        'msisdn' => '6282112345678',
        'amount' => '50000.00',
        'total_amount' => '50000.00',
        'payment_status' => PaymentStatus::Paid,
        'redeem_status' => RedeemStatus::Processing,
        'paid_at' => now(),
    ]);

    config(['payment.provider' => 'midtrans']);

    // Fake Midtrans client throwing an exception (e.g. channel doesn't support online refund)
    $midtransFake = Mockery::mock(MidtransClient::class);
    $midtransFake->shouldReceive('refund')
        ->once()
        ->andThrow(new RuntimeException('Online refund not supported for this payment channel'));
    $this->app->instance(MidtransClient::class, $midtransFake);

    $telkomselMock = Mockery::mock(TelkomselVoucherService::class);
    $telkomselMock->shouldReceive('redeemVoucher')
        ->once()
        ->andReturn([
            'success' => false,
            'code' => '4001',
            'description' => 'Invalid Region',
            'raw' => [],
        ]);
    $telkomselMock->shouldReceive('isPermanentFailure')
        ->once()
        ->andReturn(true);

    $job = new ProcessVoucherRedeemJob($order);
    $job->handle($telkomselMock, app(PaymentGatewayService::class));

    $order->refresh();

    // Order must transition to REFUND_PENDING
    expect($order->redeem_status)->toBe(RedeemStatus::Failed)
        ->and($order->payment_status)->toBe(PaymentStatus::RefundPending)
        ->and((float) $order->refund_amount)->toBe(50000.00)
        ->and($order->refund_ref_id)->toBeNull();
});

test('admin can mark REFUND_PENDING order as REFUNDED', function () {
    $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
    $order = Order::factory()->create([
        'payment_status' => PaymentStatus::RefundPending,
        'redeem_status' => RedeemStatus::Failed,
        'total_amount' => '30000.00',
        'refund_amount' => '30000.00',
        'refund_reason' => 'Invalid Region',
    ]);

    $response = $this->actingAs($admin)
        ->post(route('admin.orders.mark-refunded', $order), [
            'refund_ref_id' => 'MANUAL-TRF-998877',
            'refund_reason' => 'Dana telah ditransfer manual ke rekening BCA pelanggan',
        ]);

    $response->assertRedirect();
    $order->refresh();

    expect($order->payment_status)->toBe(PaymentStatus::Refunded)
        ->and($order->refund_ref_id)->toBe('MANUAL-TRF-998877')
        ->and($order->refunded_at)->not->toBeNull()
        ->and($order->refund_reason)->toBe('Dana telah ditransfer manual ke rekening BCA pelanggan');
});

test('ProcessVoucherRedeemJob sets REFUNDED when Midtrans online refund succeeds', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['sell_price' => '20000.00']);
    $voucher = Voucher::factory()->create([
        'product_id' => $product->id,
        'status' => VoucherStatus::Reserved,
        'reserved_at' => now(),
    ]);

    $order = Order::factory()->create([
        'user_id' => $user->id,
        'product_id' => $product->id,
        'voucher_id' => $voucher->id,
        'msisdn' => '6281312345678',
        'amount' => '20000.00',
        'total_amount' => '20000.00',
        'payment_status' => PaymentStatus::Paid,
        'redeem_status' => RedeemStatus::Processing,
        'paid_at' => now(),
    ]);

    config(['payment.provider' => 'midtrans']);

    $midtransFake = Mockery::mock(MidtransClient::class);
    $midtransFake->shouldReceive('refund')
        ->once()
        ->andReturn((object) [
            'status_code' => '200',
            'id' => 'MID-REF-123456',
            'refund_key' => 'ref-123',
        ]);
    $this->app->instance(MidtransClient::class, $midtransFake);

    $telkomselMock = Mockery::mock(TelkomselVoucherService::class);
    $telkomselMock->shouldReceive('redeemVoucher')
        ->once()
        ->andReturn([
            'success' => false,
            'code' => '4001',
            'description' => 'Region tidak sesuai',
            'raw' => [],
        ]);
    $telkomselMock->shouldReceive('isPermanentFailure')
        ->once()
        ->andReturn(true);

    $job = new ProcessVoucherRedeemJob($order);
    $job->handle($telkomselMock, app(PaymentGatewayService::class));

    $order->refresh();

    expect($order->redeem_status)->toBe(RedeemStatus::Failed)
        ->and($order->payment_status)->toBe(PaymentStatus::Refunded)
        ->and($order->refund_ref_id)->toBe('MID-REF-123456')
        ->and((float) $order->refund_amount)->toBe(20000.00)
        ->and($order->refunded_at)->not->toBeNull();
});


<?php

use App\Enums\PaymentStatus;
use App\Enums\RedeemStatus;
use App\Enums\VoucherStatus;
use App\Jobs\ProcessVoucherRedeemJob;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->withoutVite();
    $this->admin = User::factory()->admin()->create();
    $this->product = Product::factory()->create();
});

test('order index renders with filters and paginated rows', function () {
    Order::factory()->create([
        'product_id' => $this->product->id,
        'voucher_id' => null,
        'payment_status' => PaymentStatus::Paid,
        'redeem_status' => RedeemStatus::Success,
    ]);

    $this->actingAs($this->admin)
        ->get('/admin/orders')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/orders/Index')
            ->has('orders.data', 1)
            ->has('paymentStatuses')
            ->has('redeemStatuses')
        );
});

test('order index filters by payment status', function () {
    Order::factory()->create([
        'product_id' => $this->product->id,
        'voucher_id' => null,
        'payment_status' => PaymentStatus::Paid,
    ]);
    Order::factory()->create([
        'product_id' => $this->product->id,
        'voucher_id' => null,
        'payment_status' => PaymentStatus::Unpaid,
    ]);

    $this->actingAs($this->admin)
        ->get('/admin/orders?payment_status=PAID')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('orders.data', 1)
            ->where('orders.data.0.payment_status', 'PAID')
        );
});

test('order detail page renders without exposing the hrn', function () {
    $voucher = Voucher::factory()->create([
        'product_id' => $this->product->id,
        'serial_number' => '300338120354',
        'hrn' => '71125613431848001',
    ]);

    $order = Order::factory()->create([
        'product_id' => $this->product->id,
        'voucher_id' => $voucher->id,
        'payment_status' => PaymentStatus::Paid,
    ]);

    $this->actingAs($this->admin)
        ->get("/admin/orders/{$order->id}")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/orders/Show')
            ->where('order.voucher_serial_number', '300338120354')
            ->missing('order.hrn')
        );
});

test('retry redeem dispatches the job for a paid unfinished order', function () {
    Queue::fake();

    $voucher = Voucher::factory()->create([
        'product_id' => $this->product->id,
        'status' => VoucherStatus::Reserved,
    ]);

    $order = Order::factory()->create([
        'product_id' => $this->product->id,
        'voucher_id' => $voucher->id,
        'payment_status' => PaymentStatus::Paid,
        'redeem_status' => RedeemStatus::Failed,
    ]);

    $this->actingAs($this->admin)
        ->post("/admin/orders/{$order->id}/retry-redeem")
        ->assertRedirect();

    Queue::assertPushed(ProcessVoucherRedeemJob::class, function (ProcessVoucherRedeemJob $job) use ($order): bool {
        return $job->order->is($order);
    });
});

test('retry redeem is rejected when the order is not paid', function () {
    Queue::fake();

    $order = Order::factory()->create([
        'product_id' => $this->product->id,
        'voucher_id' => null,
        'payment_status' => PaymentStatus::Unpaid,
        'redeem_status' => RedeemStatus::Pending,
    ]);

    $this->actingAs($this->admin)
        ->post("/admin/orders/{$order->id}/retry-redeem")
        ->assertSessionHasErrors('order');

    Queue::assertNothingPushed();
});

test('cancelling an order marks it failed and releases the reserved voucher', function () {
    $voucher = Voucher::factory()->create([
        'product_id' => $this->product->id,
        'status' => VoucherStatus::Reserved,
    ]);

    $order = Order::factory()->create([
        'product_id' => $this->product->id,
        'voucher_id' => $voucher->id,
        'payment_status' => PaymentStatus::Paid,
        'redeem_status' => RedeemStatus::Pending,
    ]);

    $this->actingAs($this->admin)
        ->post("/admin/orders/{$order->id}/cancel")
        ->assertRedirect();

    expect($order->fresh()->payment_status)->toBe(PaymentStatus::Canceled)
        ->and($order->fresh()->redeem_status)->toBe(RedeemStatus::Canceled)
        ->and($voucher->fresh()->status)->toBe(VoucherStatus::Available);
});

test('a successful order cannot be cancelled', function () {
    $order = Order::factory()->create([
        'product_id' => $this->product->id,
        'voucher_id' => null,
        'payment_status' => PaymentStatus::Paid,
        'redeem_status' => RedeemStatus::Success,
    ]);

    $this->actingAs($this->admin)
        ->post("/admin/orders/{$order->id}/cancel")
        ->assertSessionHasErrors('order');

    expect($order->fresh()->redeem_status)->toBe(RedeemStatus::Success);
});

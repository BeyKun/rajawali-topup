<?php

use App\Enums\PaymentStatus;
use App\Enums\RedeemStatus;
use App\Events\OrderCreated;
use App\Events\OrderStatusUpdated;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Voucher;
use App\Services\OrderService;
use App\Services\PaymentGatewayService;
use Illuminate\Support\Facades\Event;

it('dispatches OrderCreated event when order is created', function () {
    Event::fake([OrderCreated::class]);

    $user = User::factory()->create();
    $product = Product::factory()->create(['sell_price' => 25000]);
    $voucher = Voucher::factory()->create(['product_id' => $product->id]);

    $paymentGateway = Mockery::mock(PaymentGatewayService::class);
    $paymentGateway->shouldReceive('createQrisInvoice')
        ->once()
        ->andReturn([
            'qris_string' => '00020101021226670016ID.CO.SHOPEE.WWW',
            'qris_url' => 'https://qris.example/invoice.png',
            'expired_at' => now()->addMinutes(15),
            'transaction_id' => 'TRX-12345',
        ]);

    $orderService = new OrderService($paymentGateway);
    $order = $orderService->createOrder($user, $product->id, '081234567890');

    Event::assertDispatched(OrderCreated::class, function (OrderCreated $event) use ($order) {
        $data = $event->broadcastWith();

        return $event->order->id === $order->id
            && $data['order']['order_no'] === $order->order_no
            && $data['order']['total_amount'] === 25000.0;
    });
});

it('dispatches OrderStatusUpdated event when order is cancelled', function () {
    Event::fake([OrderStatusUpdated::class]);

    $order = Order::factory()->create([
        'payment_status' => PaymentStatus::Unpaid,
        'redeem_status' => RedeemStatus::Pending,
        'payment_ref_id' => 'TRX-999',
    ]);

    $paymentGateway = Mockery::mock(PaymentGatewayService::class);
    $paymentGateway->shouldReceive('cancelQrisInvoice')->with('TRX-999')->once();

    $orderService = new OrderService($paymentGateway);
    $orderService->cancelOrder($order);

    Event::assertDispatched(OrderStatusUpdated::class, function (OrderStatusUpdated $event) use ($order) {
        return $event->order->id === $order->id
            && $event->order->payment_status === PaymentStatus::Canceled;
    });
});

<?php

namespace App\Http\Controllers\Api;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessVoucherRedeemJob;
use App\Models\Order;
use App\Services\PaymentGatewayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Receives asynchronous payment callbacks from the QRIS gateway (Midtrans).
 *
 * The endpoint is intentionally unauthenticated — gateways cannot carry a
 * Sanctum token — so authenticity is enforced solely through the payload
 * signature verified by the payment gateway service.
 */
class WebhookController extends Controller
{
    public function __construct(private readonly PaymentGatewayService $paymentGateway) {}

    /**
     * Handle a QRIS payment callback.
     */
    public function qris(Request $request): JsonResponse
    {
        /** @var array<array-key, mixed> $payload */
        $payload = $request->all();

        if (! $this->paymentGateway->verifyWebhookSignature($payload, $request->headers->all())) {
            Log::warning('Rejected QRIS webhook with an invalid signature.', [
                'provider' => config('payment.provider'),
                'order_id' => $payload['order_id'] ?? null,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid signature.',
            ], 401);
        }

        $order = $this->resolveOrder($payload);

        if ($order === null) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found.',
            ], 404);
        }

        if ($this->paymentGateway->isPaymentSuccessful($payload)) {
            $this->markAsPaid($order);

            return response()->json(['success' => true]);
        }

        $this->handleNonSuccessStatus($order, $payload);

        return response()->json(['success' => true]);
    }

    /**
     * Flip an order to PAID (idempotently) and start the redeem pipeline.
     */
    protected function markAsPaid(Order $order): void
    {
        if ($order->payment_status === PaymentStatus::Paid) {
            return;
        }

        $order->update([
            'payment_status' => PaymentStatus::Paid,
            'paid_at' => now(),
        ]);

        ProcessVoucherRedeemJob::dispatch($order);
    }

    /**
     * React to terminal-but-unsuccessful gateway statuses.
     *
     * @param  array<array-key, mixed>  $payload
     */
    protected function handleNonSuccessStatus(Order $order, array $payload): void
    {
        $status = strtolower((string) ($payload['transaction_status'] ?? ''));

        if (! in_array($status, ['expire', 'expired', 'cancel', 'deny', 'failure'], true)) {
            return;
        }

        if ($order->payment_status === PaymentStatus::Unpaid) {
            $order->update(['payment_status' => PaymentStatus::Failed]);
        }
    }

    /**
     * Locate the order referenced by the gateway payload.
     *
     * Midtrans sends `order_id`; the mock/legacy providers send `order_no` or a
     * `payment_ref_id` / `transaction_id` reference.
     *
     * @param  array<array-key, mixed>  $payload
     */
    protected function resolveOrder(array $payload): ?Order
    {
        $orderNo = $payload['order_id'] ?? $payload['order_no'] ?? null;

        if (is_string($orderNo) && $orderNo !== '') {
            $order = Order::query()->where('order_no', $orderNo)->first();

            if ($order !== null) {
                return $order;
            }
        }

        $paymentRefId = $payload['payment_ref_id'] ?? $payload['transaction_id'] ?? null;

        if (is_string($paymentRefId) && $paymentRefId !== '') {
            return Order::query()->where('payment_ref_id', $paymentRefId)->first();
        }

        return null;
    }
}

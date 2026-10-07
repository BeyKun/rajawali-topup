<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Enums\RedeemStatus;
use App\Enums\VoucherStatus;
use App\Enums\WhatsAppEvent;
use App\Exceptions\OutOfStockException;
use App\Jobs\NotifyWhatsAppChannelJob;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Voucher;
use App\Support\Phone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Creates customer orders and keeps the physical voucher pool consistent.
 *
 * Voucher allocation runs inside a database transaction that locks the selected
 * row with `lockForUpdate()`, which guarantees a single voucher can never be
 * handed to two concurrent orders (no double-spending of stock).
 */
class OrderService
{
    public function __construct(private readonly PaymentGatewayService $paymentGateway) {}

    /**
     * Create an unpaid order and reserve one available voucher for it.
     *
     * Voucher allocation is split from the payment-gateway round-trip on purpose:
     *
     *  1. A short transaction locks the first AVAILABLE voucher row, flips it to
     *     RESERVED and commits. `lockForUpdate()` guarantees a single voucher can
     *     never be handed to two concurrent orders (no double-spending of stock).
     *  2. The gateway invoice is created *outside* that transaction so the row
     *     lock is not held for the duration of an external HTTP call, which would
     *     serialise every concurrent order for the same product.
     *
     * If the gateway fails the reservation is rolled back so the voucher returns
     * to the sellable pool.
     *
     * @throws OutOfStockException When no voucher is available for the product.
     */
    public function createOrder(User $user, int $productId, string $msisdn): Order
    {
        $normalizedMsisdn = Phone::normalizeTo62($msisdn);

        $order = DB::transaction(function () use ($user, $productId, $normalizedMsisdn): Order {
            $product = Product::query()->findOrFail($productId);

            /** @var Voucher|null $voucher */
            $voucher = Voucher::query()
                ->where('product_id', $productId)
                ->where('status', VoucherStatus::Available)
                ->when($user->city_id, fn ($q) => $q->forCity($user->city_id))
                ->lockForUpdate()
                ->first();

            if ($voucher === null) {
                throw new OutOfStockException('Stok paket data ini sedang kosong.');
            }

            $voucher->update([
                'status' => VoucherStatus::Reserved,
                'reserved_at' => now(),
            ]);

            return Order::query()->create([
                'order_no' => $this->generateOrderNo(),
                'user_id' => $user->id,
                'product_id' => $productId,
                'voucher_id' => $voucher->id,
                'msisdn' => $normalizedMsisdn,
                'amount' => $product->sell_price,
                'admin_fee' => '0',
                'total_amount' => $product->sell_price,
                'payment_channel' => 'QRIS',
                'payment_status' => PaymentStatus::Unpaid,
                'redeem_status' => RedeemStatus::Pending,
            ]);
        });

        try {
            $invoice = $this->paymentGateway->createQrisInvoice([
                'order_no' => $order->order_no,
                'amount' => $order->amount,
                'customer_name' => $user->name,
                'msisdn' => $normalizedMsisdn,
            ]);
        } catch (Throwable $exception) {
            $this->discardFailedCheckout($order);

            throw $exception;
        }

        $order->update([
            'qris_string' => $invoice['qris_string'],
            'qris_url' => $invoice['qris_url'],
            'qris_expired_at' => $invoice['expired_at'],
            'payment_ref_id' => $invoice['transaction_id'],
        ]);

        return $order;
    }

    /**
     * Cancel a still-unpaid order at the customer's request.
     *
     * The order is flipped to EXPIRED and its reserved voucher is returned to
     * the AVAILABLE pool. The state transition happens first (and atomically)
     * so an abandoned order can never keep stock locked; the gateway is then
     * asked to cancel the pending charge as a best-effort cleanup.
     *
     * Guarding on `payment_status === Unpaid` means a webhook that already
     * marked the order PAID can never be overwritten by a late cancellation.
     *
     * A cron sweep ({@see releaseExpiredOrders()}) remains the safety net for
     * any unpaid order the customer never explicitly cancels.
     *
     * @throws ValidationException When the order is not awaiting payment.
     */
    public function cancelOrder(Order $order): Order
    {
        $cancelled = DB::transaction(function () use ($order): bool {
            $locked = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->first();

            if ($locked === null || $locked->payment_status !== PaymentStatus::Unpaid) {
                return false;
            }

            $locked->update([
                'payment_status' => PaymentStatus::Canceled,
                'redeem_status' => RedeemStatus::Canceled,
            ]);

            $this->releaseReservedVoucher($locked->voucher_id);

            return true;
        });

        if (! $cancelled) {
            throw ValidationException::withMessages([
                'order' => 'Pesanan ini tidak dapat dibatalkan karena sudah dibayar atau sudah berakhir.',
            ]);
        }

        $order->refresh();

        if ($order->payment_ref_id !== null) {
            $this->paymentGateway->cancelQrisInvoice($order->payment_ref_id);
        }

        NotifyWhatsAppChannelJob::dispatchFor($order, WhatsAppEvent::Canceled);

        return $order;
    }

    /**
     * Expire unpaid orders whose QRIS window has closed and free their vouchers.
     *
     * Vouchers that are still RESERVED are returned to AVAILABLE so they can be
     * sold again. The number of released orders is returned.
     */
    public function releaseExpiredOrders(): int
    {
        return DB::transaction(function (): int {
            $expiredOrders = Order::query()
                ->where('payment_status', PaymentStatus::Unpaid)
                ->whereNotNull('qris_expired_at')
                ->where('qris_expired_at', '<', now())
                ->lockForUpdate()
                ->get();

            $released = 0;

            foreach ($expiredOrders as $order) {
                $order->update([
                    'payment_status' => PaymentStatus::Expired,
                    'redeem_status' => RedeemStatus::Failed,
                ]);

                $this->releaseReservedVoucher($order->voucher_id);

                NotifyWhatsAppChannelJob::dispatchFor($order, WhatsAppEvent::Expired);

                $released++;
            }

            return $released;
        });
    }

    /**
     * Undo a checkout whose payment invoice could not be created.
     *
     * The reserved voucher returns to the AVAILABLE pool and the provisional
     * order is removed, so a failed gateway call leaves no orphaned state.
     */
    protected function discardFailedCheckout(Order $order): void
    {
        $this->releaseReservedVoucher($order->voucher_id);

        $order->delete();
    }

    /**
     * Return a RESERVED voucher to the AVAILABLE pool.
     *
     * The conditional `where('status', RESERVED)` update keeps the release safe
     * against concurrent releases: only a voucher still reserved for the same
     * order is touched.
     */
    protected function releaseReservedVoucher(?int $voucherId): void
    {
        if ($voucherId === null) {
            return;
        }

        Voucher::query()
            ->whereKey($voucherId)
            ->where('status', VoucherStatus::Reserved->value)
            ->update([
                'status' => VoucherStatus::Available->value,
                'reserved_at' => null,
            ]);
    }

    /**
     * Build a unique order number in the `RJW-<timestamp>-<suffix>` format.
     */
    protected function generateOrderNo(): string
    {
        do {
            $orderNo = 'RJW-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4));
        } while (Order::query()->where('order_no', $orderNo)->exists());

        return $orderNo;
    }
}

<?php

namespace App\Jobs;

use App\Enums\RedeemStatus;
use App\Enums\VoucherStatus;
use App\Models\Order;
use App\Models\Voucher;
use App\Services\TelkomselVoucherService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Redeems a paid order's reserved voucher against the Telkomsel voucher API.
 *
 * The job is retried up to `$tries` times with a growing backoff. Each attempt
 * increments the order's `retry_count`; once the maximum is reached the order is
 * parked as FAILED while the voucher is intentionally left RESERVED so an admin
 * can retry the redemption manually from the dashboard.
 */
class ProcessVoucherRedeemJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Telkomsel /redeem response code signalling a successful redemption. */
    private const SUCCESS_CODE = '00';

    /** Maximum number of redeem attempts before the order is marked FAILED. */
    public int $tries = 3;

    public function __construct(public Order $order) {}

    /**
     * Delays in seconds between successive attempts.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [30, 60, 120];
    }

    /**
     * Execute the redeem attempt for the order.
     */
    public function handle(TelkomselVoucherService $telkomsel): void
    {
        $order = $this->order->fresh();

        if ($order === null || $order->redeem_status === RedeemStatus::Success) {
            return;
        }

        $voucher = $order->voucher;

        if ($voucher === null) {
            $order->update(['redeem_status' => RedeemStatus::Failed]);

            Log::error('Voucher redeem aborted: order has no reserved voucher.', [
                'order_id' => $order->id,
            ]);

            return;
        }

        $order->update([
            'redeem_status' => RedeemStatus::Processing,
            'retry_count' => $order->retry_count + 1,
        ]);

        $result = $telkomsel->redeemVoucher($voucher->hrn, $order->msisdn, $order->id, $voucher->id);

        if ($result['success'] && $result['code'] === self::SUCCESS_CODE) {
            $this->markSucceeded($order, $voucher, $result);

            return;
        }

        $this->handleFailure($order, $result);
    }

    /**
     * Safety net for the rare case the queue gives up before a verdict is stored.
     */
    public function failed(?Throwable $exception): void
    {
        $order = $this->order->fresh();

        if ($order !== null && $order->redeem_status !== RedeemStatus::Success) {
            $order->update(['redeem_status' => RedeemStatus::Failed]);
        }
    }

    /**
     * Record a successful redemption on both the voucher and the order.
     *
     * @param  array{success: bool, code: string, description: string, raw: array<array-key, mixed>}  $result
     */
    protected function markSucceeded(Order $order, Voucher $voucher, array $result): void
    {
        $voucher->update([
            'status' => VoucherStatus::Redeemed,
            'redeemed_at' => now(),
            'redeemed_msisdn' => $order->msisdn,
        ]);

        $order->update([
            'redeem_status' => RedeemStatus::Success,
            'redeem_response_code' => $result['code'],
            'redeem_response_raw' => $result['raw'],
        ]);
    }

    /**
     * Persist the failure response and decide whether to retry or give up.
     *
     * @param  array{success: bool, code: string, description: string, raw: array<array-key, mixed>}  $result
     *
     * @throws RuntimeException To release the job back onto the queue for a retry.
     */
    protected function handleFailure(Order $order, array $result): void
    {
        $order->update([
            'redeem_response_code' => $result['code'],
            'redeem_response_raw' => $result['raw'],
        ]);

        if ($order->retry_count >= $this->tries) {
            $order->update(['redeem_status' => RedeemStatus::Failed]);

            Log::error('Voucher redeem failed after the maximum number of attempts.', [
                'order_id' => $order->id,
                'code' => $result['code'],
                'description' => $result['description'],
                'retry_count' => $order->retry_count,
            ]);

            return;
        }

        throw new RuntimeException("Telkomsel redeem failed ({$result['code']}): {$result['description']}");
    }
}

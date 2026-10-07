<?php

namespace App\Jobs;

use App\Enums\PaymentStatus;
use App\Enums\RedeemStatus;
use App\Enums\VoucherStatus;
use App\Enums\WhatsAppEvent;
use App\Models\Order;
use App\Models\Voucher;
use App\Services\PaymentGatewayService;
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
 * increments the order's `retry_count`; once the maximum is reached or when a
 * permanent failure (such as region mismatch) occurs, the order is marked FAILED,
 * the voucher is unreserved, and a refund is initiated via the payment gateway.
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
    public function handle(TelkomselVoucherService $telkomsel, PaymentGatewayService $paymentGateway): void
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

            if ($order->payment_status === PaymentStatus::Paid) {
                $paymentGateway->refundOrder($order, 'Voucher tidak ditemukan untuk pesanan ini.');
            }

            return;
        }

        $order->update([
            'redeem_status' => RedeemStatus::Processing,
            'retry_count' => $order->retry_count + 1,
        ]);

        NotifyWhatsAppChannelJob::dispatchFor($order, WhatsAppEvent::Redeeming);

        $result = $telkomsel->redeemVoucher($voucher->hrn, $order->msisdn, $order->id, $voucher->id);

        if ($result['success'] && $result['code'] === self::SUCCESS_CODE) {
            $this->markSucceeded($order, $voucher, $result);

            return;
        }

        $this->handleFailure($order, $voucher, $result, $telkomsel, $paymentGateway);
    }

    /**
     * Safety net for the rare case the queue gives up before a verdict is stored.
     */
    public function failed(?Throwable $exception): void
    {
        $order = $this->order->fresh();

        if ($order !== null && $order->redeem_status !== RedeemStatus::Success) {
            $order->update(['redeem_status' => RedeemStatus::Failed]);

            if ($order->payment_status === PaymentStatus::Paid) {
                app(PaymentGatewayService::class)->refundOrder(
                    $order,
                    'Gagal aktivasi paket data Telkomsel: '.($exception?->getMessage() ?? 'Antrean proses gagal')
                );
            }

            NotifyWhatsAppChannelJob::dispatchFor($order, WhatsAppEvent::Failed);
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

        NotifyWhatsAppChannelJob::dispatchFor($order, WhatsAppEvent::Success);
    }

    /**
     * Persist the failure response and decide whether to retry or give up.
     *
     * @param  array{success: bool, code: string, description: string, raw: array<array-key, mixed>}  $result
     *
     * @throws RuntimeException To release the job back onto the queue for a retry.
     */
    protected function handleFailure(
        Order $order,
        Voucher $voucher,
        array $result,
        TelkomselVoucherService $telkomsel,
        PaymentGatewayService $paymentGateway
    ): void {
        $order->update([
            'redeem_response_code' => $result['code'],
            'redeem_response_raw' => $result['raw'],
        ]);

        $isPermanent = $telkomsel->isPermanentFailure($result);
        $exhaustedRetries = $order->retry_count >= $this->tries;

        if ($isPermanent || $exhaustedRetries) {
            $order->update(['redeem_status' => RedeemStatus::Failed]);

            // Release voucher back to available if it failed due to region mismatch or customer ineligibility
            if ($result['code'] !== '15' && $result['code'] !== '3023') {
                $voucher->update([
                    'status' => VoucherStatus::Available,
                    'reserved_at' => null,
                ]);
            }

            $reason = "Aktivasi voucher gagal ({$result['code']}): {$result['description']}";
            if ($isPermanent) {
                $reason = "Aktivasi voucher gagal kendala wilayah/nomor ({$result['code']}): {$result['description']}";
            }

            Log::error('Voucher redeem permanently failed or retries exhausted. Initiating refund.', [
                'order_id' => $order->id,
                'code' => $result['code'],
                'description' => $result['description'],
                'is_permanent' => $isPermanent,
                'retry_count' => $order->retry_count,
            ]);

            if ($order->payment_status === PaymentStatus::Paid) {
                $paymentGateway->refundOrder($order, $reason);
            }

            NotifyWhatsAppChannelJob::dispatchFor($order, WhatsAppEvent::Failed);

            return;
        }

        throw new RuntimeException("Telkomsel redeem failed ({$result['code']}): {$result['description']}");
    }
}

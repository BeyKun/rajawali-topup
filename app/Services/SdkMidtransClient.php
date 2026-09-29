<?php

namespace App\Services;

use App\Contracts\MidtransClient;
use Midtrans\Config as MidtransConfig;
use Midtrans\CoreApi;
use Midtrans\Snap;
use Midtrans\Transaction;

/**
 * Default {@see MidtransClient} backed by the official midtrans/midtrans-php SDK.
 */
class SdkMidtransClient implements MidtransClient
{
    /**
     * {@inheritDoc}
     */
    public function charge(array $payload): object
    {
        $this->configure();

        return CoreApi::charge($payload);
    }

    /**
     * {@inheritDoc}
     */
    public function createSnapTransaction(array $payload): object
    {
        $this->configure();

        return Snap::createTransaction($payload);
    }

    /**
     * {@inheritDoc}
     */
    public function cancel(string $transactionId): void
    {
        $this->configure();

        Transaction::cancel($transactionId);
    }

    /**
     * {@inheritDoc}
     */
    public function refund(string $transactionId, int $amount, string $reason): object
    {
        $this->configure();

        return (object) Transaction::refund($transactionId, [
            'refund_key' => 'ref_'.uniqid(),
            'amount' => $amount,
            'reason' => $reason,
        ]);
    }

    /**
     * Apply the configured Midtrans credentials to the SDK's static config.
     */
    protected function configure(): void
    {
        MidtransConfig::$serverKey = (string) config('payment.midtrans.server_key', '');
        MidtransConfig::$clientKey = (string) config('payment.midtrans.client_key', '');
        MidtransConfig::$isProduction = (bool) config('payment.midtrans.is_production', false);
        MidtransConfig::$isSanitized = (bool) config('payment.midtrans.sanitize', true);
        MidtransConfig::$is3ds = true;
    }
}

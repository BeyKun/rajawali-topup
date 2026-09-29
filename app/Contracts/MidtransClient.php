<?php

namespace App\Contracts;

/**
 * Thin seam over the Midtrans Core API `charge` call.
 *
 * The concrete SDK implementation is bound in the service container so tests
 * can swap in a fake without touching Midtrans' static SDK classes.
 */
interface MidtransClient
{
    /**
     * Create a Midtrans transaction (Core API `/v2/charge`).
     *
     * @param  array<string, mixed>  $payload
     * @return object The decoded Midtrans response (SDK returns an object).
     */
    public function charge(array $payload): object;

    /**
     * Cancel a pending Midtrans transaction (Core API `/v2/{id}/cancel`).
     *
     * Used when a customer abandons a QRIS payment so the pending charge does
     * not linger on the gateway. Cancelling an already-settled transaction is
     * rejected by Midtrans; callers treat this as best-effort, so the raw
     * gateway response is discarded and failures surface as exceptions.
     */
    public function cancel(string $transactionId): void;
}

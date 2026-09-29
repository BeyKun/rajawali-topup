<?php

namespace App\Services;

use App\Contracts\MidtransClient;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * QRIS payment gateway abstraction.
 *
 * The active provider is selected through `config('payment.provider')`.
 *
 * - `mock`     : deterministic QRIS payload built locally (local dev & tests).
 * - `midtrans` : real Midtrans Core API QRIS charge (sandbox or production)
 *                using the official `midtrans/midtrans-php` SDK.
 *
 * Both providers expose the same `createQrisInvoice()` / `verifyWebhookSignature()`
 * surface so the order flow never has to know which gateway is active.
 */
class PaymentGatewayService
{
    /** QRIS invoices are valid for 15 minutes (fallback when the gateway omits expiry). */
    private const INVOICE_TTL_MINUTES = 15;

    /** EMVCo merchant category code for "telecommunication services". */
    private const MERCHANT_CATEGORY_CODE = '4814';

    /** ISO-4217 numeric currency code for the Indonesian Rupiah. */
    private const CURRENCY_CODE_IDR = '360';

    /** ASN.1 DER tag carrying the CRC-16 checksum of the QR payload. */
    private const CRC_TAG = '63';

    /** Timezone Midtrans reports its `expiry_time` in. */
    private const MIDTRANS_TIMEZONE = 'Asia/Jakarta';

    public function __construct(private readonly MidtransClient $midtrans) {}

    /**
     * Create a dynamic QRIS invoice for the given order.
     *
     * @param  array{order_no: string, amount: float|int|string, customer_name?: string, msisdn?: string}  $params
     * @return array{qris_string: string|null, qris_url: string|null, transaction_id: string, expired_at: CarbonInterface}
     */
    public function createQrisInvoice(array $params): array
    {
        return match ($this->provider()) {
            'mock' => $this->createMockQrisInvoice($params),
            'midtrans' => $this->createMidtransQrisInvoice($params),
            default => throw new RuntimeException(
                "QRIS invoice creation is not implemented for payment provider [{$this->provider()}]."
            ),
        };
    }

    /**
     * Cancel a pending QRIS invoice so an abandoned charge is closed at the gateway.
     *
     * Cancellation is best-effort: the local order must still be expired even if
     * the gateway rejects the request (for example because the QR already
     * settled or was captured). `true` means the gateway accepted the cancel.
     *
     * @param  string  $transactionId  The gateway reference stored on the order.
     */
    public function cancelQrisInvoice(string $transactionId): bool
    {
        if ($transactionId === '') {
            return false;
        }

        if ($this->isMockProvider()) {
            return true;
        }

        if ($this->provider() !== 'midtrans') {
            return false;
        }

        try {
            $this->midtrans->cancel($transactionId);

            return true;
        } catch (Throwable $exception) {
            Log::warning('Midtrans QRIS cancellation failed.', [
                'transaction_id' => $transactionId,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Verify that an incoming payment callback was sent by the gateway.
     *
     * In mock mode every callback is accepted. For Midtrans the canonical
     * `signature_key` (SHA-512 of order_id + status_code + gross_amount +
     * server_key) is recomputed and compared in constant time.
     *
     * @param  array<array-key, mixed>  $payload
     * @param  array<array-key, mixed>  $headers
     */
    public function verifyWebhookSignature(array $payload, array $headers): bool
    {
        if ($this->isMockProvider()) {
            return true;
        }

        if ($this->provider() === 'midtrans') {
            return $this->verifyMidtransSignature($payload);
        }

        $secret = (string) config('payment.webhook_secret', '');

        if ($secret === '') {
            return false;
        }

        $signature = $this->header($headers, 'X-Callback-Signature')
            ?? $this->header($headers, 'X-Signature');

        if ($signature === null || $signature === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $this->canonicalize($payload), $secret);

        return hash_equals($expected, $signature);
    }

    /**
     * Whether the callback payload reports a settled (successful) payment.
     *
     * @param  array<array-key, mixed>  $payload
     */
    public function isPaymentSuccessful(array $payload): bool
    {
        if ($this->isMockProvider()) {
            return true;
        }

        if ($this->provider() === 'midtrans') {
            $status = strtolower((string) ($payload['transaction_status'] ?? ''));
            $fraud = strtolower((string) ($payload['fraud_status'] ?? 'accept'));

            return in_array($status, ['settlement', 'capture'], true)
                && in_array($fraud, ['accept', 'deny', 'challenge'], true);
        }

        $status = $payload['transaction_status']
            ?? $payload['status']
            ?? $payload['payment_status']
            ?? null;

        if (! is_string($status)) {
            return false;
        }

        return in_array(strtolower($status), ['settlement', 'capture', 'paid', 'success'], true);
    }

    /**
     * Build the deterministic mock invoice used in local development and tests.
     *
     * @param  array{order_no: string, amount: float|int|string, customer_name?: string, msisdn?: string}  $params
     * @return array{qris_string: string|null, qris_url: string|null, transaction_id: string, expired_at: CarbonInterface}
     */
    protected function createMockQrisInvoice(array $params): array
    {
        $orderNo = $params['order_no'];
        $amount = $this->formatAmount($params['amount']);
        $expiredAt = now()->addMinutes(self::INVOICE_TTL_MINUTES);
        $baseUrl = rtrim((string) config('payment.mock.qris_base_url'), '/');

        return [
            'qris_string' => $this->buildQrisPayload($orderNo, $amount),
            'qris_url' => "{$baseUrl}/{$orderNo}.png",
            'transaction_id' => "MOCK-QRIS-{$orderNo}",
            'expired_at' => $expiredAt,
        ];
    }

    /**
     * Create a real QRIS charge through the Midtrans Core API.
     *
    /**
     * Create a Midtrans payment invoice (Snap or Core API based on driver setting).
     *
     * @param  array{order_no: string, amount: float|int|string, customer_name?: string, msisdn?: string}  $params
     * @return array{qris_string: string|null, qris_url: string|null, transaction_id: string, expired_at: CarbonInterface}
     *
     * @throws RuntimeException When Midtrans rejects or is unreachable.
     */
    protected function createMidtransQrisInvoice(array $params): array
    {
        $driver = (string) config('payment.midtrans.driver', 'snap');

        if ($driver === 'snap') {
            return $this->createMidtransSnapInvoice($params);
        }

        return $this->createMidtransCoreInvoice($params);
    }

    /**
     * Create a Snap checkout transaction returning redirect URL and token.
     *
     * @param  array{order_no: string, amount: float|int|string, customer_name?: string, msisdn?: string}  $params
     * @return array{qris_string: string|null, qris_url: string|null, transaction_id: string, expired_at: CarbonInterface}
     */
    protected function createMidtransSnapInvoice(array $params): array
    {
        if ((string) config('payment.midtrans.server_key', '') === '') {
            throw new RuntimeException('MIDTRANS_SERVER_KEY belum dikonfigurasi.');
        }

        $orderNo = $params['order_no'];
        $amount = (int) round((float) $params['amount']);

        $payload = [
            'transaction_details' => [
                'order_id' => $orderNo,
                'gross_amount' => $amount,
            ],
            'customer_details' => [
                'first_name' => $params['customer_name'] ?? 'Pelanggan',
                'phone' => $params['msisdn'] ?? null,
            ],
            'enabled_payments' => (array) config('payment.midtrans.enabled_payments', ['gopay', 'qris', 'bni_va']),
            'custom_field1' => $params['msisdn'] ?? null,
        ];

        try {
            $response = $this->midtrans->createSnapTransaction($payload);
        } catch (Throwable $exception) {
            Log::error('Midtrans Snap transaction creation failed.', [
                'order_no' => $orderNo,
                'error' => $exception->getMessage(),
            ]);

            throw new RuntimeException(
                'Gagal membuat transaksi pembayaran melalui Midtrans Snap: '.$exception->getMessage(),
                previous: $exception,
            );
        }

        /** @var array<string, mixed> $data */
        $data = json_decode((string) json_encode($response), true) ?? [];

        $token = (string) ($data['token'] ?? '');
        $redirectUrl = (string) ($data['redirect_url'] ?? '');

        if ($token === '' || $redirectUrl === '') {
            Log::error('Midtrans Snap returned an unexpected response.', [
                'order_no' => $orderNo,
                'response' => $data,
            ]);

            throw new RuntimeException('Midtrans tidak mengembalikan token transaksi Snap yang valid.');
        }

        return [
            'qris_string' => null,
            'qris_url' => $redirectUrl,
            'transaction_id' => $token,
            'expired_at' => now()->addMinutes(self::INVOICE_TTL_MINUTES),
        ];
    }

    /**
     * Create a direct Core API QRIS / GoPay transaction.
     *
     * @param  array{order_no: string, amount: float|int|string, customer_name?: string, msisdn?: string}  $params
     * @return array{qris_string: string|null, qris_url: string|null, transaction_id: string, expired_at: CarbonInterface}
     */
    protected function createMidtransCoreInvoice(array $params): array
    {
        if ((string) config('payment.midtrans.server_key', '') === '') {
            throw new RuntimeException('MIDTRANS_SERVER_KEY belum dikonfigurasi.');
        }

        $orderNo = $params['order_no'];

        $paymentType = (string) config('payment.midtrans.payment_type', 'qris');

        /** @var array<string, mixed> $charge */
        $charge = [
            'payment_type' => $paymentType,
            'transaction_details' => [
                'order_id' => $orderNo,
                'gross_amount' => (int) round((float) $params['amount']),
            ],
            'custom_field1' => $params['msisdn'] ?? null,
        ];

        if ($paymentType === 'qris') {
            $charge['qris'] = [
                'acquirer' => (string) config('payment.midtrans.acquirer', 'gopay'),
            ];
        }

        if (! empty($params['customer_name'])) {
            $charge['customer_details'] = [
                'first_name' => $params['customer_name'],
                'phone' => $params['msisdn'] ?? null,
            ];
        }

        try {
            $response = $this->midtrans->charge($charge);
        } catch (Throwable $exception) {
            Log::error('Midtrans QRIS charge failed.', [
                'order_no' => $orderNo,
                'error' => $exception->getMessage(),
            ]);

            throw new RuntimeException(
                'Gagal membuat pembayaran QRIS melalui Midtrans: '.$exception->getMessage(),
                previous: $exception,
            );
        }

        /** @var array<string, mixed> $data */
        $data = json_decode((string) json_encode($response), true) ?? [];

        $transactionId = (string) ($data['transaction_id'] ?? '');
        $statusCode = (string) ($data['status_code'] ?? '');

        if ($transactionId === '' || ! in_array($statusCode, ['200', '201'], true)) {
            Log::error('Midtrans QRIS charge returned an unexpected response.', [
                'order_no' => $orderNo,
                'response' => $data,
            ]);

            throw new RuntimeException(
                'Midtrans menolak permintaan QRIS: '.($data['status_message'] ?? 'Unknown error')
            );
        }

        return [
            'qris_string' => isset($data['qr_string']) && is_string($data['qr_string']) && $data['qr_string'] !== ''
                ? $data['qr_string']
                : null,
            'qris_url' => $this->extractQrUrlFromActions($data),
            'transaction_id' => $transactionId,
            'expired_at' => $this->resolveMidtransExpiry($data),
        ];
    }

    /**
     * Locate the URL of the `generate-qr-code` action Midtrans returns.
     *
     * @param  array<array-key, mixed>  $data
     */
    protected function extractQrUrlFromActions(array $data): ?string
    {
        $actions = $data['actions'] ?? null;

        if (! is_array($actions)) {
            return null;
        }

        foreach ($actions as $action) {
            if (! is_array($action)) {
                continue;
            }

            if (($action['name'] ?? null) === 'generate-qr-code' && is_string($action['url'] ?? null)) {
                return $action['url'];
            }
        }

        return null;
    }

    /**
     * Resolve the QRIS expiry moment reported by Midtrans.
     *
     * Midtrans returns `expiry_time` as a naive wall-clock string in Jakarta
     * time (e.g. "2026-09-28 12:25:47"). It is parsed in that timezone and then
     * stored in the application timezone (UTC); otherwise the expiry is
     * silently shifted by the UTC+7 offset and unpaid orders never expire.
     *
     * @param  array<array-key, mixed>  $data
     */
    protected function resolveMidtransExpiry(array $data): CarbonInterface
    {
        $raw = $data['expiry_time'] ?? null;

        if (is_string($raw) && $raw !== '') {
            try {
                return CarbonImmutable::parse($raw, self::MIDTRANS_TIMEZONE)
                    ->setTimezone((string) config('app.timezone', 'UTC'));
            } catch (Throwable) {
                // Fall through to the default TTL below.
            }
        }

        return now()->addMinutes(self::INVOICE_TTL_MINUTES);
    }

    /**
     * Verify the Midtrans `signature_key` for a notification payload.
     *
     * @param  array<array-key, mixed>  $payload
     */
    protected function verifyMidtransSignature(array $payload): bool
    {
        $serverKey = (string) config('payment.midtrans.server_key', '');
        $orderId = $payload['order_id'] ?? null;
        $statusCode = $payload['status_code'] ?? null;
        $grossAmount = $payload['gross_amount'] ?? null;
        $signature = $payload['signature_key'] ?? null;

        if ($serverKey === '' || ! is_string($orderId) || ! is_string($statusCode)
            || ! is_string($grossAmount) || ! is_string($signature)) {
            return false;
        }

        $expected = hash('sha512', $orderId.$statusCode.$grossAmount.$serverKey);

        return hash_equals($expected, $signature);
    }

    /**
     * Assemble a well-formed EMVCo TLV QRIS payload with a valid CRC-16 trailer.
     */
    protected function buildQrisPayload(string $merchantRef, string $amount): string
    {
        $merchantName = (string) config('payment.mock.merchant_name', 'RAJAWALI TOPUP');
        $merchantCity = (string) config('payment.mock.merchant_city', 'JAKARTA');

        $merchantAccount = $this->tlv('00', 'ID.CO.RAJAWALITOPUP.QRIS')
            .$this->tlv('02', $merchantRef);

        $payload = $this->tlv('00', '01')
            .$this->tlv('01', '12')
            .$this->tlv('26', $merchantAccount)
            .$this->tlv('52', self::MERCHANT_CATEGORY_CODE)
            .$this->tlv('53', self::CURRENCY_CODE_IDR)
            .$this->tlv('54', $amount)
            .$this->tlv('58', 'ID')
            .$this->tlv('59', $merchantName)
            .$this->tlv('60', $merchantCity)
            .$this->tlv('62', $this->tlv('01', $merchantRef));

        $payloadWithCrcPlaceholder = $payload.self::CRC_TAG.'04';

        return $payloadWithCrcPlaceholder.$this->crc16($payloadWithCrcPlaceholder);
    }

    /**
     * Encode a single EMVCo TLV field: 2-digit tag, 2-digit length, value.
     */
    protected function tlv(string $tag, string $value): string
    {
        return $tag.str_pad((string) strlen($value), 2, '0', STR_PAD_LEFT).$value;
    }

    /**
     * Compute the EMVCo CRC-16/CCITT-FALSE checksum of a payload, upper-cased hex.
     */
    protected function crc16(string $payload): string
    {
        $crc = 0xFFFF;
        $length = strlen($payload);

        for ($index = 0; $index < $length; $index++) {
            $crc ^= ord($payload[$index]) << 8;

            for ($bit = 0; $bit < 8; $bit++) {
                $crc = ($crc & 0x8000) !== 0
                    ? (($crc << 1) ^ 0x1021) & 0xFFFF
                    : ($crc << 1) & 0xFFFF;
            }
        }

        return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }

    /**
     * Render the transaction amount as a fixed 2-decimal EMVCo amount string.
     */
    protected function formatAmount(float|int|string $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }

    /**
     * Canonicalize a webhook payload before signing. Key order is preserved so
     * the gateway and this service hash byte-identical strings.
     *
     * @param  array<array-key, mixed>  $payload
     */
    protected function canonicalize(array $payload): string
    {
        $encoded = json_encode($payload);

        return $encoded === false ? '' : $encoded;
    }

    /**
     * Read a header value, tolerating both scalar and multi-value header bags.
     *
     * @param  array<array-key, mixed>  $headers
     */
    protected function header(array $headers, string $name): ?string
    {
        $needle = strtolower($name);

        foreach ($headers as $key => $value) {
            if (strtolower((string) $key) !== $needle) {
                continue;
            }

            if (is_array($value)) {
                $first = reset($value);

                return $first === false ? null : (string) $first;
            }

            return $value === null ? null : (string) $value;
        }

        return null;
    }

    /**
     * Resolve the active payment provider name.
     */
    protected function provider(): string
    {
        return strtolower((string) config('payment.provider', 'mock'));
    }

    /**
     * Whether the mock provider (no external network calls) is active.
     */
    protected function isMockProvider(): bool
    {
        return $this->provider() === 'mock';
    }
}

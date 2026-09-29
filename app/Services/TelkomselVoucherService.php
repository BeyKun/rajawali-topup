<?php

namespace App\Services;

use App\Models\TelkomselApiLog;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * HTTP client for the official Telkomsel physical-voucher API (check & redeem).
 *
 * Every outbound call is mirrored into the `telkomsel_api_logs` audit table with
 * the secret voucher code (HRN) masked. When `config('telkomsel.mock')` is true the
 * service short-circuits the network entirely and returns deterministic fake
 * responses so local development and the test suite never burn real vouchers.
 */
class TelkomselVoucherService
{
    private const DEFAULT_BASE_URL = 'https://www.telkomsel.com/api/voucher';

    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    /** /check statusCode: voucher is available. */
    private const CHECK_STATUS_AVAILABLE = 1;

    /** /check statusCode: voucher has already been used. */
    private const CHECK_STATUS_USED = 3;

    /** /check statusCode: serial number is invalid. */
    private const CHECK_STATUS_INVALID = -1;

    /** Number of trailing characters kept when masking a secret value. */
    private const MASK_VISIBLE_CHARS = 4;

    /** Payload keys whose values are secrets and must never be persisted in clear text. */
    private const SENSITIVE_KEYS = ['hrn', 'sn'];

    /** Fallback messages for the Telkomsel /check statusCode values. */
    private const CHECK_STATUS_MESSAGES = [
        self::CHECK_STATUS_AVAILABLE => 'Tersedia',
        self::CHECK_STATUS_USED => 'Sudah Digunakan',
        self::CHECK_STATUS_INVALID => 'Voucher Invalid',
    ];

    /** Fallback descriptions for the Telkomsel /redeem `data.code` values. */
    private const REDEEM_CODE_DESCRIPTIONS = [
        '00' => 'Success',
        '15' => 'VoucherAlreadyUsed',
        '3023' => 'HRNNotFound',
        'Error' => 'Error',
    ];

    /**
     * Check whether a physical voucher serial number is valid and still unused.
     *
     * @return array{is_valid: bool, status_code: int, status_message: string, serial_number: string, name: string, description: string, validity: string, expired_date: string, region: string, raw: array<array-key, mixed>}
     */
    public function checkVoucher(string $serialNumber): array
    {
        $serialNumber = trim($serialNumber);

        /** @var array<string, mixed> $payload */
        $payload = [
            'recaptcharesponse' => '',
            'serialNumber' => $serialNumber,
            'voucher_type' => 'voucher',
        ];

        $startTime = microtime(true);

        if ($this->isMockEnabled()) {
            $data = $this->mockCheckResponse($serialNumber);

            $this->logApiCall('check', null, null, $payload, 200, $data, $this->elapsedMs($startTime));

            return $this->normalizeCheckResponse($data, $serialNumber);
        }

        try {
            $response = $this->post('/check', $payload);
        } catch (ConnectionException $exception) {
            return $this->handleCheckConnectionFailure($payload, $exception, $startTime);
        }

        $data = $this->toArray($response->json());

        $this->logApiCall('check', null, null, $payload, $response->status(), $data, $this->elapsedMs($startTime));

        return $this->normalizeCheckResponse($data, $serialNumber);
    }

    /**
     * Redeem a physical voucher's HRN onto the destination MSISDN.
     *
     * @return array{success: bool, code: string, description: string, raw: array<array-key, mixed>}
     */
    public function redeemVoucher(string $hrn, string $msisdn, ?int $orderId = null, ?int $voucherId = null): array
    {
        $cleanMsisdn = $this->formatMsisdnTo62($msisdn);

        /** @var array<string, mixed> $payload */
        $payload = [
            'recaptcharesponse' => '',
            'hrn' => trim($hrn),
            'msisdn' => $cleanMsisdn,
            'no-captcha' => true,
            'tag' => '',
            'voucher_type' => 'voucher',
        ];

        $startTime = microtime(true);

        if ($this->isMockEnabled()) {
            $data = $this->mockRedeemResponse($cleanMsisdn);

            $this->logApiCall('redeem', $orderId, $voucherId, $payload, 200, $data, $this->elapsedMs($startTime));

            return $this->normalizeRedeemResponse($data, true);
        }

        try {
            $response = $this->post('/redeem', $payload);
        } catch (ConnectionException $exception) {
            return $this->handleRedeemConnectionFailure($payload, $exception, $startTime, $orderId, $voucherId);
        }

        $data = $this->toArray($response->json());

        $this->logApiCall('redeem', $orderId, $voucherId, $payload, $response->status(), $data, $this->elapsedMs($startTime));

        return $this->normalizeRedeemResponse($data, $response->successful());
    }

    /**
     * Normalize an Indonesian phone number into E.164-style `628xxx` format.
     *
     * Strips every non-digit character and converts local prefixes (`08…`) or
     * bare prefixes (`8…`) into the international `62…` form.
     */
    public function formatMsisdnTo62(string $phone): string
    {
        $digits = (string) preg_replace('/[^0-9]/', '', $phone);

        if (str_starts_with($digits, '08')) {
            return '62'.substr($digits, 1);
        }

        if (str_starts_with($digits, '8')) {
            return '62'.$digits;
        }

        return $digits;
    }

    /**
     * Build the normalized result from a raw /check response body.
     *
     * @param  array<array-key, mixed>  $data
     * @return array{is_valid: bool, status_code: int, status_message: string, serial_number: string, name: string, description: string, validity: string, expired_date: string, region: string, raw: array<array-key, mixed>}
     */
    protected function normalizeCheckResponse(array $data, string $serialNumber): array
    {
        $statusCode = (int) ($data['statusCode'] ?? self::CHECK_STATUS_INVALID);

        return [
            'is_valid' => $statusCode === self::CHECK_STATUS_AVAILABLE,
            'status_code' => $statusCode,
            'status_message' => (string) ($data['statusMessage'] ?? self::CHECK_STATUS_MESSAGES[$statusCode] ?? 'Unknown'),
            'serial_number' => (string) ($data['serialNumber'] ?? $serialNumber),
            'name' => (string) ($data['name'] ?? ''),
            'description' => (string) ($data['description'] ?? ''),
            'validity' => (string) ($data['validity'] ?? ''),
            'expired_date' => (string) ($data['expired_date'] ?? ''),
            'region' => (string) ($data['region'] ?? ''),
            'raw' => $data,
        ];
    }

    /**
     * Build the normalized result from a raw /redeem response body.
     *
     * @param  array<array-key, mixed>  $data
     * @return array{success: bool, code: string, description: string, raw: array<array-key, mixed>}
     */
    protected function normalizeRedeemResponse(array $data, bool $httpSuccessful): array
    {
        $inner = $this->toArray($data['data'] ?? null);
        $code = (string) ($inner['code'] ?? $data['message'] ?? 'ERROR');

        $isSuccess = $httpSuccessful
            && ($data['status'] ?? false) === true
            && $code === '00';

        return [
            'success' => $isSuccess,
            'code' => $code,
            'description' => (string) ($inner['description'] ?? $data['error'] ?? self::REDEEM_CODE_DESCRIPTIONS[$code] ?? 'Unknown error'),
            'raw' => $data,
        ];
    }

    /**
     * Deterministic fake payload used instead of a real /check call in mock mode.
     *
     * @return array<array-key, mixed>
     */
    protected function mockCheckResponse(string $serialNumber): array
    {
        return [
            'mock' => true,
            'statusCode' => self::CHECK_STATUS_AVAILABLE,
            'statusMessage' => 'Tersedia',
            'serialNumber' => $serialNumber,
            'name' => 'Internet',
            'description' => '4 GB & 3 GB Local Internet, 20 Menit Telepon Sesama Telkomsel (MOCK)',
            'validity' => '5',
            'expired_date' => '15-03-2027 14:55',
            'used_by' => '',
            'usedDateTime' => '00-00-0000 00:00:00',
            'region' => 'SIKKA',
        ];
    }

    /**
     * Deterministic fake payload used instead of a real /redeem call in mock mode.
     *
     * @return array<array-key, mixed>
     */
    protected function mockRedeemResponse(string $msisdn): array
    {
        return [
            'mock' => true,
            'status' => true,
            'message' => 200,
            'data' => [
                'code' => '00',
                'description' => 'Success',
                'msisdn' => $msisdn,
                'sn' => '',
                'region' => '',
                'validity' => '',
                'title' => '',
            ],
        ];
    }

    /**
     * @param  array<array-key, mixed>  $payload
     * @return array{is_valid: bool, status_code: int, status_message: string, serial_number: string, name: string, description: string, validity: string, expired_date: string, region: string, raw: array<array-key, mixed>}
     */
    protected function handleCheckConnectionFailure(array $payload, ConnectionException $exception, float $startTime): array
    {
        $data = [
            'error' => 'Connection error',
            'message' => $exception->getMessage(),
        ];

        Log::warning('Telkomsel check voucher request failed', ['error' => $exception->getMessage()]);

        $this->logApiCall('check', null, null, $payload, 0, $data, $this->elapsedMs($startTime));

        return [
            'is_valid' => false,
            'status_code' => self::CHECK_STATUS_INVALID,
            'status_message' => 'Connection Error: '.$exception->getMessage(),
            'serial_number' => (string) ($payload['serialNumber'] ?? ''),
            'name' => '',
            'description' => '',
            'validity' => '',
            'expired_date' => '',
            'region' => '',
            'raw' => $data,
        ];
    }

    /**
     * @param  array<array-key, mixed>  $payload
     * @return array{success: bool, code: string, description: string, raw: array<array-key, mixed>}
     */
    protected function handleRedeemConnectionFailure(array $payload, ConnectionException $exception, float $startTime, ?int $orderId, ?int $voucherId): array
    {
        $data = [
            'error' => 'Connection error',
            'message' => $exception->getMessage(),
        ];

        Log::warning('Telkomsel redeem voucher request failed', ['error' => $exception->getMessage()]);

        $this->logApiCall('redeem', $orderId, $voucherId, $payload, 0, $data, $this->elapsedMs($startTime));

        return [
            'success' => false,
            'code' => 'ERROR',
            'description' => 'Connection Error: '.$exception->getMessage(),
            'raw' => $data,
        ];
    }

    /**
     * Persist an audit record for a single Telkomsel API interaction.
     *
     * Request and response payloads are scrubbed of secrets (HRN / SN) before
     * they are written so the audit table never stores the redeemable code.
     *
     * @param  array<array-key, mixed>  $requestPayload
     * @param  array<array-key, mixed>|null  $responsePayload
     */
    protected function logApiCall(string $endpoint, ?int $orderId, ?int $voucherId, array $requestPayload, int $responseCode, ?array $responsePayload, int $durationMs): void
    {
        TelkomselApiLog::create([
            'order_id' => $orderId,
            'voucher_id' => $voucherId,
            'endpoint' => $endpoint,
            'request_payload' => $this->maskSensitivePayload($requestPayload),
            'response_code' => $responseCode,
            'response_payload' => $responsePayload === null ? null : $this->maskSensitivePayload($responsePayload),
            'duration_ms' => $durationMs,
        ]);
    }

    /**
     * Recursively mask every sensitive key inside a payload.
     *
     * @param  array<array-key, mixed>  $payload
     * @return array<array-key, mixed>
     */
    protected function maskSensitivePayload(array $payload): array
    {
        $masked = [];

        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $masked[$key] = $this->maskSensitivePayload($value);

                continue;
            }

            if (is_string($key) && is_string($value) && in_array(strtolower($key), self::SENSITIVE_KEYS, true)) {
                $masked[$key] = $this->maskSensitiveValue($value);

                continue;
            }

            $masked[$key] = $value;
        }

        return $masked;
    }

    /**
     * Mask a secret value, keeping only its last few characters (e.g. `****8001`).
     */
    protected function maskSensitiveValue(string $value): string
    {
        return str_repeat('*', self::MASK_VISIBLE_CHARS).substr($value, -self::MASK_VISIBLE_CHARS);
    }

    /**
     * Send a JSON request to the configured Telkomsel endpoint.
     *
     * @param  array<array-key, mixed>  $payload
     */
    protected function post(string $path, array $payload): Response
    {
        return Http::withHeaders([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'User-Agent' => self::USER_AGENT,
        ])
            ->timeout((int) config('telkomsel.timeout', 15))
            ->post($this->baseUrl().$path, $payload);
    }

    /**
     * Resolve the base URL at call time so tests can override the config.
     */
    protected function baseUrl(): string
    {
        return rtrim((string) config('telkomsel.base_url', self::DEFAULT_BASE_URL), '/');
    }

    /**
     * Whether mock mode is active (no real network calls).
     */
    protected function isMockEnabled(): bool
    {
        return (bool) config('telkomsel.mock', true);
    }

    /**
     * Normalize a decoded HTTP body (or a mock payload) into an array.
     *
     * @return array<array-key, mixed>
     */
    protected function toArray(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    /**
     * Milliseconds elapsed since the given start timestamp.
     */
    protected function elapsedMs(float $startTime): int
    {
        return (int) round((microtime(true) - $startTime) * 1000);
    }
}

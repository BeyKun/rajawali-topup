<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class VoucherOcrService
{
    /**
     * Scan a voucher photo using the Self-Hosted RapidOCR service.
     *
     * @param  UploadedFile|string  $file  Uploaded file instance or absolute file path
     * @return array{
     *     success: bool,
     *     vouchers: array<int, array{
     *         id: string,
     *         serial_number: string,
     *         hrn: string,
     *         sell_price: int,
     *         confidence: float,
     *         serial_status: string,
     *         hrn_status: string
     *     }>,
     *     raw_texts: array<int, string>,
     *     message?: string
     * }
     */
    public function scanPhoto(UploadedFile|string $file): array
    {
        $baseUrl = rtrim((string) config('services.ocr.url', 'http://ocr-service:8000'), '/');
        $timeout = (int) config('services.ocr.timeout', 30);

        try {
            $request = Http::timeout($timeout);

            if ($file instanceof UploadedFile) {
                $filename = $file->getClientOriginalName();
                $contents = file_get_contents($file->getRealPath());
            } else {
                if (! file_exists($file)) {
                    throw new RuntimeException("File not found: {$file}");
                }
                $filename = basename($file);
                $contents = file_get_contents($file);
            }

            $response = $request->attach('file', $contents, $filename)
                ->post("{$baseUrl}/extract-vouchers");

            if (! $response->successful()) {
                Log::warning('OCR Service HTTP error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                throw new RuntimeException("OCR Service returned HTTP {$response->status()}: {$response->body()}");
            }

            $data = $response->json();
            $rawVouchers = $data['vouchers'] ?? [];
            $rawTexts = $data['raw_texts'] ?? [];

            $formattedVouchers = [];
            foreach ($rawVouchers as $idx => $v) {
                $serial = preg_replace('/\D/', '', (string) ($v['serial_number'] ?? ''));
                if (strlen($serial) > 12) {
                    $serial = substr($serial, 0, 12);
                }
                $hrn = preg_replace('/\D/', '', (string) ($v['hrn'] ?? ''));

                // Serial hanya menggunakan 12 digit
                $serialStatus = strlen($serial) === 12 ? 'valid' : 'warning';
                $hrnStatus = (strlen($hrn) >= 14 && strlen($hrn) <= 20) ? 'valid' : 'warning';

                $formattedVouchers[] = [
                    'id' => 'v-ocr-'.uniqid().'-'.$idx,
                    'serial_number' => $serial,
                    'hrn' => $hrn,
                    'sell_price' => 0,
                    'confidence' => (float) ($v['confidence'] ?? 0.9),
                    'serial_status' => $serialStatus,
                    'hrn_status' => $hrnStatus,
                ];
            }

            return [
                'success' => true,
                'vouchers' => $formattedVouchers,
                'raw_texts' => $rawTexts,
            ];
        } catch (Throwable $e) {
            Log::error('OCR Scan failed: '.$e->getMessage(), [
                'exception' => $e,
                'url' => $baseUrl,
            ]);

            throw new RuntimeException('Layanan OCR lokal sedang tidak tersedia atau mengalami kendala: '.$e->getMessage(), 0, $e);
        }
    }
}

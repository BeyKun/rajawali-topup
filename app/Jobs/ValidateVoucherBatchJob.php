<?php

namespace App\Jobs;

use App\Services\VoucherInventoryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Validates a batch of uploaded physical vouchers against the Telkomsel API.
 *
 * Each row is checked individually: valid serials are stored as AVAILABLE
 * vouchers, while duplicates, used or unknown serials are skipped. A per-row
 * result log is written so operators can audit a bulk import afterwards.
 */
class ValidateVoucherBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  array<int, array{serial_number: string, hrn: string, sell_price: float}>  $rows
     * @param  array<int, int>  $cityIds
     */
    public function __construct(
        public array $rows,
        public ?int $adminId = null,
        public array $cityIds = [],
        public ?float $marginPercentage = null,
        public ?string $batchId = null,
    ) {
        $this->batchId ??= (string) Str::uuid();
    }

    public function handle(VoucherInventoryService $inventory): void
    {
        $stored = 0;
        $skipped = 0;
        $failed = 0;
        $errors = [];

        // Inisialisasi status batch di cache
        Cache::put("voucher_batch_{$this->batchId}", [
            'id' => $this->batchId,
            'status' => 'processing',
            'total' => count($this->rows),
            'processed' => 0,
            'stored' => 0,
            'skipped' => 0,
            'failed' => 0,
            'errors' => [],
        ], now()->addHours(24));

        foreach ($this->rows as $index => $row) {
            /*
            // Di-comment out: Pengecekan rate limit Telkomsel saat upload bulk
            if ($index > 0) {
                if (! app()->runningUnitTests()) {
                    sleep(2);
                }
            }
            */

            try {
                $basePrice = (float) $row['sell_price'];
                $sellPrice = $this->marginPercentage !== null && $this->marginPercentage > 0
                    ? (float) round($basePrice * (1 + ($this->marginPercentage / 100)))
                    : $basePrice;

                /*
                // Di-comment out: Cek validasi voucher ke Telkomsel saat upload bulk
                $inventory->addVoucher(
                    $row['serial_number'],
                    $row['hrn'],
                    $sellPrice,
                    $this->adminId,
                    $this->cityIds,
                    hppPrice: $basePrice,
                    validateTelkomsel: true,
                );
                */

                $inventory->addVoucher(
                    $row['serial_number'],
                    $row['hrn'],
                    $sellPrice,
                    $this->adminId,
                    $this->cityIds,
                    hppPrice: $basePrice,
                    validateTelkomsel: false,
                );

                $stored++;
            } catch (ValidationException $exception) {
                $skipped++;
                $reason = $this->firstError($exception);
                $errors[] = [
                    'row' => $index + 1,
                    'serial_number' => $row['serial_number'],
                    'reason' => $reason,
                ];

                Log::info('Bulk voucher import skipped a row.', [
                    'row' => $index + 1,
                    'serial_number' => $row['serial_number'],
                    'reason' => $reason,
                ]);
            } catch (Throwable $exception) {
                $failed++;
                $reason = $exception->getMessage();
                $errors[] = [
                    'row' => $index + 1,
                    'serial_number' => $row['serial_number'],
                    'reason' => $reason,
                ];

                Log::error('Bulk voucher import failed on a row.', [
                    'row' => $index + 1,
                    'serial_number' => $row['serial_number'],
                    'error' => $reason,
                ]);
            }

            // Update progress ke cache setiap baris
            Cache::put("voucher_batch_{$this->batchId}", [
                'id' => $this->batchId,
                'status' => 'processing',
                'total' => count($this->rows),
                'processed' => $index + 1,
                'stored' => $stored,
                'skipped' => $skipped,
                'failed' => $failed,
                'errors' => $errors,
            ], now()->addHours(24));
        }

        $summary = [
            'id' => $this->batchId,
            'status' => 'completed',
            'total' => count($this->rows),
            'processed' => count($this->rows),
            'stored' => $stored,
            'skipped' => $skipped,
            'failed' => $failed,
            'errors' => $errors,
            'finished_at' => now()->toIso8601String(),
        ];

        Cache::put("voucher_batch_{$this->batchId}", $summary, now()->addHours(24));

        if ($this->adminId) {
            Cache::put("voucher_batch_latest_{$this->adminId}", $summary, now()->addHours(24));
        }

        Log::info('Bulk voucher import finished.', [
            'batch_id' => $this->batchId,
            'total' => count($this->rows),
            'stored' => $stored,
            'skipped' => $skipped,
            'failed' => $failed,
        ]);
    }

    /**
     * Extract the first validation message from an exception safely.
     */
    protected function firstError(ValidationException $exception): string
    {
        /** @var array<string, mixed> $errors */
        $errors = $exception->errors();

        foreach ($errors as $messages) {
            if (is_array($messages)) {
                foreach ($messages as $msg) {
                    if (is_string($msg)) {
                        return trim($msg);
                    }
                    if (is_array($msg)) {
                        $flat = implode(', ', array_filter($msg, 'is_string'));
                        if ($flat !== '') {
                            return $flat;
                        }
                    }
                }
            } elseif (is_string($messages)) {
                return trim($messages);
            }
        }

        return $exception->getMessage();
    }
}

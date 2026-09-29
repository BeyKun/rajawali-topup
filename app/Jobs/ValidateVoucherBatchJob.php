<?php

namespace App\Jobs;

use App\Services\VoucherInventoryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
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
     */
    public function __construct(public array $rows, public ?int $adminId = null) {}

    public function handle(VoucherInventoryService $inventory): void
    {
        $stored = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($this->rows as $index => $row) {
            try {
                $inventory->addVoucher(
                    $row['serial_number'],
                    $row['hrn'],
                    $row['sell_price'],
                    $this->adminId,
                );

                $stored++;
            } catch (ValidationException $exception) {
                $skipped++;

                Log::info('Bulk voucher import skipped a row.', [
                    'row' => $index + 1,
                    'serial_number' => $row['serial_number'],
                    'reason' => $this->firstError($exception),
                ]);
            } catch (Throwable $exception) {
                $failed++;

                Log::error('Bulk voucher import failed on a row.', [
                    'row' => $index + 1,
                    'serial_number' => $row['serial_number'],
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        Log::info('Bulk voucher import finished.', [
            'total' => count($this->rows),
            'stored' => $stored,
            'skipped' => $skipped,
            'failed' => $failed,
        ]);
    }

    /**
     * Extract the first validation message from an exception.
     */
    protected function firstError(ValidationException $exception): string
    {
        /** @var array<string, array<int, string>> $errors */
        $errors = $exception->errors();

        foreach ($errors as $messages) {
            if ($messages !== []) {
                return (string) $messages[0];
            }
        }

        return $exception->getMessage();
    }
}

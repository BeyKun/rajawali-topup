<?php

namespace App\Console\Commands;

use App\Exceptions\OutOfStockException;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Test-support command that attempts to place a single order.
 *
 * It is used by the automated concurrency test to fire many truly parallel
 * processes at the same voucher pool. A file-based barrier lets every worker
 * reach the critical section at the same moment so the pessimistic row lock in
 * {@see OrderService::createOrder()} is genuinely exercised.
 *
 * The command is hidden and refuses to run outside local/testing environments.
 */
class ConcurrencyAttemptOrder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rajawali:concurrency-attempt
        {product : The product id to buy}
        {user : The customer user id placing the order}
        {--barrier= : Directory used to synchronize the worker pool}
        {--participants=0 : Number of workers that must reach the barrier}
        {--id=0 : This worker\'s identifier}
        {--result= : File the JSON result is written to}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Testing helper: attempt a single order and report the outcome as JSON';

    /**
     * The command is an internal testing tool.
     *
     * @var bool
     */
    protected $hidden = true;

    /**
     * Execute the console command.
     */
    public function handle(OrderService $orders): int
    {
        if (! $this->laravel->environment(['local', 'testing'])) {
            $this->components->error('This command can only run in local or testing environments.');

            return self::FAILURE;
        }

        $this->waitForBarrier();

        try {
            $order = $orders->createOrder(
                User::query()->findOrFail((int) $this->argument('user')),
                (int) $this->argument('product'),
                '082233456777',
            );

            $this->writeResult([
                'result' => 'success',
                'order_no' => $order->order_no,
            ]);

            return self::SUCCESS;
        } catch (OutOfStockException $exception) {
            $this->writeResult([
                'result' => 'out_of_stock',
                'message' => $exception->getMessage(),
            ]);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->writeResult([
                'result' => 'error',
                'message' => $exception->getMessage(),
            ]);

            return self::FAILURE;
        }
    }

    /**
     * Block until every expected worker has signalled readiness.
     *
     * Workers write a `ready-<id>` marker file and then poll the directory until
     * the expected number of markers exists (or a 15s safety deadline elapses).
     */
    protected function waitForBarrier(): void
    {
        $directory = $this->option('barrier');

        if (! is_string($directory) || $directory === '') {
            return;
        }

        $expected = (int) $this->option('participants');

        file_put_contents($this->barrierPath($directory, 'ready-'.(int) $this->option('id')), '1');

        $deadline = microtime(true) + 15;

        while (count((array) glob($this->barrierPath($directory, 'ready-*'))) < $expected) {
            if (microtime(true) >= $deadline) {
                return;
            }

            usleep(5_000);
        }
    }

    /**
     * Persist the JSON outcome for the parent test to read back.
     *
     * @param  array<string, mixed>  $result
     */
    protected function writeResult(array $result): void
    {
        $encoded = (string) json_encode($result);
        $resultPath = $this->option('result');

        if (is_string($resultPath) && $resultPath !== '') {
            file_put_contents($resultPath, $encoded);
        }

        $this->line($encoded);
    }

    /**
     * Build a path inside the barrier directory.
     */
    protected function barrierPath(string $directory, string $file): string
    {
        return rtrim($directory, '\\/').DIRECTORY_SEPARATOR.$file;
    }
}

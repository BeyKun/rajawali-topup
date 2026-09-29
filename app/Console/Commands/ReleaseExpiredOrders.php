<?php

namespace App\Console\Commands;

use App\Services\OrderService;
use Illuminate\Console\Command;

/**
 * Releases vouchers held by unpaid orders whose QRIS payment window has closed.
 */
class ReleaseExpiredOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:release-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Release vouchers reserved by unpaid orders whose QRIS payment window has expired';

    /**
     * Execute the console command.
     */
    public function handle(OrderService $orders): int
    {
        $released = $orders->releaseExpiredOrders();

        $this->info("Released {$released} expired order(s).");

        return self::SUCCESS;
    }
}

<?php

use App\Enums\VoucherStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Process\Pool;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;

/**
 * Point the test process at a dedicated PostgreSQL database.
 *
 * SQLite cannot demonstrate row-level locking, so the concurrency scenario
 * requires PostgreSQL (which is what production runs on).
 */
function useConcurrencyDatabase(): void
{
    config([
        'database.default' => 'pgsql',
        'database.connections.pgsql.host' => '127.0.0.1',
        'database.connections.pgsql.port' => '5432',
        'database.connections.pgsql.database' => 'rajawali_topup_testing',
        'database.connections.pgsql.username' => 'postgres',
        'database.connections.pgsql.password' => 'root',
    ]);

    DB::purge('pgsql');
    DB::setDefaultConnection('pgsql');
}

beforeEach(function () {
    useConcurrencyDatabase();

    try {
        DB::connection('pgsql')->getPdo();
    } catch (Throwable $exception) {
        $this->markTestSkipped('PostgreSQL is not available: '.$exception->getMessage());
    }

    Artisan::call('migrate:fresh', ['--force' => true]);
});

test('only one of ten parallel orders gets the last available voucher', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['is_active' => true, 'sell_price' => 25000]);
    Voucher::factory()->create([
        'product_id' => $product->id,
        'status' => VoucherStatus::Available,
    ]);

    $participants = 10;
    $workspace = storage_path('framework/testing/concurrency-'.uniqid());
    mkdir($workspace, 0777, true);

    $environment = [
        'DB_CONNECTION' => 'pgsql',
        'DB_HOST' => '127.0.0.1',
        'DB_PORT' => '5432',
        'DB_DATABASE' => 'rajawali_topup_testing',
        'DB_USERNAME' => 'postgres',
        'DB_PASSWORD' => 'root',
        'TELKOMSEL_MOCK' => 'true',
        'PAYMENT_PROVIDER' => 'mock',
        'CACHE_STORE' => 'array',
        'QUEUE_CONNECTION' => 'sync',
        'SESSION_DRIVER' => 'array',
    ];

    $results = Process::pool(function (Pool $pool) use ($participants, $user, $product, $workspace, $environment): void {
        for ($id = 0; $id < $participants; $id++) {
            $pool->as((string) $id)
                ->path(base_path())
                ->env($environment)
                ->timeout(60)
                ->command([
                    PHP_BINARY,
                    'artisan',
                    'rajawali:concurrency-attempt',
                    (string) $product->id,
                    (string) $user->id,
                    '--barrier='.$workspace,
                    '--participants='.$participants,
                    '--id='.$id,
                    '--result='.$workspace.DIRECTORY_SEPARATOR.'result-'.$id.'.json',
                ]);
        }
    })->run();

    $outcomes = [];

    for ($id = 0; $id < $participants; $id++) {
        $file = $workspace.DIRECTORY_SEPARATOR.'result-'.$id.'.json';

        if (! is_file($file)) {
            $outcomes[] = 'missing';

            continue;
        }

        /** @var array{result: string} $decoded */
        $decoded = json_decode((string) file_get_contents($file), true);
        $outcomes[] = $decoded['result'];
    }

    $successes = array_filter($outcomes, fn (string $outcome): bool => $outcome === 'success');
    $outOfStock = array_filter($outcomes, fn (string $outcome): bool => $outcome === 'out_of_stock');

    expect($outcomes)->not->toContain('error')
        ->and($outcomes)->not->toContain('missing')
        ->and($outcomes)->toHaveCount($participants)
        ->and($successes)->toHaveCount(1)
        ->and($outOfStock)->toHaveCount($participants - 1);

    expect(Order::query()->count())->toBe(1);

    $voucher = Voucher::query()->sole();

    expect($voucher->status)->toBe(VoucherStatus::Reserved)
        ->and(Voucher::query()->where('status', VoucherStatus::Available)->count())->toBe(0);

    collect((array) glob($workspace.DIRECTORY_SEPARATOR.'*'))->each(fn (string $path) => @unlink($path));
    @rmdir($workspace);
});

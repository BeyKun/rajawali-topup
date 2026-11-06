<?php

use App\Jobs\ValidateVoucherBatchJob;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->withoutVite();
    config(['telkomsel.mock' => true]);
    $this->admin = User::factory()->admin()->create();
});

test('bulk store creates batch in cache and returns batch_id in json response', function () {
    $response = $this->actingAs($this->admin)
        ->postJson(route('admin.vouchers.bulk.store'), [
            'rows' => [
                [
                    'serial_number' => '300338120354',
                    'hrn' => '71125613431848001',
                    'sell_price' => 25000,
                ],
            ],
        ]);

    $response->assertOk()
        ->assertJsonStructure([
            'success',
            'batch_id',
            'count',
        ]);

    $batchId = $response->json('batch_id');
    expect(Cache::has("voucher_batch_{$batchId}"))->toBeTrue();

    // Dalam environment test QUEUE_CONNECTION=sync, job langsung selesai
    $batchData = Cache::get("voucher_batch_{$batchId}");
    expect($batchData['status'])->toBe('completed')
        ->and($batchData['stored'])->toBe(1)
        ->and($batchData['total'])->toBe(1)
        ->and($batchData['errors'])->toBeEmpty();
});

test('batch status endpoint returns progress and error report for invalid vouchers', function () {
    $batchId = 'test-batch-uuid';

    Cache::put("voucher_batch_{$batchId}", [
        'id' => $batchId,
        'status' => 'completed',
        'total' => 4,
        'processed' => 4,
        'stored' => 1,
        'skipped' => 3,
        'failed' => 0,
        'errors' => [
            [
                'row' => 1,
                'serial_number' => '500730332927',
                'reason' => 'Voucher tidak dapat ditambahkan: Sudah Digunakan (kode 3).',
            ],
            [
                'row' => 2,
                'serial_number' => '500730332928',
                'reason' => 'Voucher tidak dapat ditambahkan: Voucher Invalid (kode -1).',
            ],
            [
                'row' => 3,
                'serial_number' => '500730332929',
                'reason' => 'Voucher tidak dapat ditambahkan: Voucher Invalid (kode -1).',
            ],
        ],
    ]);

    $response = $this->actingAs($this->admin)
        ->getJson(route('admin.vouchers.batch-status', ['batchId' => $batchId]));

    $response->assertOk()
        ->assertJson([
            'id' => $batchId,
            'status' => 'completed',
            'total' => 4,
            'stored' => 1,
            'skipped' => 3,
        ])
        ->assertJsonCount(3, 'errors');
});

test('latest batch status endpoint returns the most recent batch for the admin', function () {
    Cache::put("voucher_batch_latest_{$this->admin->id}", [
        'id' => 'latest-uuid',
        'status' => 'completed',
        'total' => 2,
        'stored' => 0,
        'skipped' => 2,
        'failed' => 0,
        'errors' => [
            [
                'row' => 1,
                'serial_number' => '500730332927',
                'reason' => 'Voucher tidak dapat ditambahkan: Sudah Digunakan (kode 3).',
            ],
        ],
    ]);

    $response = $this->actingAs($this->admin)
        ->getJson(route('admin.vouchers.latest-batch'));

    $response->assertOk()
        ->assertJson([
            'id' => 'latest-uuid',
            'stored' => 0,
            'skipped' => 2,
        ]);
});


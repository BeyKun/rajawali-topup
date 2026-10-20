<?php

use App\Jobs\ValidateVoucherBatchJob;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

it('accepts bulk voucher import from scanned rows array', function () {
    Queue::fake();

    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)
        ->post(route('admin.vouchers.bulk.store'), [
            'rows' => [
                [
                    'serial_number' => '3003 3812 0354 186',
                    'hrn' => '71125 61343 18480 01',
                    'sell_price' => 25000,
                ],
                [
                    'serial_number' => '3003 3812 0355 869',
                    'hrn' => '29225 31740 18486 13',
                    'sell_price' => 25000,
                ],
            ],
            'margin_percentage' => 5,
        ]);

    $response->assertRedirect(route('admin.vouchers.index'));

    Queue::assertPushed(ValidateVoucherBatchJob::class, function (ValidateVoucherBatchJob $job) {
        return count($job->rows) === 2
            && $job->rows[0]['serial_number'] === '300338120354186'
            && $job->rows[0]['hrn'] === '71125613431848001'
            && (float) $job->marginPercentage === 5.0;
    });
});

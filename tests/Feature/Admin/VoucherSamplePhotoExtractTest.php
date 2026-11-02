<?php

use App\Jobs\ValidateVoucherBatchJob;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

it('extracts all 5 vouchers correctly from the sample voucher strip', function () {
    $admin = User::factory()->admin()->create();

    $expectedVouchers = [
        [
            'serial_number' => '500730332927269',
            'hrn' => '92225414690447797',
            'confidence' => 0.99,
            'serial_status' => 'valid',
            'hrn_status' => 'valid',
        ],
        [
            'serial_number' => '500730332928184',
            'hrn' => '33925819300346175',
            'confidence' => 0.98,
            'serial_status' => 'valid',
            'hrn_status' => 'valid',
        ],
        [
            'serial_number' => '500730332929880',
            'hrn' => '97425213340240397',
            'confidence' => 0.96,
            'serial_status' => 'valid',
            'hrn_status' => 'valid',
        ],
        [
            'serial_number' => '500730332930914',
            'hrn' => '87322812750846634',
            'confidence' => 0.97,
            'serial_status' => 'valid',
            'hrn_status' => 'valid',
        ],
        [
            'serial_number' => '500730332931048',
            'hrn' => '96222412030145418',
            'confidence' => 0.98,
            'serial_status' => 'valid',
            'hrn_status' => 'valid',
        ],
    ];

    Http::fake([
        '*/extract-vouchers' => Http::response([
            'success' => true,
            'vouchers' => $expectedVouchers,
            'raw_texts' => [
                'No Seri:500730332927269',
                '92225414690447797',
                'No Seri:5007 30332928184',
                '33925819300346175',
                'No Seri:5007 3033 2929 880',
                '97425213340240397',
                'No Seri:5007 30332930 914',
                '87322812750846634',
                'No Seri:500730332931048',
                '96222412030145418',
            ],
        ], 200),
    ]);

    $samplePath = base_path('tests/Fixtures/vouchers_strip_sample.jpg');
    expect(file_exists($samplePath))->toBeTrue();

    $uploadedFile = new UploadedFile(
        $samplePath,
        'vouchers_strip_sample.jpg',
        'image/jpeg',
        null,
        true
    );

    $response = $this->actingAs($admin)
        ->post(route('admin.vouchers.scan-photo'), [
            'image' => $uploadedFile,
        ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
        ]);

    $data = $response->json();
    expect($data['vouchers'])->toHaveCount(5);

    // Verify all 5 extracted serial numbers and voucher codes
    foreach ($expectedVouchers as $idx => $expected) {
        $actual = $data['vouchers'][$idx];
        expect($actual['serial_number'])->toBe($expected['serial_number'])
            ->and($actual['hrn'])->toBe($expected['hrn'])
            ->and($actual['serial_status'])->toBe('valid')
            ->and($actual['hrn_status'])->toBe('valid');
    }
});

it('dispatches bulk store job with the extracted 5 vouchers from sample image', function () {
    Queue::fake();

    $admin = User::factory()->admin()->create();

    $extractedRows = [
        [
            'serial_number' => '5007 3033 2927 269',
            'hrn' => '92225 41469 04477 97',
            'sell_price' => 25000,
        ],
        [
            'serial_number' => '5007 3033 2928 184',
            'hrn' => '33925 81930 03461 75',
            'sell_price' => 25000,
        ],
        [
            'serial_number' => '5007 3033 2929 880',
            'hrn' => '97425 21334 02403 97',
            'sell_price' => 25000,
        ],
        [
            'serial_number' => '5007 3033 2930 914',
            'hrn' => '87322 81275 08466 34',
            'sell_price' => 25000,
        ],
        [
            'serial_number' => '5007 3033 2931 048',
            'hrn' => '96222 41203 01454 18',
            'sell_price' => 25000,
        ],
    ];

    $response = $this->actingAs($admin)
        ->post(route('admin.vouchers.bulk.store'), [
            'rows' => $extractedRows,
            'margin_percentage' => 0,
        ]);

    $response->assertRedirect(route('admin.vouchers.index'));

    Queue::assertPushed(ValidateVoucherBatchJob::class, function (ValidateVoucherBatchJob $job) {
        if (count($job->rows) !== 5) {
            return false;
        }

        return $job->rows[0]['serial_number'] === '500730332927269'
            && $job->rows[0]['hrn'] === '92225414690447797'
            && $job->rows[1]['serial_number'] === '500730332928184'
            && $job->rows[1]['hrn'] === '33925819300346175'
            && $job->rows[2]['serial_number'] === '500730332929880'
            && $job->rows[2]['hrn'] === '97425213340240397'
            && $job->rows[3]['serial_number'] === '500730332930914'
            && $job->rows[3]['hrn'] === '87322812750846634'
            && $job->rows[4]['serial_number'] === '500730332931048'
            && $job->rows[4]['hrn'] === '96222412030145418';
    });
});

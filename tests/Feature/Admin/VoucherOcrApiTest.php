<?php

use App\Models\User;
use App\Models\VoucherOcrLog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

it('requires authentication to scan voucher photo', function () {
    $file = UploadedFile::fake()->image('voucher.jpg', 800, 600);

    $response = $this->post(route('admin.vouchers.scan-photo'), [
        'image' => $file,
    ]);

    $response->assertRedirect(route('login'));
});

it('rejects non-admin users from scanning voucher photos', function () {
    $regularUser = User::factory()->create();
    $file = UploadedFile::fake()->image('voucher.jpg', 800, 600);

    $response = $this->actingAs($regularUser)
        ->post(route('admin.vouchers.scan-photo'), [
            'image' => $file,
        ]);

    $response->assertForbidden();
});

it('validates that an image file is provided', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)
        ->postJson(route('admin.vouchers.scan-photo'), []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['image']);
});

it('successfully scans voucher photo and parses serial and hrn from OCR service', function () {
    $admin = User::factory()->admin()->create();

    Http::fake([
        '*/extract-vouchers' => Http::response([
            'success' => true,
            'vouchers' => [
                [
                    'serial_number' => '300338120354',
                    'hrn' => '71125613431848001',
                    'confidence' => 0.96,
                ],
            ],
            'raw_texts' => [
                'No Seri: 3003 3812 0354 186',
                '*133* 71125 61343 18480 01 #',
            ],
        ], 200),
    ]);

    $file = UploadedFile::fake()->image('voucher.jpg', 1200, 800);

    $response = $this->actingAs($admin)
        ->post(route('admin.vouchers.scan-photo'), [
            'image' => $file,
        ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'vouchers' => [
                [
                    'serial_number' => '300338120354',
                    'hrn' => '71125613431848001',
                    'serial_status' => 'valid',
                    'hrn_status' => 'valid',
                ],
            ],
        ]);
});

it('returns 503 with friendly message when OCR service is offline', function () {
    $admin = User::factory()->admin()->create();

    Http::fake([
        '*/extract-vouchers' => Http::response(null, 500),
    ]);

    $file = UploadedFile::fake()->image('voucher.jpg', 1200, 800);

    $response = $this->actingAs($admin)
        ->post(route('admin.vouchers.scan-photo'), [
            'image' => $file,
        ]);

    $response->assertStatus(503)
        ->assertJson([
            'success' => false,
        ]);
});

it('saves an OCR log to the database and storage on scan', function () {
    Storage::fake('public');
    $admin = User::factory()->admin()->create();

    Http::fake([
        '*/extract-vouchers' => Http::response([
            'success' => true,
            'vouchers' => [
                [
                    'serial_number' => '300338120354',
                    'hrn' => '71125613431848001',
                    'confidence' => 0.98,
                ],
            ],
            'raw_texts' => ['Sample text line 1'],
        ], 200),
    ]);

    $file = UploadedFile::fake()->image('my_voucher.jpg', 1200, 800);

    $response = $this->actingAs($admin)
        ->post(route('admin.vouchers.scan-photo'), [
            'image' => $file,
        ]);

    $response->assertOk();

    $log = VoucherOcrLog::query()->first();
    expect($log)->not->toBeNull()
        ->and($log->user_id)->toBe($admin->id)
        ->and($log->original_filename)->toBe('my_voucher.jpg')
        ->and($log->is_success)->toBeTrue()
        ->and($log->extracted_vouchers)->toHaveCount(1)
        ->and($log->raw_texts)->toBe(['Sample text line 1']);

    Storage::disk('public')->assertExists($log->image_path);

    // Verify image serving route
    $imageResponse = $this->actingAs($admin)->get(route('admin.ocr-logs.image', $log));
    $imageResponse->assertOk();
});

it('includes ocr_logs in products catalog only for super admins', function () {
    $this->withoutVite();

    $superAdmin = User::factory()->admin()->create();
    $operator = User::factory()->operator()->create();

    VoucherOcrLog::factory()->create([
        'user_id' => $superAdmin->id,
        'original_filename' => 'scan1.jpg',
        'is_success' => true,
    ]);

    $superResponse = $this->actingAs($superAdmin)->get(route('admin.products.index'));
    $superResponse->assertOk();
    expect($superResponse->viewData('page')['props']['ocr_logs'])->not->toBeNull()
        ->and($superResponse->viewData('page')['props']['ocr_logs']['data'])->toHaveCount(1);

    $operatorResponse = $this->actingAs($operator)->get(route('admin.products.index'));
    $operatorResponse->assertOk();
    expect($operatorResponse->viewData('page')['props']['ocr_logs'])->toBeNull();
});

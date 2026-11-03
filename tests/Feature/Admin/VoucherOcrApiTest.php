<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

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

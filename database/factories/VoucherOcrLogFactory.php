<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\VoucherOcrLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VoucherOcrLog>
 */
class VoucherOcrLogFactory extends Factory
{
    protected $model = VoucherOcrLog::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'city_id' => null,
            'image_path' => 'voucher-ocr-logs/sample.jpg',
            'original_filename' => 'sample.jpg',
            'is_success' => true,
            'vouchers_count' => 1,
            'extracted_vouchers' => [
                [
                    'serial_number' => '300338120354',
                    'hrn' => '71125613431848001',
                    'serial_status' => 'valid',
                    'hrn_status' => 'valid',
                ],
            ],
            'raw_texts' => ['3003 3812 0354 186', '*133* 71125 61343 18480 01 #'],
            'error_message' => null,
        ];
    }
}


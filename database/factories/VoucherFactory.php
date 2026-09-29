<?php

namespace Database\Factories;

use App\Enums\VoucherStatus;
use App\Models\Product;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Voucher>
 */
class VoucherFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'serial_number' => fake()->unique()->numerify('############'),
            'hrn' => fake()->unique()->numerify('#################'),
            'status' => VoucherStatus::Available,
            'validity' => fake()->optional()->word(),
            'expired_date' => fake()->optional()->date('d-m-Y H:i'),
            'region' => fake()->optional()->city(),
            'telkomsel_check_response' => null,
            'reserved_at' => null,
            'redeemed_at' => null,
            'redeemed_msisdn' => null,
            'telkomsel_trace_id' => null,
            'created_by' => User::factory(),
        ];
    }
}

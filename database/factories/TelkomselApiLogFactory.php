<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\TelkomselApiLog;
use App\Models\Voucher;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TelkomselApiLog>
 */
class TelkomselApiLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'voucher_id' => Voucher::factory(),
            'endpoint' => fake()->randomElement(['check', 'redeem']),
            'request_payload' => ['serial_number' => fake()->numerify('############')],
            'response_code' => fake()->numberBetween(100, 599),
            'response_payload' => ['status' => 'ok'],
            'duration_ms' => fake()->numberBetween(10, 5000),
        ];
    }
}

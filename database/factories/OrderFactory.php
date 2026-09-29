<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Enums\RedeemStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = fake()->randomFloat(2, 10000, 100000);

        return [
            'order_no' => 'RJW-'.now()->format('YmdHis').'-'.fake()->unique()->numerify('####'),
            'user_id' => User::factory(),
            'product_id' => Product::factory(),
            'voucher_id' => Voucher::factory(),
            'msisdn' => fake()->numerify('628##########'),
            'amount' => $amount,
            'admin_fee' => 0,
            'total_amount' => $amount,
            'payment_channel' => 'QRIS',
            'payment_status' => PaymentStatus::Unpaid,
            'redeem_status' => RedeemStatus::Pending,
            'qris_string' => null,
            'qris_url' => null,
            'qris_expired_at' => now()->addMinutes(15),
            'payment_ref_id' => null,
            'paid_at' => null,
            'redeem_response_code' => null,
            'redeem_response_raw' => null,
            'retry_count' => 0,
        ];
    }
}

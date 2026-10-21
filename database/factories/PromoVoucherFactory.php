<?php

namespace Database\Factories;

use App\Enums\DiscountType;
use App\Models\PromoVoucher;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PromoVoucher>
 */
class PromoVoucherFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'PROMO'.Str::upper(Str::random(6)),
            'title' => fake()->sentence(3),
            'description' => fake()->optional()->sentence(),
            'discount_type' => DiscountType::Fixed,
            'discount_value' => fake()->randomElement([2000, 5000, 10000]),
            'max_discount_amount' => null,
            'min_spend' => 0,
            'usage_limit' => null,
            'used_count' => 0,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the voucher applies a percentage discount.
     */
    public function percentage(float $percent = 10, ?float $maxDiscount = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'discount_type' => DiscountType::Percentage,
            'discount_value' => $percent,
            'max_discount_amount' => $maxDiscount,
        ]);
    }

    /**
     * Indicate that the voucher is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the voucher has passed its end date.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => now()->subDay()->toDateString(),
        ]);
    }
}

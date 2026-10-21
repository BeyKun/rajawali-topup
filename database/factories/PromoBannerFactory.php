<?php

namespace Database\Factories;

use App\Models\PromoBanner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PromoBanner>
 */
class PromoBannerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'image_path' => 'promo-banners/'.fake()->uuid().'.jpg',
            'target_url' => null,
            'is_active' => true,
            'is_featured' => false,
            'sort_order' => fake()->numberBetween(0, 10),
        ];
    }

    /**
     * Indicate that the banner is featured for mobile popup.
     */
    public function featured(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_featured' => true,
        ]);
    }

    /**
     * Indicate that the banner is hidden from the mobile carousel.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}

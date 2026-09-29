<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'category_id' => Category::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'quota_description' => fake()->sentence(),
            'validity_days' => fake()->numberBetween(1, 30),
            'region' => fake()->optional()->city(),
            'hpp_price' => fake()->randomFloat(2, 10000, 100000),
            'sell_price' => fake()->randomFloat(2, 11000, 110000),
            'is_active' => true,
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }
}

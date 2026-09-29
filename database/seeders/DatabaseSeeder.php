<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(AdminUserSeeder::class);

        User::firstOrCreate(
            ['email' => 'test@example.com'],
            ['name' => 'Test User', 'password' => 'password'],
        );

        $products = [
            [
                'name' => 'Internet 5 Hari',
                'slug' => 'internet-5-hari-7gb',
                'quota_description' => '4 GB Kuota Nasional, 3 GB Kuota Lokal, 20 Menit Telepon',
                'validity_days' => 5,
                'region' => null,
                'hpp_price' => 22000,
                'sell_price' => 25000,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Internet 7 Hari',
                'slug' => 'internet-7-hari-10gb',
                'quota_description' => '6 GB Kuota Nasional, 4 GB Kuota Lokal',
                'validity_days' => 7,
                'region' => null,
                'hpp_price' => 32000,
                'sell_price' => 35000,
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Combo Sakti 30 Hari',
                'slug' => 'combo-sakti-30-hari-17gb',
                'quota_description' => '17 GB Kuota Nasional, Nelpon & SMS',
                'validity_days' => 30,
                'region' => null,
                'hpp_price' => 70000,
                'sell_price' => 75000,
                'is_active' => true,
                'sort_order' => 3,
            ],
        ];

        foreach ($products as $attributes) {
            Product::firstOrCreate(['slug' => $attributes['slug']], $attributes);
        }

        $product = Product::where('slug', 'internet-5-hari-7gb')->first();

        if ($product !== null && Voucher::where('product_id', $product->id)->doesntExist()) {
            Voucher::factory()->count(5)->create(['product_id' => $product->id]);
        }
    }
}

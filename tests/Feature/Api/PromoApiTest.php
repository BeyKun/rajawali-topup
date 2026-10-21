<?php

use App\Enums\DiscountType;
use App\Enums\VoucherStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\PromoBanner;
use App\Models\PromoVoucher;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Storage::fake('public');

    $this->user = User::factory()->customer()->create();
    $this->product = Product::factory()->create(['is_active' => true, 'sell_price' => 25000]);
    completeOutletProfile($this->user);
});

test('the banner endpoint returns only active banners in order', function () {
    $second = PromoBanner::factory()->create(['title' => 'Kedua', 'sort_order' => 2, 'is_featured' => true]);
    $first = PromoBanner::factory()->create(['title' => 'Pertama', 'sort_order' => 1, 'is_featured' => false]);
    PromoBanner::factory()->inactive()->create(['sort_order' => 0]);

    $this->getJson('/api/v1/banners')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $first->id)
        ->assertJsonPath('data.0.is_featured', false)
        ->assertJsonPath('data.1.id', $second->id)
        ->assertJsonPath('data.1.is_featured', true)
        ->assertJsonStructure(['data' => [['id', 'title', 'description', 'image_url', 'slider_image_url', 'popup_image_url', 'target_url', 'is_featured', 'sort_order']]]);
});

test('the promo list returns only vouchers that are usable now', function () {
    $usable = PromoVoucher::factory()->create(['title' => 'Aktif']);
    PromoVoucher::factory()->inactive()->create();
    PromoVoucher::factory()->expired()->create();
    PromoVoucher::factory()->create(['usage_limit' => 5, 'used_count' => 5]);

    $this->getJson('/api/v1/promos')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $usable->id)
        ->assertJsonStructure(['data' => [['id', 'code', 'title', 'discount_type', 'discount_value', 'min_spend', 'start_date', 'end_date']]]);
});

test('a fixed promo code validates against a product and returns the discount', function () {
    PromoVoucher::factory()->create(['code' => 'HEMAT10', 'discount_type' => DiscountType::Fixed, 'discount_value' => 10000]);

    $this->postJson('/api/v1/promos/validate', [
        'code' => 'hemat10',
        'product_id' => $this->product->id,
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('valid', true)
        ->assertJsonPath('code', 'HEMAT10')
        ->assertJsonPath('discount_amount', 10000)
        ->assertJsonPath('final_amount', 15000)
        ->assertJsonPath('message', 'Diskon Rp 10.000 diterapkan');
});

test('a percentage promo code is capped by the max discount', function () {
    PromoVoucher::factory()->percentage(50, 8000)->create(['code' => 'SETENGAH']);

    $this->postJson('/api/v1/promos/validate', [
        'code' => 'SETENGAH',
        'product_id' => $this->product->id,
    ])
        ->assertOk()
        ->assertJsonPath('discount_amount', 8000)
        ->assertJsonPath('final_amount', 17000);
});

test('an expired promo code is rejected with a message', function () {
    PromoVoucher::factory()->expired()->create(['code' => 'TELAT']);

    $this->postJson('/api/v1/promos/validate', [
        'code' => 'TELAT',
        'product_id' => $this->product->id,
    ])
        ->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonPath('valid', false)
        ->assertJsonPath('message', 'Kode promo sudah kedaluwarsa.');
});

test('an unknown promo code is rejected with a message', function () {
    $this->postJson('/api/v1/promos/validate', [
        'code' => 'TIDAKADA',
        'product_id' => $this->product->id,
    ])
        ->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Kode promo tidak ditemukan.');
});

test('a promo code below the minimum spend is rejected', function () {
    PromoVoucher::factory()->create(['code' => 'BESAR', 'min_spend' => 50000]);

    $this->postJson('/api/v1/promos/validate', [
        'code' => 'BESAR',
        'product_id' => $this->product->id,
    ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Belanja minimal 50.000 untuk memakai kode ini.');
});

test('a promo code with an exhausted quota is rejected', function () {
    PromoVoucher::factory()->create(['code' => 'HABIS', 'usage_limit' => 3, 'used_count' => 3]);

    $this->postJson('/api/v1/promos/validate', [
        'code' => 'HABIS',
        'product_id' => $this->product->id,
    ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Kuota kode promo sudah habis.');
});

test('the promo validate endpoint requires a code and a real product', function () {
    $this->postJson('/api/v1/promos/validate', [
        'code' => 'HEMAT10',
        'product_id' => 999999,
    ])
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

test('an order created with a promo code stores the discounted total', function () {
    Sanctum::actingAs($this->user);

    $promo = PromoVoucher::factory()->create([
        'code' => 'HEMAT10',
        'discount_type' => DiscountType::Fixed,
        'discount_value' => 10000,
    ]);

    Voucher::factory()->create([
        'product_id' => $this->product->id,
        'status' => VoucherStatus::Available,
    ]);

    $this->postJson('/api/v1/orders', [
        'product_id' => $this->product->id,
        'msisdn' => '082233456777',
        'promo_code' => 'hemat10',
    ])
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.amount', 25000)
        ->assertJsonPath('data.discount_amount', 10000)
        ->assertJsonPath('data.promo_code', 'HEMAT10')
        ->assertJsonPath('data.total_amount', 15000);

    expect($promo->fresh()->used_count)->toBe(1);
});

test('an order with an invalid promo code is rejected and reserves no stock', function () {
    Sanctum::actingAs($this->user);

    $voucher = Voucher::factory()->create([
        'product_id' => $this->product->id,
        'status' => VoucherStatus::Available,
    ]);

    $this->postJson('/api/v1/orders', [
        'product_id' => $this->product->id,
        'msisdn' => '082233456777',
        'promo_code' => 'TIDAKADA',
    ])
        ->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Kode promo tidak ditemukan.');

    expect($voucher->fresh()->status)->toBe(VoucherStatus::Available)
        ->and(Order::query()->count())->toBe(0);
});

test('cancelling an order returns the promo quota', function () {
    Sanctum::actingAs($this->user);

    $promo = PromoVoucher::factory()->create(['code' => 'HEMAT10', 'discount_value' => 10000, 'usage_limit' => 5]);
    Voucher::factory()->create([
        'product_id' => $this->product->id,
        'status' => VoucherStatus::Available,
    ]);

    $created = $this->postJson('/api/v1/orders', [
        'product_id' => $this->product->id,
        'msisdn' => '082233456777',
        'promo_code' => 'HEMAT10',
    ])->assertCreated();

    expect($promo->fresh()->used_count)->toBe(1);

    $this->postJson('/api/v1/orders/'.$created->json('data.order_no').'/cancel')
        ->assertOk();

    expect($promo->fresh()->used_count)->toBe(0);
});

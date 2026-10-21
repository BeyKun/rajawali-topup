<?php

use App\Enums\DiscountType;
use App\Enums\VoucherStatus;
use App\Models\City;
use App\Models\District;
use App\Models\Order;
use App\Models\Product;
use App\Models\PromoBanner;
use App\Models\PromoVoucher;
use App\Models\Province;
use App\Models\User;
use App\Models\Village;
use App\Models\Voucher;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->withoutVite();

    Storage::fake('public');

    config([
        'telkomsel.mock' => true,
        'payment.provider' => 'mock',
    ]);

    Http::preventStrayRequests();

    $this->admin = User::factory()->admin()->create();

    $province = Province::create(['code' => '11', 'name' => 'Aceh']);
    $this->city = City::create([
        'province_id' => $province->id,
        'province_code' => '11',
        'code' => '11.01',
        'name' => 'Aceh Selatan',
    ]);
    $this->district = District::create([
        'city_id' => $this->city->id,
        'city_code' => '11.01',
        'code' => '11.01.01',
        'name' => 'Bakongan',
    ]);
    $this->village = Village::create([
        'district_id' => $this->district->id,
        'district_code' => '11.01.01',
        'code' => '11.01.01.2001',
        'name' => 'Keude Bakongan',
    ]);
});

/**
 * The numbered comments below mirror TASK.md Fase 5 so the assertions read
 * top-to-bottom as the real admin -> outlet -> mobile journey.
 */
test('an admin-provisioned outlet checks out with a promo voucher end to end', function () {
    // 1. Admin provisions a new outlet user (default password) for a region.
    $this->actingAs($this->admin)
        ->post('/admin/outlets', [
            'name' => 'Budi Outlet',
            'phone' => '081200000001',
            'outlet_name' => 'Konter Budi',
            'whatsapp' => '081200000001',
            'province_id' => $this->city->province_id,
            'city_id' => $this->city->id,
            'district_id' => $this->district->id,
            'village_id' => $this->village->id,
            'address' => 'Jl. Merdeka No. 1',
        ])
        ->assertRedirect('/admin/outlets');

    $outlet = User::query()->where('phone', '6281200000001')->sole();

    expect($outlet->name)->toBe('Budi Outlet')
        ->and($outlet->email)->toBeNull()
        ->and($outlet->outletProfile?->outlet_name)->toBe('Konter Budi');

    // 2. Admin intakes a physical voucher (mock Telkomsel check auto-creates the product).
    $this->actingAs($this->admin)
        ->post('/admin/vouchers', [
            'serial_number' => '300338120351',
            'hrn' => '71125613431848001',
            'sell_price' => 25000,
        ])
        ->assertRedirect('/admin/vouchers');

    $product = Product::query()->sole();

    expect((float) $product->sell_price)->toBe(25000.0)
        ->and(Voucher::query()->where('product_id', $product->id)->where('status', VoucherStatus::Available)->count())->toBe(1);

    // 3. Admin uploads a promo banner for the mobile home carousel.
    $this->actingAs($this->admin)
        ->post('/admin/promos/banners', [
            'title' => 'Promo Rajawali',
            'image' => UploadedFile::fake()->image('promo.png', 800, 400),
            'target_url' => 'https://example.test/promo',
            'is_active' => true,
            'sort_order' => 1,
        ])
        ->assertRedirect('/admin/promos');

    $banner = PromoBanner::query()->sole();

    Storage::disk('public')->assertExists($banner->image_path);

    // 4. Admin creates a fixed-discount promo voucher for the outlets.
    $this->actingAs($this->admin)
        ->post('/admin/promos/vouchers', [
            'code' => 'hemat10',
            'title' => 'Diskon Rp 10.000',
            'discount_type' => DiscountType::Fixed->value,
            'discount_value' => 10000,
            'max_discount_amount' => null,
            'min_spend' => 0,
            'usage_limit' => 5,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'is_active' => true,
        ])
        ->assertRedirect('/admin/promos');

    $promo = PromoVoucher::query()->sole();

    expect($promo->code)->toBe('HEMAT10')
        ->and((float) $promo->discount_value)->toBe(10000.0)
        ->and($promo->used_count)->toBe(0);

    // Drop the admin session so the Sanctum token drives the mobile requests.
    $this->app['auth']->forgetGuards();

    // 5. The outlet signs into the mobile app with the phone number + default password.
    $auth = $this->postJson('/api/v1/auth/login', [
        'phone' => '081200000001',
        'password' => 'rajawali123',
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('user.phone', '6281200000001');

    $token = (string) $auth->json('token');

    expect($token)->not->toBeEmpty();

    // 6. The mobile app pulls the banner and the usable promo vouchers.
    $this->withToken($token)
        ->getJson('/api/v1/banners')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Promo Rajawali')
        ->assertJsonPath('data.0.image_url', fn ($url) => is_string($url) && str_contains($url, 'promo-banners/'));

    $this->withToken($token)
        ->getJson('/api/v1/promos')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.code', 'HEMAT10');

    // 7. The outlet previews the discount before confirming the purchase.
    $this->withToken($token)
        ->postJson('/api/v1/promos/validate', [
            'code' => 'hemat10',
            'product_id' => $product->id,
        ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('valid', true)
        ->assertJsonPath('code', 'HEMAT10')
        ->assertJsonPath('discount_amount', 10000)
        ->assertJsonPath('final_amount', 15000)
        ->assertJsonPath('message', 'Diskon Rp 10.000 diterapkan');

    // 8. The outlet confirms the purchase with the promo code applied.
    $created = $this->withToken($token)
        ->postJson('/api/v1/orders', [
            'product_id' => $product->id,
            'msisdn' => '082233456777',
            'promo_code' => 'hemat10',
        ])
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.amount', 25000)
        ->assertJsonPath('data.discount_amount', 10000)
        ->assertJsonPath('data.promo_code', 'HEMAT10')
        ->assertJsonPath('data.total_amount', 15000);

    $order = Order::query()->where('order_no', $created->json('data.order_no'))->sole();

    expect($order->user_id)->toBe($outlet->id)
        ->and((float) $order->total_amount)->toBe(15000.0)
        ->and($order->promo_voucher_id)->toBe($promo->id);

    // The QRIS invoice is built for the discounted amount, not the list price.
    $qris = (string) $created->json('data.qris_string');

    expect($qris)->toContain('540815000.00')
        ->and($qris)->not->toContain('540825000.00');

    // 9. The promo quota is consumed and the physical stock is reserved.
    expect($promo->fresh()->used_count)->toBe(1)
        ->and(Voucher::query()->where('product_id', $product->id)->sole()->status)->toBe(VoucherStatus::Reserved);
});

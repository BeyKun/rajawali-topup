<?php

use App\Enums\DiscountType;
use App\Models\PromoBanner;
use App\Models\PromoVoucher;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->withoutVite();
    Storage::fake('public');
    $this->admin = User::factory()->admin()->create();
});

/**
 * Build the promo voucher payload shared by the create and update requests.
 *
 * @return array<string, mixed>
 */
function promoVoucherPayload(array $overrides = []): array
{
    return array_merge([
        'code' => 'HEMAT10',
        'title' => 'Diskon Rp 10.000',
        'description' => 'Potongan untuk semua paket',
        'discount_type' => DiscountType::Fixed->value,
        'discount_value' => 10000,
        'min_spend' => 0,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'is_active' => true,
    ], $overrides);
}

test('the promo page renders both banners and promo vouchers', function () {
    PromoBanner::factory()->count(2)->create();
    PromoVoucher::factory()->count(3)->create();

    $this->actingAs($this->admin)
        ->get('/admin/promos')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/promos/Index')
            ->has('banners', 2)
            ->has('promoVouchers', 3)
            ->where('discountTypes', [DiscountType::Fixed->value, DiscountType::Percentage->value])
        );
});

test('an admin can upload a promo banner', function () {
    $this->actingAs($this->admin)
        ->post('/admin/promos/banners', [
            'title' => 'Promo Ramadan',
            'image' => UploadedFile::fake()->image('banner.jpg', 800, 400),
            'target_url' => 'https://example.test/promo',
            'is_active' => true,
            'sort_order' => 1,
        ])
        ->assertRedirect('/admin/promos');

    $banner = PromoBanner::query()->sole();

    expect($banner->title)->toBe('Promo Ramadan')
        ->and($banner->is_active)->toBeTrue()
        ->and($banner->sort_order)->toBe(1);

    Storage::disk('public')->assertExists($banner->image_path);
});

test('a banner upload requires an image and a title', function () {
    $this->actingAs($this->admin)
        ->post('/admin/promos/banners', ['title' => ''])
        ->assertSessionHasErrors(['title', 'image']);

    expect(PromoBanner::query()->count())->toBe(0);
});

test('updating a banner without a new image keeps the stored file', function () {
    $banner = PromoBanner::factory()->create(['title' => 'Lama']);
    Storage::disk('public')->put($banner->image_path, 'fake-image');

    $this->actingAs($this->admin)
        ->post("/admin/promos/banners/{$banner->id}", [
            'title' => 'Baru',
            'is_active' => false,
            'sort_order' => 5,
        ])
        ->assertRedirect('/admin/promos');

    $banner->refresh();

    expect($banner->title)->toBe('Baru')
        ->and($banner->is_active)->toBeFalse()
        ->and($banner->sort_order)->toBe(5);

    Storage::disk('public')->assertExists($banner->image_path);
});

test('updating a banner with a new image replaces the old file', function () {
    $banner = PromoBanner::factory()->create();
    Storage::disk('public')->put($banner->image_path, 'fake-image');
    $oldPath = $banner->image_path;

    $this->actingAs($this->admin)
        ->post("/admin/promos/banners/{$banner->id}", [
            'title' => $banner->title,
            'image' => UploadedFile::fake()->image('baru.png', 600, 300),
        ])
        ->assertRedirect('/admin/promos');

    $newPath = $banner->fresh()->image_path;

    expect($newPath)->not->toBe($oldPath);

    Storage::disk('public')->assertMissing($oldPath);
    Storage::disk('public')->assertExists($newPath);
});

test('deleting a banner removes its image file', function () {
    $banner = PromoBanner::factory()->create();
    Storage::disk('public')->put($banner->image_path, 'fake-image');

    $this->actingAs($this->admin)
        ->delete("/admin/promos/banners/{$banner->id}")
        ->assertRedirect('/admin/promos');

    $this->assertDatabaseMissing('promo_banners', ['id' => $banner->id]);
    Storage::disk('public')->assertMissing($banner->image_path);
});

test('uploading a featured banner unsets previous featured banner', function () {
    $first = PromoBanner::factory()->featured()->create();

    $this->actingAs($this->admin)
        ->post('/admin/promos/banners', [
            'title' => 'Banner Kedua Featured',
            'image' => UploadedFile::fake()->image('banner2.jpg', 800, 400),
            'is_active' => true,
            'is_featured' => true,
        ])
        ->assertRedirect('/admin/promos');

    expect($first->fresh()->is_featured)->toBeFalse();

    $second = PromoBanner::query()->where('title', 'Banner Kedua Featured')->sole();
    expect($second->is_featured)->toBeTrue();
});

test('updating a banner to featured unsets other featured banners', function () {
    $banner1 = PromoBanner::factory()->featured()->create();
    $banner2 = PromoBanner::factory()->create(['is_featured' => false]);

    $this->actingAs($this->admin)
        ->post("/admin/promos/banners/{$banner2->id}", [
            'title' => $banner2->title,
            'is_featured' => true,
        ])
        ->assertRedirect('/admin/promos');

    expect($banner1->fresh()->is_featured)->toBeFalse()
        ->and($banner2->fresh()->is_featured)->toBeTrue();
});

test('an admin can create a fixed-discount promo voucher', function () {
    $this->actingAs($this->admin)
        ->post('/admin/promos/vouchers', promoVoucherPayload())
        ->assertRedirect('/admin/promos');

    $voucher = PromoVoucher::query()->sole();

    expect($voucher->code)->toBe('HEMAT10')
        ->and($voucher->discount_type)->toBe(DiscountType::Fixed)
        ->and((float) $voucher->discount_value)->toBe(10000.0)
        ->and($voucher->is_active)->toBeTrue();
});

test('a promo voucher code is stored uppercase and must be unique', function () {
    $this->actingAs($this->admin)
        ->post('/admin/promos/vouchers', promoVoucherPayload(['code' => 'hemat10']))
        ->assertRedirect('/admin/promos');

    $this->assertDatabaseHas('promo_vouchers', ['code' => 'HEMAT10']);

    $this->actingAs($this->admin)
        ->post('/admin/promos/vouchers', promoVoucherPayload(['code' => 'HEMAT10']))
        ->assertSessionHasErrors('code');

    expect(PromoVoucher::query()->count())->toBe(1);
});

test('a promo voucher rejects an end date before the start date', function () {
    $this->actingAs($this->admin)
        ->post('/admin/promos/vouchers', promoVoucherPayload([
            'start_date' => now()->toDateString(),
            'end_date' => now()->subDay()->toDateString(),
        ]))
        ->assertSessionHasErrors('end_date');
});

test('a promo voucher rejects an invalid discount type and non-positive value', function () {
    $this->actingAs($this->admin)
        ->post('/admin/promos/vouchers', promoVoucherPayload([
            'discount_type' => 'gratis',
            'discount_value' => 0,
        ]))
        ->assertSessionHasErrors(['discount_type', 'discount_value']);
});

test('an admin can update a promo voucher', function () {
    $voucher = PromoVoucher::factory()->create(['code' => 'LAMA', 'title' => 'Lama']);

    $this->actingAs($this->admin)
        ->put("/admin/promos/vouchers/{$voucher->id}", promoVoucherPayload([
            'code' => 'BARU50',
            'title' => 'Baru',
            'discount_type' => DiscountType::Percentage->value,
            'discount_value' => 50,
            'max_discount_amount' => 20000,
            'usage_limit' => 100,
        ]))
        ->assertRedirect('/admin/promos');

    $voucher->refresh();

    expect($voucher->code)->toBe('BARU50')
        ->and($voucher->title)->toBe('Baru')
        ->and($voucher->discount_type)->toBe(DiscountType::Percentage)
        ->and((float) $voucher->discount_value)->toBe(50.0)
        ->and((float) $voucher->max_discount_amount)->toBe(20000.0)
        ->and($voucher->usage_limit)->toBe(100);
});

test('an admin can delete a promo voucher', function () {
    $voucher = PromoVoucher::factory()->create();

    $this->actingAs($this->admin)
        ->delete("/admin/promos/vouchers/{$voucher->id}")
        ->assertRedirect('/admin/promos');

    $this->assertDatabaseMissing('promo_vouchers', ['id' => $voucher->id]);
});

test('guests cannot manage promo banners or vouchers', function () {
    $banner = PromoBanner::factory()->create();
    $voucher = PromoVoucher::factory()->create();

    $this->get('/admin/promos')->assertRedirect('/login');
    $this->post('/admin/promos/banners', [])->assertRedirect('/login');
    $this->post('/admin/promos/vouchers', [])->assertRedirect('/login');
    $this->delete("/admin/promos/banners/{$banner->id}")->assertRedirect('/login');
    $this->delete("/admin/promos/vouchers/{$voucher->id}")->assertRedirect('/login');
});

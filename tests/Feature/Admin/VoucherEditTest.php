<?php

use App\Enums\UserRole;
use App\Enums\VoucherStatus;
use App\Models\City;
use App\Models\Product;
use App\Models\Province;
use App\Models\User;
use App\Models\Voucher;

beforeEach(function () {
    $this->withoutVite();
    $this->admin = User::factory()->admin()->create(['role' => UserRole::SuperAdmin]);
    $this->product = Product::factory()->create([
        'hpp_price' => 20000,
        'sell_price' => 20400,
    ]);
});

test('super admin can update voucher details and pricing', function () {
    $voucher = Voucher::factory()->create([
        'product_id' => $this->product->id,
        'serial_number' => '111122223333',
        'hrn' => '71125613431848001',
        'status' => VoucherStatus::Available,
    ]);

    $response = $this->actingAs($this->admin)
        ->put("/admin/vouchers/{$voucher->id}", [
            'serial_number' => '111122223334',
            'sell_price' => 30000,
            'margin_percentage' => 5,
            'status' => 'EXPIRED',
        ]);

    $response->assertRedirect('/admin/vouchers');

    $voucher->refresh();
    expect($voucher->serial_number)->toBe('111122223334')
        ->and($voucher->status)->toBe(VoucherStatus::Expired)
        ->and($voucher->hrn)->toBe('71125613431848001'); // HRN unchanged

    $this->product->refresh();
    expect((float) $this->product->hpp_price)->toBe(30000.0)
        ->and((float) $this->product->sell_price)->toBe(31500.0); // 30000 + 5% = 31500
});

test('updating voucher with new hrn encrypts and saves it', function () {
    $voucher = Voucher::factory()->create([
        'product_id' => $this->product->id,
        'serial_number' => '111122223333',
        'hrn' => '71125613431848001',
        'status' => VoucherStatus::Available,
    ]);

    $newHrn = '99998888777766665';

    $response = $this->actingAs($this->admin)
        ->put("/admin/vouchers/{$voucher->id}", [
            'serial_number' => '111122223333',
            'hrn' => $newHrn,
            'sell_price' => 20000,
            'margin_percentage' => 2,
            'status' => 'AVAILABLE',
        ]);

    $response->assertRedirect('/admin/vouchers');

    $voucher->refresh();
    expect($voucher->hrn)->toBe($newHrn);
});

test('super admin can update assigned cities of voucher', function () {
    $province = Province::query()->create(['name' => 'Jawa Barat']);
    $city1 = City::query()->create(['province_id' => $province->id, 'name' => 'Kota Bandung']);
    $city2 = City::query()->create(['province_id' => $province->id, 'name' => 'Kota Cirebon']);

    $voucher = Voucher::factory()->create([
        'product_id' => $this->product->id,
        'serial_number' => '111122223333',
        'hrn' => '71125613431848001',
        'status' => VoucherStatus::Available,
    ]);

    $response = $this->actingAs($this->admin)
        ->put("/admin/vouchers/{$voucher->id}", [
            'serial_number' => '111122223333',
            'sell_price' => 20000,
            'margin_percentage' => 2,
            'status' => 'AVAILABLE',
            'city_ids' => [$city1->id, $city2->id],
        ]);

    $response->assertRedirect('/admin/vouchers');

    $voucher->refresh();
    expect($voucher->cities->pluck('id')->all())->toEqualCanonicalizing([$city1->id, $city2->id]);
});

test('kabupaten admin cannot edit voucher belonging to another city', function () {
    $province = Province::query()->create(['name' => 'Jawa Barat']);
    $city1 = City::query()->create(['province_id' => $province->id, 'name' => 'Kota Bandung']);
    $city2 = City::query()->create(['province_id' => $province->id, 'name' => 'Kota Cirebon']);

    $kabAdmin = User::factory()->create([
        'role' => UserRole::KabupatenAdmin,
        'city_id' => $city1->id,
    ]);

    $otherCityVoucher = Voucher::factory()->create([
        'product_id' => $this->product->id,
        'serial_number' => '555566667777',
        'hrn' => '71125613431848001',
        'city_id' => $city2->id,
        'status' => VoucherStatus::Available,
    ]);

    $response = $this->actingAs($kabAdmin)
        ->put("/admin/vouchers/{$otherCityVoucher->id}", [
            'serial_number' => '555566667777',
            'sell_price' => 20000,
            'margin_percentage' => 2,
            'status' => 'AVAILABLE',
        ]);

    $response->assertForbidden();
});

test('edit voucher validates duplicate serial number', function () {
    $voucher1 = Voucher::factory()->create([
        'product_id' => $this->product->id,
        'serial_number' => '111122223333',
    ]);
    $voucher2 = Voucher::factory()->create([
        'product_id' => $this->product->id,
        'serial_number' => '444455556666',
    ]);

    $response = $this->actingAs($this->admin)
        ->put("/admin/vouchers/{$voucher2->id}", [
            'serial_number' => '111122223333', // already taken by voucher1
            'sell_price' => 20000,
            'status' => 'AVAILABLE',
        ]);

    $response->assertSessionHasErrors('serial_number');
});

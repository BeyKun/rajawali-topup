<?php

use App\Enums\VoucherStatus;
use App\Models\Product;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->withoutVite();
    config(['telkomsel.mock' => true]);
    $this->admin = User::factory()->admin()->create();
});

test('voucher create page renders without a product select', function () {
    $this->actingAs($this->admin)
        ->get('/admin/vouchers/create')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/vouchers/Create')
            ->missing('products')
        );
});

test('check endpoint returns a preview without persisting anything', function () {
    $response = $this->actingAs($this->admin)
        ->postJson('/admin/vouchers/check', [
            'serial_number' => '300338120354',
        ]);

    $response->assertOk()
        ->assertJson([
            'is_valid' => true,
            'status_code' => 1,
        ]);

    expect(Voucher::query()->count())->toBe(0);
});

test('a valid voucher is stored as available with an encrypted hrn and auto-creates its product', function () {
    $serial = '300338120354';
    $hrn = '71125613431848001';

    $this->actingAs($this->admin)
        ->post('/admin/vouchers', [
            'serial_number' => $serial,
            'hrn' => $hrn,
            'sell_price' => 25000,
        ])
        ->assertRedirect('/admin/vouchers');

    $voucher = Voucher::query()->where('serial_number', $serial)->sole();

    expect($voucher->status)->toBe(VoucherStatus::Available)
        ->and($voucher->hrn)->toBe($hrn)
        ->and($voucher->created_by)->toBe($this->admin->id);

    $product = Product::query()->findOrFail($voucher->product_id);

    expect((float) $product->sell_price)->toBe(25000.0)
        ->and($product->is_active)->toBeTrue()
        ->and($product->region)->not->toBeNull();

    $rawHrn = DB::table('vouchers')->where('id', $voucher->id)->value('hrn');

    expect($rawHrn)->not->toBe($hrn);
});

test('two vouchers of the same package share one auto-generated product', function () {
    $pairs = [
        ['300338120354', '71125613431848001'],
        ['300338120355', '71125613431848002'],
    ];

    foreach ($pairs as [$serial, $hrn]) {
        $this->actingAs($this->admin)
            ->post('/admin/vouchers', [
                'serial_number' => $serial,
                'hrn' => $hrn,
                'sell_price' => 25000,
            ])
            ->assertRedirect('/admin/vouchers');
    }

    expect(Product::query()->count())->toBe(1)
        ->and(Voucher::query()->count())->toBe(2);
});

test('the sell price is required', function () {
    $this->actingAs($this->admin)
        ->post('/admin/vouchers', [
            'serial_number' => '300338120354',
            'hrn' => '71125613431848001',
        ])
        ->assertSessionHasErrors('sell_price');

    expect(Voucher::query()->count())->toBe(0);
});

test('an invalid or used voucher is rejected', function () {
    config(['telkomsel.mock' => false]);

    Http::fake([
        '*/check' => Http::response([
            'statusCode' => 3,
            'statusMessage' => 'Sudah Digunakan',
            'serialNumber' => '300338120999',
        ]),
    ]);

    $this->actingAs($this->admin)
        ->post('/admin/vouchers', [
            'serial_number' => '300338120999',
            'hrn' => '71125613431848099',
            'sell_price' => 25000,
        ])
        ->assertSessionHasErrors('serial_number');

    expect(Voucher::query()->count())->toBe(0);
});

test('a duplicate serial number is rejected', function () {
    $product = Product::factory()->create();

    Voucher::factory()->create([
        'product_id' => $product->id,
        'serial_number' => '300338120354',
    ]);

    $this->actingAs($this->admin)
        ->post('/admin/vouchers', [
            'serial_number' => '300338120354',
            'hrn' => '71125613431848001',
            'sell_price' => 25000,
        ])
        ->assertSessionHasErrors('serial_number');

    expect(Voucher::query()->where('serial_number', '300338120354')->count())->toBe(1);
});

test('voucher input validates serial and hrn length', function () {
    $this->actingAs($this->admin)
        ->post('/admin/vouchers', [
            'serial_number' => '123',
            'hrn' => '456',
            'sell_price' => 25000,
        ])
        ->assertSessionHasErrors(['serial_number', 'hrn']);
});

test('an available voucher can be deleted but a redeemed one cannot', function () {
    $product = Product::factory()->create();

    $available = Voucher::factory()->create([
        'product_id' => $product->id,
        'status' => VoucherStatus::Available,
    ]);

    $redeemed = Voucher::factory()->create([
        'product_id' => $product->id,
        'status' => VoucherStatus::Redeemed,
    ]);

    $this->actingAs($this->admin)
        ->delete("/admin/vouchers/{$available->id}")
        ->assertRedirect('/admin/vouchers');

    $this->assertDatabaseMissing('vouchers', ['id' => $available->id]);

    $this->actingAs($this->admin)
        ->delete("/admin/vouchers/{$redeemed->id}")
        ->assertSessionHasErrors('voucher');

    $this->assertDatabaseHas('vouchers', ['id' => $redeemed->id]);
});

test('super admin sees is_super_admin and cities props on create page', function () {
    $province = \App\Models\Province::create(['code' => '32', 'name' => 'Jawa Barat']);
    $city = \App\Models\City::create(['province_id' => $province->id, 'province_code' => '32', 'code' => '32.01', 'name' => 'Bogor']);

    $this->actingAs($this->admin)
        ->get('/admin/vouchers/create')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/vouchers/Create')
            ->where('is_super_admin', true)
            ->has('cities', 1)
        );
});

test('super admin can assign multiple cities to a voucher', function () {
    $province = \App\Models\Province::create(['code' => '11', 'name' => 'Aceh']);
    $cityA = \App\Models\City::create(['province_id' => $province->id, 'province_code' => '11', 'code' => '11.01', 'name' => 'Aceh Selatan']);
    $cityB = \App\Models\City::create(['province_id' => $province->id, 'province_code' => '11', 'code' => '11.02', 'name' => 'Aceh Singkil']);
    $cityC = \App\Models\City::create(['province_id' => $province->id, 'province_code' => '11', 'code' => '11.03', 'name' => 'Aceh Barat']);

    $serial = '300338120354';
    $hrn = '71125613431848001';

    $this->actingAs($this->admin)
        ->post('/admin/vouchers', [
            'serial_number' => $serial,
            'hrn' => $hrn,
            'sell_price' => 25000,
            'city_ids' => [$cityA->id, $cityB->id],
        ])
        ->assertRedirect('/admin/vouchers');

    $voucher = Voucher::query()->where('serial_number', $serial)->sole();

    expect($voucher->cities()->count())->toBe(2)
        ->and($voucher->cities->pluck('id')->all())->toEqualCanonicalizing([$cityA->id, $cityB->id]);

    // Available for city A and city B, but not city C
    expect(Voucher::query()->forCity($cityA->id)->count())->toBe(1)
        ->and(Voucher::query()->forCity($cityB->id)->count())->toBe(1)
        ->and(Voucher::query()->forCity($cityC->id)->count())->toBe(0);
});

test('voucher input applies margin percentage to calculate final mobile sell price and preserves hpp price', function () {
    $serial = '300338120354';
    $hrn = '71125613431848001';

    $this->actingAs($this->admin)
        ->post('/admin/vouchers', [
            'serial_number' => $serial,
            'hrn' => $hrn,
            'sell_price' => 10000,
            'margin_percentage' => 2,
        ])
        ->assertRedirect('/admin/vouchers');

    $voucher = Voucher::query()->where('serial_number', $serial)->sole();
    $product = Product::query()->findOrFail($voucher->product_id);

    // Base price becomes hpp_price (10,000) and final sell_price becomes 10,200 (+2%)
    expect((float) $product->hpp_price)->toBe(10000.0)
        ->and((float) $product->sell_price)->toBe(10200.0);
});

test('negative margin percentage is rejected by validation', function () {
    $this->actingAs($this->admin)
        ->post('/admin/vouchers', [
            'serial_number' => '300338120354',
            'hrn' => '71125613431848001',
            'sell_price' => 10000,
            'margin_percentage' => -5,
        ])
        ->assertSessionHasErrors('margin_percentage');
});

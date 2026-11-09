<?php

use App\Models\City;
use App\Models\Product;
use App\Models\Province;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->withoutVite();
    config(['telkomsel.mock' => true]);
    $this->admin = User::factory()->admin()->create();
    $this->province = Province::create(['code' => '99', 'name' => 'Provinsi Default']);
    $this->city = City::create(['province_id' => $this->province->id, 'province_code' => '99', 'code' => '99.01', 'name' => 'Kota Default']);
});

test('bulk import stores only the valid rows', function () {
    $content = implode("\n", [
        'serial_number,hrn,sell_price',
        '300338120354,71125613431848001,25000',
        '300338120355,71125613431848002,25000',
    ]);

    $file = UploadedFile::fake()->createWithContent('vouchers.csv', $content);

    $this->actingAs($this->admin)
        ->post('/admin/vouchers/bulk', ['file' => $file, 'city_ids' => [$this->city->id]])
        ->assertRedirect('/admin/vouchers/bulk');

    expect(Voucher::query()->count())->toBe(2);

    $product = Product::query()->sole();

    expect((float) $product->sell_price)->toBe(25500.0);
});

test('bulk import rejects batch if serials already exist in inventory', function () {
    $product = Product::factory()->create();

    Voucher::factory()->create([
        'product_id' => $product->id,
        'serial_number' => '300338120354',
    ]);

    $content = implode("\n", [
        'serial_number,hrn,sell_price',
        '300338120354,71125613431848001,25000',
        '300338120356,71125613431848003,25000',
    ]);

    $file = UploadedFile::fake()->createWithContent('vouchers.csv', $content);

    $this->actingAs($this->admin)
        ->post('/admin/vouchers/bulk', ['file' => $file, 'city_ids' => [$this->city->id]])
        ->assertSessionHasErrors(['file' => 'Voucher dengan nomor seri berikut sudah pernah ada di inventori: 300338120354.']);

    expect(Voucher::query()->count())->toBe(1);
});

test('bulk import rejects batch if file contains duplicate serial numbers', function () {
    $content = implode("\n", [
        'serial_number,hrn,sell_price',
        '300338120356,71125613431848001,25000',
        '300338120356,71125613431848002,25000',
    ]);

    $file = UploadedFile::fake()->createWithContent('vouchers.csv', $content);

    $this->actingAs($this->admin)
        ->post('/admin/vouchers/bulk', ['file' => $file, 'city_ids' => [$this->city->id]])
        ->assertSessionHasErrors(['file' => 'Terdapat nomor seri yang terduplikasi di dalam data import: 300338120356.']);

    expect(Voucher::query()->count())->toBe(0);
});

test('bulk import rejects a file without valid rows', function () {
    $file = UploadedFile::fake()->createWithContent('vouchers.csv', "header\nfoo");

    $this->actingAs($this->admin)
        ->post('/admin/vouchers/bulk', ['file' => $file, 'city_ids' => [$this->city->id]])
        ->assertSessionHasErrors('file');
});

test('bulk import requires a file', function () {
    $this->actingAs($this->admin)
        ->post('/admin/vouchers/bulk', ['city_ids' => [$this->city->id]])
        ->assertSessionHasErrors('file');
});

test('super admin bulk import requires city_ids', function () {
    $content = implode("\n", [
        'serial_number,hrn,sell_price',
        '300338120354,71125613431848001,25000',
    ]);

    $file = UploadedFile::fake()->createWithContent('vouchers.csv', $content);

    $this->actingAs($this->admin)
        ->post('/admin/vouchers/bulk', ['file' => $file])
        ->assertSessionHasErrors('city_ids');
});

test('super admin sees is_super_admin and cities props on bulk page', function () {
    $province = Province::create(['code' => '32', 'name' => 'Jawa Barat']);
    $city = City::create(['province_id' => $province->id, 'province_code' => '32', 'code' => '32.01', 'name' => 'Bogor']);

    $this->actingAs($this->admin)
        ->get('/admin/vouchers/bulk')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/vouchers/Bulk')
            ->where('is_super_admin', true)
            ->has('cities', 2)
        );
});

test('super admin bulk import applies multiple cities to all imported vouchers', function () {
    $province = Province::create(['code' => '11', 'name' => 'Aceh']);
    $cityA = City::create(['province_id' => $province->id, 'province_code' => '11', 'code' => '11.01', 'name' => 'Aceh Selatan']);
    $cityB = City::create(['province_id' => $province->id, 'province_code' => '11', 'code' => '11.02', 'name' => 'Aceh Singkil']);

    $content = implode("\n", [
        'serial_number,hrn,sell_price',
        '300338120354,71125613431848001,25000',
        '300338120355,71125613431848002,25000',
    ]);

    $file = UploadedFile::fake()->createWithContent('vouchers.csv', $content);

    $this->actingAs($this->admin)
        ->post('/admin/vouchers/bulk', [
            'file' => $file,
            'city_ids' => [$cityA->id, $cityB->id],
        ])
        ->assertRedirect('/admin/vouchers/bulk');

    expect(Voucher::query()->count())->toBe(2);

    $vouchers = Voucher::with('cities')->get();
    foreach ($vouchers as $voucher) {
        expect($voucher->cities->pluck('id')->all())->toEqualCanonicalizing([$cityA->id, $cityB->id]);
    }
});

test('bulk import rejects rows with zero or negative sell price', function () {
    $this->actingAs($this->admin)
        ->post('/admin/vouchers/bulk', [
            'rows' => [
                [
                    'serial_number' => '300338120354',
                    'hrn' => '71125613431848001',
                    'sell_price' => 0,
                ],
            ],
        ])
        ->assertSessionHasErrors('rows.0.sell_price');

    expect(Voucher::query()->count())->toBe(0);
});

test('bulk import csv rejects file with zero sell price', function () {
    $content = implode("\n", [
        'serial_number,hrn,sell_price',
        '300338120354,71125613431848001,0',
    ]);

    $file = UploadedFile::fake()->createWithContent('vouchers_zero.csv', $content);

    $this->actingAs($this->admin)
        ->post('/admin/vouchers/bulk', ['file' => $file, 'city_ids' => [$this->city->id]])
        ->assertSessionHasErrors('file');

    expect(Voucher::query()->count())->toBe(0);
});

test('bulk import groups vouchers by title description and price into products', function () {
    $rows = [
        [
            'serial_number' => '300338120354',
            'hrn' => '71125613431848001',
            'sell_price' => 25000,
        ],
        [
            'serial_number' => '300338120355',
            'hrn' => '71125613431848002',
            'sell_price' => 25000,
        ],
        [
            'serial_number' => '300338120356',
            'hrn' => '71125613431848003',
            'sell_price' => 30000,
        ],
    ];

    $this->actingAs($this->admin)
        ->post('/admin/vouchers/bulk', [
            'title' => 'Telkomsel Flash Max',
            'description' => '10 GB Kuota Utama 30 Hari',
            'rows' => $rows,
            'margin_percentage' => 0,
            'city_ids' => [$this->city->id],
        ])
        ->assertRedirect('/admin/vouchers/bulk');

    expect(Voucher::query()->count())->toBe(3);

    // Because row 1 & row 2 have price 25000 (+2% margin = 25500), and row 3 has price 30000 (+2% margin = 30600), exactly 2 products must be created
    expect(Product::query()->count())->toBe(2);

    $p25 = Product::query()->where('sell_price', 25500)->first();
    $p30 = Product::query()->where('sell_price', 30600)->first();

    expect($p25)->not->toBeNull()
        ->and($p25->name)->toBe('Telkomsel Flash Max')
        ->and($p25->quota_description)->toBe('10 GB Kuota Utama 30 Hari')
        ->and($p25->vouchers()->count())->toBe(2);

    expect($p30)->not->toBeNull()
        ->and($p30->name)->toBe('Telkomsel Flash Max')
        ->and($p30->quota_description)->toBe('10 GB Kuota Utama 30 Hari')
        ->and($p30->vouchers()->count())->toBe(1);
});

test('bulk import respects per-row title and description from scanned rows', function () {
    $rows = [
        [
            'serial_number' => '300300000001',
            'hrn' => '1111111111111111',
            'sell_price' => 20000,
            'title' => 'Paket A 5GB',
            'description' => '5 GB Kuota 7 Hari',
        ],
        [
            'serial_number' => '300300000002',
            'hrn' => '2222222222222222',
            'sell_price' => 20000,
            'title' => 'Paket B 10GB',
            'description' => '10 GB Kuota 30 Hari',
        ],
    ];

    $this->actingAs($this->admin)
        ->post('/admin/vouchers/bulk', [
            'rows' => $rows,
            'margin_percentage' => 0,
            'city_ids' => [$this->city->id],
        ])
        ->assertRedirect('/admin/vouchers/bulk');

    expect(Voucher::query()->count())->toBe(2);
    expect(Product::query()->count())->toBe(2);

    $v1 = Voucher::query()->with('product')->where('serial_number', '300300000001')->first();
    $v2 = Voucher::query()->with('product')->where('serial_number', '300300000002')->first();

    expect($v1->product->name)->toBe('Paket A 5GB')
        ->and($v1->product->quota_description)->toBe('5 GB Kuota 7 Hari');

    expect($v2->product->name)->toBe('Paket B 10GB')
        ->and($v2->product->quota_description)->toBe('10 GB Kuota 30 Hari');
});

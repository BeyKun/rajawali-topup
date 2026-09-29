<?php

use App\Models\Product;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    $this->withoutVite();
    config(['telkomsel.mock' => true]);
    $this->admin = User::factory()->admin()->create();
});

test('bulk import stores only the valid rows', function () {
    $content = implode("\n", [
        'serial_number,hrn,sell_price',
        '300338120354,71125613431848001,25000',
        '300338120355,71125613431848002,25000',
    ]);

    $file = UploadedFile::fake()->createWithContent('vouchers.csv', $content);

    $this->actingAs($this->admin)
        ->post('/admin/vouchers/bulk', ['file' => $file])
        ->assertRedirect('/admin/vouchers');

    expect(Voucher::query()->count())->toBe(2);

    $product = Product::query()->sole();

    expect((float) $product->sell_price)->toBe(25000.0);
});

test('bulk import skips duplicate serials already in inventory', function () {
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
        ->post('/admin/vouchers/bulk', ['file' => $file])
        ->assertRedirect('/admin/vouchers');

    expect(Voucher::query()->count())->toBe(2);
});

test('bulk import rejects a file without valid rows', function () {
    $file = UploadedFile::fake()->createWithContent('vouchers.csv', "header\nfoo");

    $this->actingAs($this->admin)
        ->post('/admin/vouchers/bulk', ['file' => $file])
        ->assertSessionHasErrors('file');
});

test('bulk import requires a file', function () {
    $this->actingAs($this->admin)
        ->post('/admin/vouchers/bulk', [])
        ->assertSessionHasErrors('file');
});

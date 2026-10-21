<?php

use App\Enums\UserRole;
use App\Models\City;
use App\Models\District;
use App\Models\Province;
use App\Models\User;
use App\Models\Village;
use App\Support\Phone;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->withoutVite();
    $this->admin = User::factory()->admin()->create();

    $province = Province::create(['code' => '11', 'name' => 'Aceh']);
    $this->province = $province;
    $this->city = City::create(['province_id' => $province->id, 'province_code' => '11', 'code' => '11.01', 'name' => 'Aceh Selatan']);
    $this->district = District::create(['city_id' => $this->city->id, 'city_code' => '11.01', 'code' => '11.01.01', 'name' => 'Bakongan']);
    $this->village = Village::create(['district_id' => $this->district->id, 'district_code' => '11.01.01', 'code' => '11.01.01.2001', 'name' => 'Keude Bakongan']);
});

/**
 * Build the outlet payload shared by the create and update requests.
 *
 * @return array<string, mixed>
 */
function outletPayload(array $overrides = []): array
{
    static $counter = 0;
    $counter++;

    $phone = '0812'.str_pad((string) $counter, 7, '0', STR_PAD_LEFT);

    return array_merge([
        'name' => 'Pemilik '.$counter,
        'phone' => $phone,
        'outlet_name' => 'Konter '.$counter,
        'whatsapp' => $phone,
        'province_id' => test()->province->id,
        'city_id' => test()->city->id,
        'district_id' => test()->district->id,
        'village_id' => test()->village->id,
        'address' => 'Jl. Merdeka No. '.$counter,
    ], $overrides);
}

test('the outlet index lists only customer accounts', function () {
    User::factory()->customer()->create(['name' => 'Outlet Aktif', 'phone' => '6281111111111']);
    User::factory()->operator()->create(['name' => 'Operator Gudang', 'phone' => '6281111111112']);

    $this->actingAs($this->admin)
        ->get('/admin/outlets')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/outlets/Index')
            ->has('outlets.data', 1)
            ->where('outlets.data.0.name', 'Outlet Aktif')
            ->where('defaultPassword', 'rajawali123')
        );
});

test('an admin can create an outlet user with the default password', function () {
    $payload = outletPayload();

    $this->actingAs($this->admin)
        ->post('/admin/outlets', $payload)
        ->assertRedirect('/admin/outlets');

    $user = User::query()->where('phone', Phone::normalizeTo62($payload['phone']))->sole();

    expect($user->role)->toBe(UserRole::Customer)
        ->and($user->email)->toBeNull()
        ->and(Hash::check('rajawali123', $user->password))->toBeTrue()
        ->and($user->outletProfile?->outlet_name)->toBe($payload['outlet_name']);
});

test('the outlet phone number is normalized to 628 format', function () {
    $this->actingAs($this->admin)
        ->post('/admin/outlets', outletPayload(['phone' => '081299998888']))
        ->assertRedirect('/admin/outlets');

    $this->assertDatabaseHas('users', ['phone' => '6281299998888', 'role' => UserRole::Customer->value]);
});

test('an outlet phone number must be unique', function () {
    User::factory()->customer()->create(['phone' => '6281299998888']);

    $this->actingAs($this->admin)
        ->post('/admin/outlets', outletPayload(['phone' => '081299998888']))
        ->assertSessionHasErrors('phone');

    expect(User::query()->where('phone', '6281299998888')->count())->toBe(1);
});

test('an outlet without a name or outlet name is rejected', function () {
    $this->actingAs($this->admin)
        ->post('/admin/outlets', outletPayload(['name' => '', 'outlet_name' => '']))
        ->assertSessionHasErrors(['name', 'outlet_name']);
});

test('an admin can update an outlet user and their profile', function () {
    $outlet = User::factory()->customer()->create(['name' => 'Lama', 'phone' => '6281111111111']);
    $outlet->outletProfile()->create([
        'outlet_name' => 'Konter Lama',
        'whatsapp' => '6281111111111',
        'province_id' => $this->province->id,
        'city_id' => $this->city->id,
        'district_id' => $this->district->id,
        'village_id' => $this->village->id,
        'address' => 'Alamat Lama',
    ]);

    $this->actingAs($this->admin)
        ->put("/admin/outlets/{$outlet->id}", outletPayload([
            'name' => 'Baru',
            'phone' => '081200007777',
            'outlet_name' => 'Konter Baru',
        ]))
        ->assertRedirect('/admin/outlets');

    $outlet->refresh();

    expect($outlet->name)->toBe('Baru')
        ->and($outlet->phone)->toBe('6281200007777')
        ->and($outlet->outletProfile?->outlet_name)->toBe('Konter Baru');
});

test('updating an outlet never targets a non-customer account', function () {
    $operator = User::factory()->operator()->create();

    $this->actingAs($this->admin)
        ->put("/admin/outlets/{$operator->id}", outletPayload())
        ->assertNotFound();
});

test('resetting a password falls back to the platform default', function () {
    $outlet = User::factory()->customer()->create(['password' => 'old-password']);
    $outlet->createToken('mobile');

    $this->actingAs($this->admin)
        ->post("/admin/outlets/{$outlet->id}/reset-password", [])
        ->assertRedirect('/admin/outlets');

    expect(Hash::check('rajawali123', $outlet->fresh()->password))->toBeTrue()
        ->and($outlet->tokens()->count())->toBe(0);
});

test('resetting a password accepts an explicit new value', function () {
    $outlet = User::factory()->customer()->create();

    $this->actingAs($this->admin)
        ->post("/admin/outlets/{$outlet->id}/reset-password", ['password' => 'baru-sekali'])
        ->assertRedirect('/admin/outlets');

    expect(Hash::check('baru-sekali', $outlet->fresh()->password))->toBeTrue();
});

test('a too short reset password is rejected', function () {
    $outlet = User::factory()->customer()->create();
    $original = $outlet->password;

    $this->actingAs($this->admin)
        ->post("/admin/outlets/{$outlet->id}/reset-password", ['password' => '123'])
        ->assertSessionHasErrors('password');

    expect($outlet->fresh()->password)->toBe($original);
});

test('guests are redirected away from outlet management', function () {
    $this->get('/admin/outlets')->assertRedirect('/login');
});

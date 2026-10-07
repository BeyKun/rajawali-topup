<?php

use App\Models\City;
use App\Models\District;
use App\Models\OutletProfile;
use App\Models\Province;
use App\Models\User;
use App\Models\Village;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Concurrency Suite
|--------------------------------------------------------------------------
|
| These tests spawn real OS processes against a dedicated PostgreSQL database,
| so they must not use RefreshDatabase (which relies on in-memory SQLite and a
| wrapping transaction that other processes could not observe).
|
*/

pest()->extend(TestCase::class)
    ->in('Concurrency');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Attach a complete outlet profile to a customer so order endpoints are usable.
 */
function completeOutletProfile(User $user): OutletProfile
{
    static $counter = 0;
    $counter++;

    $province = Province::create(['code' => '11', 'name' => 'Aceh '.$counter]);
    $city = City::create(['province_id' => $province->id, 'province_code' => '11', 'code' => '11.'.$counter, 'name' => 'Aceh Selatan '.$counter]);
    $district = District::create(['city_id' => $city->id, 'city_code' => '11.'.$counter, 'code' => '11.'.$counter.'.01', 'name' => 'Bakongan '.$counter]);
    $village = Village::create(['district_id' => $district->id, 'district_code' => '11.'.$counter.'.01', 'code' => '11.'.$counter.'.01.2001', 'name' => 'Keude '.$counter]);

    return OutletProfile::create([
        'user_id' => $user->id,
        'outlet_name' => 'Konter '.$counter,
        'whatsapp' => '08120000'.$counter,
        'province_id' => $province->id,
        'city_id' => $city->id,
        'district_id' => $district->id,
        'village_id' => $village->id,
        'address' => 'Jl. Merdeka '.$counter,
    ]);
}

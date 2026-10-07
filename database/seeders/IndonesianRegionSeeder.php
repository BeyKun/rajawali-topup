<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\District;
use App\Models\Province;
use App\Models\Village;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class IndonesianRegionSeeder extends Seeder
{
    /**
     * Seed Indonesian administrative regions from database/data/wilayah.csv.
     *
     * The CSV uses a single `code,name` pair per row where the number of dots in
     * the code determines the level: 0 = province, 1 = city/regency, 2 = district,
     * 3 = village. Inserts are chunked to keep memory usage flat across the
     * ~91k rows in the source file.
     */
    public function run(): void
    {
        $csvPath = database_path('data/wilayah.csv');

        if (! file_exists($csvPath)) {
            $this->command?->warn("Wilayah CSV file not found at {$csvPath}. Skipping.");

            return;
        }

        $this->command?->info('Seeding Indonesian administrative regions from wilayah.csv...');

        $handle = fopen($csvPath, 'r');

        if ($handle === false) {
            return;
        }

        fgetcsv($handle);

        $now = now()->toDateTimeString();

        $provincesData = [];
        $citiesData = [];
        $districtsData = [];
        $villagesData = [];

        while (($row = fgetcsv($handle)) !== false) {
            if (empty($row[0])) {
                continue;
            }

            $code = trim($row[0]);
            $name = trim($row[1] ?? '');
            $dots = substr_count($code, '.');

            if ($dots === 0) {
                $provincesData[$code] = $name;
            } elseif ($dots === 1) {
                $citiesData[] = [
                    'code' => $code,
                    'prov_code' => substr($code, 0, strpos($code, '.')),
                    'name' => $name,
                ];
            } elseif ($dots === 2) {
                $districtsData[] = [
                    'code' => $code,
                    'city_code' => substr($code, 0, strrpos($code, '.')),
                    'name' => $name,
                ];
            } elseif ($dots === 3) {
                $villagesData[] = [
                    'code' => $code,
                    'district_code' => substr($code, 0, strrpos($code, '.')),
                    'name' => $name,
                ];
            }
        }

        fclose($handle);

        DB::transaction(function () use ($provincesData, $citiesData, $districtsData, $villagesData, $now): void {
            $provinceCodeToId = [];

            foreach ($provincesData as $code => $name) {
                $province = Province::query()->updateOrCreate(
                    ['name' => $name],
                    ['code' => $code],
                );

                $provinceCodeToId[$code] = $province->id;
            }

            $cityCodeToId = [];

            foreach ($citiesData as $city) {
                $provId = $provinceCodeToId[$city['prov_code']] ?? null;

                $existing = City::query()->where('code', $city['code'])->first();

                if ($existing !== null) {
                    $existing->update([
                        'province_id' => $provId,
                        'province_code' => $city['prov_code'],
                        'name' => $city['name'],
                    ]);

                    $cityCodeToId[$city['code']] = $existing->id;

                    continue;
                }

                $created = City::query()->create([
                    'province_id' => $provId,
                    'province_code' => $city['prov_code'],
                    'code' => $city['code'],
                    'name' => $city['name'],
                ]);

                $cityCodeToId[$city['code']] = $created->id;
            }

            $this->seedDistricts($districtsData, $cityCodeToId, $now);
            $this->seedVillages($villagesData, $now);
        });

        $this->command?->info('Indonesian regions seeded successfully.');
    }

    /**
     * Insert districts in chunks and return nothing (villages re-read the code map).
     *
     * @param  array<int, array{code: string, city_code: string, name: string}>  $districtsData
     * @param  array<string, int>  $cityCodeToId
     */
    protected function seedDistricts(array $districtsData, array $cityCodeToId, string $now): void
    {
        $existing = District::query()->count();

        if ($existing >= count($districtsData)) {
            return;
        }

        Village::query()->delete();
        District::query()->delete();

        $chunk = [];

        foreach ($districtsData as $district) {
            $chunk[] = [
                'city_id' => $cityCodeToId[$district['city_code']] ?? null,
                'city_code' => $district['city_code'],
                'code' => $district['code'],
                'name' => $district['name'],
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($chunk) >= 1000) {
                DB::table('districts')->insert($chunk);
                $chunk = [];
            }
        }

        if ($chunk !== []) {
            DB::table('districts')->insert($chunk);
        }
    }

    /**
     * Insert villages in chunks using the freshly built district code map.
     *
     * @param  array<int, array{code: string, district_code: string, name: string}>  $villagesData
     */
    protected function seedVillages(array $villagesData, string $now): void
    {
        $existing = Village::query()->count();

        if ($existing >= count($villagesData)) {
            return;
        }

        Village::query()->delete();

        $districtCodeToId = DB::table('districts')->pluck('id', 'code')->all();

        $chunk = [];

        foreach ($villagesData as $village) {
            $chunk[] = [
                'district_id' => $districtCodeToId[$village['district_code']] ?? null,
                'district_code' => $village['district_code'],
                'code' => $village['code'],
                'name' => $village['name'],
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($chunk) >= 2000) {
                DB::table('villages')->insert($chunk);
                $chunk = [];
            }
        }

        if ($chunk !== []) {
            DB::table('villages')->insert($chunk);
        }
    }
}

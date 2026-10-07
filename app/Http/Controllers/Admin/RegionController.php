<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\District;
use App\Models\Province;
use App\Models\Village;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Region dropdown endpoints (province / city / district / village).
 *
 * Every level supports server-side search and pagination so the dependent
 * searchable selects in the dashboard stay responsive against the ~91k rows of
 * Indonesian administrative data.
 */
class RegionController extends Controller
{
    /**
     * Paginated list of provinces with optional search.
     */
    public function provinces(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $perPage = $this->perPage($request);

        $paginator = Province::query()
            ->when($search !== '', function ($query) use ($search): void {
                $term = '%'.strtolower($search).'%';
                $query->whereRaw('LOWER(name) LIKE ?', [$term]);
            })
            ->orderBy('name')
            ->paginate($perPage, ['id', 'name', 'code']);

        return $this->paginated($paginator);
    }

    /**
     * Paginated list of cities/regencies inside a province.
     */
    public function cities(Request $request): JsonResponse
    {
        $provinceParam = trim((string) $request->query('province', ''));
        $search = trim((string) $request->query('search', ''));
        $perPage = $this->perPage($request);

        if ($provinceParam === '') {
            return $this->emptyPage($perPage);
        }

        $query = City::query();

        if (is_numeric($provinceParam)) {
            $query->where(function ($q) use ($provinceParam): void {
                $q->where('province_id', (int) $provinceParam)
                    ->orWhere('province_code', $provinceParam);
            });
        } else {
            $province = Province::query()
                ->whereRaw('LOWER(name) = ?', [strtolower($provinceParam)])
                ->orWhere('code', $provinceParam)
                ->first();

            if ($province === null) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where(function ($q) use ($province): void {
                    $q->where('province_id', $province->id)
                        ->orWhere('province_code', $province->code);
                });
            }
        }

        $paginator = $query
            ->when($search !== '', function ($q) use ($search): void {
                $term = '%'.strtolower($search).'%';
                $q->whereRaw('LOWER(name) LIKE ?', [$term]);
            })
            ->orderBy('name')
            ->paginate($perPage, ['id', 'province_id', 'province_code', 'name', 'code']);

        return $this->paginated($paginator);
    }

    /**
     * Paginated list of districts inside a city.
     */
    public function districts(Request $request): JsonResponse
    {
        $cityParam = trim((string) $request->query('city', ''));
        $search = trim((string) $request->query('search', ''));
        $perPage = $this->perPage($request);

        if ($cityParam === '') {
            return $this->emptyPage($perPage);
        }

        $query = District::query();

        if (is_numeric($cityParam)) {
            $query->where(function ($q) use ($cityParam): void {
                $q->where('city_id', (int) $cityParam)
                    ->orWhere('city_code', $cityParam);
            });
        } else {
            $city = City::query()
                ->whereRaw('LOWER(name) = ?', [strtolower($cityParam)])
                ->orWhere('code', $cityParam)
                ->first();

            if ($city === null) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where(function ($q) use ($city): void {
                    $q->where('city_id', $city->id)
                        ->orWhere('city_code', $city->code);
                });
            }
        }

        $paginator = $query
            ->when($search !== '', function ($q) use ($search): void {
                $term = '%'.strtolower($search).'%';
                $q->whereRaw('LOWER(name) LIKE ?', [$term]);
            })
            ->orderBy('name')
            ->paginate($perPage, ['id', 'city_id', 'city_code', 'name', 'code']);

        return $this->paginated($paginator);
    }

    /**
     * Paginated list of villages inside a district.
     */
    public function villages(Request $request): JsonResponse
    {
        $districtParam = trim((string) $request->query('district', ''));
        $search = trim((string) $request->query('search', ''));
        $perPage = $this->perPage($request);

        if ($districtParam === '') {
            return $this->emptyPage($perPage);
        }

        $query = Village::query();

        if (is_numeric($districtParam)) {
            $query->where(function ($q) use ($districtParam): void {
                $q->where('district_id', (int) $districtParam)
                    ->orWhere('district_code', $districtParam);
            });
        } else {
            $district = District::query()
                ->whereRaw('LOWER(name) = ?', [strtolower($districtParam)])
                ->orWhere('code', $districtParam)
                ->first();

            if ($district === null) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where(function ($q) use ($district): void {
                    $q->where('district_id', $district->id)
                        ->orWhere('district_code', $district->code);
                });
            }
        }

        $paginator = $query
            ->when($search !== '', function ($q) use ($search): void {
                $term = '%'.strtolower($search).'%';
                $q->whereRaw('LOWER(name) LIKE ?', [$term]);
            })
            ->orderBy('name')
            ->paginate($perPage, ['id', 'district_id', 'district_code', 'name', 'code']);

        return $this->paginated($paginator);
    }

    /**
     * Clamp the requested page size into a safe range.
     */
    protected function perPage(Request $request): int
    {
        return min(max((int) $request->query('per_page', 20), 5), 100);
    }

    /**
     * Standard paginated response shape shared with the mobile dropdowns.
     */
    protected function paginated(mixed $paginator): JsonResponse
    {
        return response()->json([
            'data' => $paginator->items(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'has_more' => $paginator->hasMorePages(),
        ]);
    }

    /**
     * Empty page payload used when a required parent filter is missing.
     */
    protected function emptyPage(int $perPage): JsonResponse
    {
        return response()->json([
            'data' => [],
            'current_page' => 1,
            'last_page' => 1,
            'per_page' => $perPage,
            'total' => 0,
            'has_more' => false,
        ]);
    }
}

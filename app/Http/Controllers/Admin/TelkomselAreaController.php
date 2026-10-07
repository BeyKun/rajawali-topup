<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TelkomselArea;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Maps Telkomsel zone/region strings to administrative cities.
 *
 * Super admin only. Each mapping lets the system attribute vouchers (and the
 * grouped packages) to a kabupaten so outlets see the right catalog.
 */
class TelkomselAreaController extends Controller
{
    /**
     * Display the mapping list with the linked city.
     */
    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString() ?: null;

        $areas = TelkomselArea::query()
            ->with('city.province:id,name')
            ->when($search, function ($query, string $term): void {
                $query->where(function ($inner) use ($term): void {
                    $inner->where('region', 'like', "%{$term}%")
                        ->orWhereHas('city', fn ($city) => $city->where('name', 'like', "%{$term}%"));
                });
            })
            ->orderBy('region')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (TelkomselArea $area): array => [
                'id' => $area->id,
                'region' => $area->region,
                'city_id' => $area->city_id,
                'city_name' => $area->city?->name,
                'province_id' => $area->city?->province_id,
                'province_name' => $area->city?->province?->name,
            ]);

        return Inertia::render('admin/areas/Index', [
            'areas' => $areas,
            'filters' => ['search' => $search],
        ]);
    }

    /**
     * Create a new zone → city mapping.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'region' => ['required', 'string', 'max:100', Rule::unique('telkomsel_areas', 'region')],
            'city_id' => ['required', 'integer', Rule::exists('cities', 'id')],
        ]);

        TelkomselArea::query()->create($validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Mapping area berhasil ditambahkan.']);

        return back();
    }

    /**
     * Update an existing mapping.
     */
    public function update(Request $request, TelkomselArea $area): RedirectResponse
    {
        $validated = $request->validate([
            'region' => ['required', 'string', 'max:100', Rule::unique('telkomsel_areas', 'region')->ignore($area->id)],
            'city_id' => ['required', 'integer', Rule::exists('cities', 'id')],
        ]);

        $area->update($validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Mapping area berhasil diperbarui.']);

        return back();
    }

    /**
     * Delete a mapping.
     */
    public function destroy(TelkomselArea $area): RedirectResponse
    {
        $area->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Mapping area berhasil dihapus.']);

        return back();
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Enums\VoucherStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public catalog endpoints for the mobile application.
 *
 * The catalog is derived automatically from the vouchers in inventory, so the
 * list only surfaces active products that actually have sellable stock.
 */
class ProductController extends Controller
{
    /**
     * List every active product that still has at least one available voucher.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user('sanctum');
        $cityId = $user?->effectiveCityId();

        if ($user !== null && $cityId === null) {
            return response()->json([
                'success' => true,
                'data' => [],
            ]);
        }

        $products = Product::query()
            ->with('category:id,name')
            ->withCount([
                'vouchers as stock_count' => fn ($query) => $query
                    ->where('status', VoucherStatus::Available)
                    ->when($cityId !== null, fn ($q) => $q->forCity($cityId)),
            ])
            ->where('is_active', true)
            ->when($cityId !== null, fn ($query) => $query->forCity($cityId))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->sortBy([
                fn (Product $a, Product $b) => ((int) $b->stock_count > 0 ? 1 : 0) <=> ((int) $a->stock_count > 0 ? 1 : 0),
                fn (Product $a, Product $b) => $a->sort_order <=> $b->sort_order,
                fn (Product $a, Product $b) => $a->id <=> $b->id,
            ])
            ->values();

        return response()->json([
            'success' => true,
            'data' => ProductResource::collection($products)->resolve($request),
        ]);
    }
}

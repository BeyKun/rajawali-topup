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
        $products = Product::query()
            ->with('category:id,name')
            ->withCount([
                'vouchers as stock_count' => fn ($query) => $query->where('status', VoucherStatus::Available),
            ])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->filter(fn (Product $product): bool => (int) $product->stock_count > 0)
            ->values();

        return response()->json([
            'success' => true,
            'data' => ProductResource::collection($products)->resolve($request),
        ]);
    }
}

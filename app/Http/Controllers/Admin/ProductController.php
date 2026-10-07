<?php

namespace App\Http\Controllers\Admin;

use App\Enums\VoucherStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductRequest;
use App\Models\Product;
use App\Models\Voucher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Manages the sellable catalog that the mobile app sees.
 *
 * Products are grouped automatically from voucher metadata, so this screen never
 * creates products by hand. It lets an operator review each grouped package (with
 * live stock), adjust the sell price, toggle availability and reorder the list.
 */
class ProductController extends Controller
{
    /** Available stock at or below this value is treated as low. */
    private const LOW_STOCK_THRESHOLD = 5;

    /**
     * Display the grouped catalog with live available-stock counts.
     */
    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString() ?: null;
        $stock = $request->string('stock')->toString() ?: null;

        $cityId = $request->user()?->isKabupatenAdmin() ? $request->user()->city_id : null;

        $products = Product::query()
            ->forCity($cityId)
            ->withCount([
                'vouchers as available_stock' => fn (Builder $query) => $query
                    ->where('status', VoucherStatus::Available)
                    ->when($cityId, fn (Builder $q) => $q->where('city_id', $cityId)),
                'vouchers as reserved_stock' => fn (Builder $query) => $query
                    ->where('status', VoucherStatus::Reserved)
                    ->when($cityId, fn (Builder $q) => $q->where('city_id', $cityId)),
                'vouchers as total_stock' => fn (Builder $query) => $query
                    ->when($cityId, fn (Builder $q) => $q->where('city_id', $cityId)),
            ])
            ->when($search, fn (Builder $query, string $term) => $query
                ->where(fn (Builder $inner) => $inner
                    ->where('name', 'like', "%{$term}%")
                    ->orWhere('quota_description', 'like', "%{$term}%")
                    ->orWhere('region', 'like', "%{$term}%")))
            ->when($stock === 'low', fn (Builder $query) => $query
                ->whereRaw(
                    '(select count(*) from vouchers where vouchers.product_id = products.id and vouchers.status = ?'
                    . ($cityId ? ' and vouchers.city_id = ?' : '')
                    . ') < ?',
                    $cityId
                        ? [VoucherStatus::Available->value, $cityId, self::LOW_STOCK_THRESHOLD]
                        : [VoucherStatus::Available->value, self::LOW_STOCK_THRESHOLD],
                ))
            ->when($stock === 'empty', fn (Builder $query) => $query
                ->whereRaw(
                    '(select count(*) from vouchers where vouchers.product_id = products.id and vouchers.status = ?'
                    . ($cityId ? ' and vouchers.city_id = ?' : '')
                    . ') = 0',
                    $cityId
                        ? [VoucherStatus::Available->value, $cityId]
                        : [VoucherStatus::Available->value],
                ))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Product $product): array => [
                'id' => $product->id,
                'name' => $product->name,
                'quota_description' => $product->quota_description,
                'validity_days' => $product->validity_days,
                'region' => $product->region,
                'sell_price' => (float) $product->sell_price,
                'hpp_price' => (float) $product->hpp_price,
                'is_active' => $product->is_active,
                'sort_order' => $product->sort_order,
                'available_stock' => (int) $product->available_stock,
                'reserved_stock' => (int) $product->reserved_stock,
                'total_stock' => (int) $product->total_stock,
            ]);

        $summaryQuery = Product::query()->forCity($cityId);

        return Inertia::render('admin/products/Index', [
            'products' => $products,
            'filters' => [
                'search' => $search,
                'stock' => $stock,
            ],
            'summary' => [
                'total_products' => (clone $summaryQuery)->count(),
                'active_products' => (clone $summaryQuery)->where('is_active', true)->count(),
                'available_stock' => Voucher::query()
                    ->forCity($cityId)
                    ->where('status', VoucherStatus::Available)
                    ->count(),
            ],
        ]);
    }

    /**
     * Update the sell price, availability and ordering of a grouped package.
     */
    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $product->update($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Paket {$product->name} berhasil diperbarui.",
        ]);

        return back();
    }
}

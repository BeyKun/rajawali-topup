<?php

namespace App\Http\Controllers\Admin;

use App\Enums\VoucherStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductRequest;
use App\Models\Product;
use App\Models\Voucher;
use App\Models\VoucherOcrLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
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

        $cityIds = $request->user()?->isKabupatenAdmin() ? $request->user()->managedCityIds() : null;

        $products = Product::query()
            ->forCity($cityIds)
            ->withCount([
                'vouchers as available_stock' => fn (Builder $query) => $query
                    ->where('status', VoucherStatus::Available)
                    ->forCity($cityIds),
                'vouchers as reserved_stock' => fn (Builder $query) => $query
                    ->where('status', VoucherStatus::Reserved)
                    ->forCity($cityIds),
                'vouchers as total_stock' => fn (Builder $query) => $query
                    ->forCity($cityIds),
            ])
            ->when($search, fn (Builder $query, string $term) => $query
                ->where(fn (Builder $inner) => $inner
                    ->where('name', 'like', "%{$term}%")
                    ->orWhere('quota_description', 'like', "%{$term}%")
                    ->orWhere('region', 'like', "%{$term}%")))
            ->when($stock === 'low', fn (Builder $query) => $query
                ->where(function (Builder $q) use ($cityIds): void {
                    $q->has('vouchers', '<', self::LOW_STOCK_THRESHOLD, 'and', fn (Builder $vq) => $vq
                        ->where('status', VoucherStatus::Available)
                        ->forCity($cityIds)
                    );
                }))
            ->when($stock === 'empty', fn (Builder $query) => $query
                ->whereDoesntHave('vouchers', fn (Builder $vq) => $vq
                    ->where('status', VoucherStatus::Available)
                    ->forCity($cityIds)
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

        $summaryQuery = Product::query()->forCity($cityIds);

        $isSuperAdmin = (bool) $request->user()?->isSuperAdmin();
        $ocrLogs = $isSuperAdmin
            ? VoucherOcrLog::query()
                ->with(['user:id,name,email,role', 'city.province:id,name'])
                ->latest('created_at')
                ->paginate(15, ['*'], 'ocr_page')
                ->withQueryString()
                ->through(fn (VoucherOcrLog $log): array => [
                    'id' => $log->id,
                    'user_name' => $log->user?->name ?? 'Anonim',
                    'user_email' => $log->user?->email,
                    'user_role' => $log->user?->role?->value,
                    'city_name' => $log->city?->name ?? '-',
                    'image_url' => route('admin.ocr-logs.image', $log->id),
                    'original_filename' => $log->original_filename,
                    'is_success' => $log->is_success,
                    'vouchers_count' => $log->vouchers_count,
                    'extracted_vouchers' => $log->extracted_vouchers ?? [],
                    'raw_texts' => $log->raw_texts ?? [],
                    'error_message' => $log->error_message,
                    'created_at' => $log->created_at?->toIso8601String(),
                ])
            : null;

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
                    ->forCity($cityIds)
                    ->where('status', VoucherStatus::Available)
                    ->count(),
            ],
            'is_super_admin' => $isSuperAdmin,
            'ocr_logs' => $ocrLogs,
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

    /**
     * Delete a product catalog item.
     *
     * Only products with 0 available stock can be deleted. If the product has ever
     * been purchased or has historical records (orders or redeemed/reserved vouchers),
     * it is soft-deleted to preserve transaction history. Otherwise, it is force-deleted.
     */
    public function destroy(Request $request, Product $product): RedirectResponse
    {
        if ($request->user()?->isKabupatenAdmin()) {
            $userCityIds = $request->user()->managedCityIds();
            $hasAccess = $product->city_id !== null && in_array($product->city_id, $userCityIds, true);
            abort_unless($hasAccess, 403, 'Anda tidak berhak menghapus katalog di luar wilayah Anda.');
        }

        // Prevent deletion if there is still active available stock
        $availableStock = $product->vouchers()
            ->where('status', VoucherStatus::Available)
            ->count();

        if ($availableStock > 0) {
            throw ValidationException::withMessages([
                'product' => 'Katalog tidak dapat dihapus karena masih memiliki stok voucher yang aktif.',
            ]);
        }

        $hasBeenPurchased = $product->orders()->exists()
            || $product->vouchers()->whereIn('status', [VoucherStatus::Redeemed, VoucherStatus::Reserved])->exists();

        if ($hasBeenPurchased) {
            $product->delete();
            $message = "Katalog {$product->name} berhasil dihapus (diarsipkan).";
        } else {
            // Delete any unredeemed vouchers first to avoid orphaned records or foreign key restrictions
            $product->vouchers()->delete();
            $product->forceDelete();
            $message = "Katalog {$product->name} berhasil dihapus permanen.";
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $message,
        ]);

        return back();
    }
}

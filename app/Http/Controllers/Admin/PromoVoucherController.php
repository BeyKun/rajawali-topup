<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DiscountType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PromoVoucherRequest;
use App\Models\PromoVoucher;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Admin CRUD for promo vouchers (marketing discounts applied at checkout).
 *
 * These are unrelated to the physical Telkomsel voucher inventory managed by
 * {@see VoucherController}; every "voucher" here is a discount code. The
 * combined listing is rendered by {@see PromoController}.
 */
class PromoVoucherController extends Controller
{
    /**
     * Create a promo voucher.
     */
    public function store(PromoVoucherRequest $request): RedirectResponse
    {
        PromoVoucher::query()->create($this->attributes($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Voucher promo berhasil dibuat.']);

        return to_route('admin.promos.index');
    }

    /**
     * Update a promo voucher.
     */
    public function update(PromoVoucherRequest $request, PromoVoucher $voucher): RedirectResponse
    {
        $voucher->update($this->attributes($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Voucher promo berhasil diperbarui.']);

        return to_route('admin.promos.index');
    }

    /**
     * Delete a promo voucher.
     */
    public function destroy(PromoVoucher $voucher): RedirectResponse
    {
        $voucher->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Voucher promo berhasil dihapus.']);

        return to_route('admin.promos.index');
    }

    /**
     * Extract the persistable attributes from a validated request.
     *
     * @return array<string, mixed>
     */
    protected function attributes(PromoVoucherRequest $request): array
    {
        return [
            'code' => PromoVoucher::normalizeCode((string) $request->validated('code')),
            'title' => $request->string('title')->toString(),
            'description' => $request->string('description')->toString() ?: null,
            'discount_type' => DiscountType::from((string) $request->validated('discount_type')),
            'discount_value' => (float) $request->validated('discount_value'),
            'max_discount_amount' => $request->filled('max_discount_amount') ? (float) $request->validated('max_discount_amount') : null,
            'min_spend' => (float) $request->validated('min_spend', 0),
            'usage_limit' => $request->filled('usage_limit') ? (int) $request->validated('usage_limit') : null,
            'start_date' => $request->validated('start_date'),
            'end_date' => $request->validated('end_date'),
            'is_active' => $request->boolean('is_active', true),
        ];
    }
}

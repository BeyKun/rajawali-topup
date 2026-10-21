<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DiscountType;
use App\Http\Controllers\Controller;
use App\Models\PromoBanner;
use App\Models\PromoVoucher;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The unified "Promo & Voucher" page.
 *
 * Banners and promo vouchers are managed from one tabbed screen, so this
 * controller supplies both datasets; the CRUD itself lives in
 * {@see PromoBannerController} and {@see PromoVoucherController}.
 */
class PromoController extends Controller
{
    /**
     * Render both the banner carousel list and the promo voucher list.
     */
    public function index(): Response
    {
        $banners = PromoBanner::query()
            ->orderBy('sort_order')
            ->latest('id')
            ->get()
            ->map(fn (PromoBanner $banner): array => $this->presentBanner($banner));

        $promoVouchers = PromoVoucher::query()
            ->latest('id')
            ->get()
            ->map(fn (PromoVoucher $voucher): array => $this->presentVoucher($voucher));

        return Inertia::render('admin/promos/Index', [
            'banners' => $banners,
            'promoVouchers' => $promoVouchers,
            'discountTypes' => array_map(fn (DiscountType $type): string => $type->value, DiscountType::cases()),
        ]);
    }

    /**
     * Shape a banner for the Inertia response.
     *
     * @return array<string, mixed>
     */
    protected function presentBanner(PromoBanner $banner): array
    {
        return [
            'id' => $banner->id,
            'title' => $banner->title,
            'description' => $banner->description,
            'image_path' => $banner->image_path,
            'image_url' => Storage::disk('public')->url($banner->image_path),
            'slider_image_path' => $banner->image_path,
            'slider_image_url' => Storage::disk('public')->url($banner->image_path),
            'popup_image_path' => $banner->popup_image_path,
            'popup_image_url' => $banner->popup_image_path ? Storage::disk('public')->url($banner->popup_image_path) : null,
            'target_url' => $banner->target_url,
            'is_active' => $banner->is_active,
            'is_featured' => $banner->is_featured,
            'sort_order' => $banner->sort_order,
            'created_at' => $banner->created_at?->toIso8601String(),
        ];
    }

    /**
     * Shape a promo voucher for the Inertia response.
     *
     * @return array<string, mixed>
     */
    protected function presentVoucher(PromoVoucher $voucher): array
    {
        return [
            'id' => $voucher->id,
            'code' => $voucher->code,
            'title' => $voucher->title,
            'description' => $voucher->description,
            'discount_type' => $voucher->discount_type->value,
            'discount_value' => (float) $voucher->discount_value,
            'max_discount_amount' => $voucher->max_discount_amount !== null ? (float) $voucher->max_discount_amount : null,
            'min_spend' => (float) $voucher->min_spend,
            'usage_limit' => $voucher->usage_limit,
            'used_count' => $voucher->used_count,
            'start_date' => $voucher->start_date->toDateString(),
            'end_date' => $voucher->end_date->toDateString(),
            'is_active' => $voucher->is_active,
            'is_usable' => $voucher->isUsable(),
            'created_at' => $voucher->created_at?->toIso8601String(),
        ];
    }
}

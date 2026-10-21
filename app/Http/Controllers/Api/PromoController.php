<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ValidatePromoRequest;
use App\Http\Resources\PromoBannerResource;
use App\Http\Resources\PromoVoucherResource;
use App\Models\Product;
use App\Models\PromoBanner;
use App\Models\PromoVoucher;
use App\Services\PromoVoucherService;
use Illuminate\Http\JsonResponse;

/**
 * Promo banners and voucher validation for the mobile application.
 */
class PromoController extends Controller
{
    public function __construct(private readonly PromoVoucherService $promoVouchers) {}

    /**
     * Return the active promo banners used by the home carousel.
     */
    public function banners(): JsonResponse
    {
        $banners = PromoBanner::query()->active()->get();

        return response()->json([
            'success' => true,
            'data' => PromoBannerResource::collection($banners)->resolve(request()),
        ]);
    }

    /**
     * Return the promo vouchers a customer can currently use.
     */
    public function index(): JsonResponse
    {
        $vouchers = PromoVoucher::query()->usable()->latest('id')->get();

        return response()->json([
            'success' => true,
            'data' => PromoVoucherResource::collection($vouchers)->resolve(request()),
        ]);
    }

    /**
     * Validate a promo code against a product and return the computed discount.
     */
    public function validate(ValidatePromoRequest $request): JsonResponse
    {
        /** @var Product $product */
        $product = Product::query()->findOrFail((int) $request->validated('product_id'));

        $amount = (float) $product->sell_price;

        $voucher = $this->promoVouchers->validate((string) $request->validated('code'), $amount);

        $discount = $voucher->discountFor($amount);

        return response()->json([
            'success' => true,
            'valid' => true,
            'code' => $voucher->code,
            'title' => $voucher->title,
            'discount_amount' => (int) round($discount),
            'final_amount' => (int) round(max(0, $amount - $discount)),
            'message' => $this->promoVouchers->messageFor($voucher, $discount),
        ]);
    }
}

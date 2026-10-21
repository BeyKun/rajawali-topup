<?php

namespace App\Services;

use App\Exceptions\InvalidPromoCodeException;
use App\Models\PromoVoucher;

/**
 * Validates promo voucher codes and computes the resulting discount.
 *
 * Promo vouchers are marketing discounts applied to the order total; they are
 * unrelated to the physical Telkomsel `vouchers` inventory handled by
 * {@see OrderService}. All checks live here so the mobile "validate" endpoint
 * and the order-creation flow can never drift apart.
 */
class PromoVoucherService
{
    /**
     * Resolve a voucher code for display/validation without locking it.
     *
     * @throws InvalidPromoCodeException When the code is unknown or unusable.
     */
    public function validate(string $code, float $amount): PromoVoucher
    {
        $voucher = PromoVoucher::query()
            ->where('code', PromoVoucher::normalizeCode($code))
            ->first();

        return $this->assertUsable($voucher, $amount);
    }

    /**
     * Lock a voucher row and verify it for use inside an order transaction.
     *
     * The row is locked with `lockForUpdate()` so a quota-limited voucher can
     * never be redeemed by two concurrent orders; the caller is expected to
     * increment `used_count` before the transaction commits.
     *
     * @throws InvalidPromoCodeException When the code is unknown or unusable.
     */
    public function lockForOrder(?string $code, float $amount): ?PromoVoucher
    {
        if ($code === null || trim($code) === '') {
            return null;
        }

        $voucher = PromoVoucher::query()
            ->where('code', PromoVoucher::normalizeCode($code))
            ->lockForUpdate()
            ->first();

        return $this->assertUsable($voucher, $amount);
    }

    /**
     * Assert that a voucher exists, is active, in period and within quota.
     *
     * @throws InvalidPromoCodeException When the voucher cannot be applied.
     */
    protected function assertUsable(?PromoVoucher $voucher, float $amount): PromoVoucher
    {
        if ($voucher === null) {
            throw new InvalidPromoCodeException('Kode promo tidak ditemukan.');
        }

        if (! $voucher->is_active) {
            throw new InvalidPromoCodeException('Kode promo sudah tidak aktif.');
        }

        $today = now()->startOfDay();

        if ($voucher->start_date->greaterThan($today)) {
            throw new InvalidPromoCodeException('Kode promo belum berlaku.');
        }

        if ($voucher->end_date->lessThan($today)) {
            throw new InvalidPromoCodeException('Kode promo sudah kedaluwarsa.');
        }

        if ($voucher->usage_limit !== null && $voucher->used_count >= $voucher->usage_limit) {
            throw new InvalidPromoCodeException('Kuota kode promo sudah habis.');
        }

        if (! $voucher->meetsMinSpend($amount)) {
            throw new InvalidPromoCodeException(
                'Belanja minimal '.number_format((float) $voucher->min_spend, 0, ',', '.').' untuk memakai kode ini.'
            );
        }

        return $voucher;
    }

    /**
     * Build a human-readable summary of an applied discount.
     */
    public function messageFor(PromoVoucher $voucher, float $discount): string
    {
        return 'Diskon Rp '.number_format($discount, 0, ',', '.').' diterapkan';
    }
}

<?php

namespace App\Services;

use App\Enums\VoucherStatus;
use App\Models\Product;
use App\Models\TelkomselArea;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Ingests physical Telkomsel vouchers into the sellable inventory pool.
 *
 * Every voucher is validated against the Telkomsel check API before it is
 * persisted, the secret HRN is stored encrypted (via the model cast) and the
 * raw API response is kept for audit purposes.
 *
 * The sellable catalog is now derived automatically: each voucher's Telkomsel
 * metadata (package name, quota, validity, region) is grouped into a Product so
 * the mobile app can list it without a separate product-management screen.
 */
class VoucherInventoryService
{
    /** Telkomsel /check statusCode signalling a sellable voucher. */
    private const CHECK_STATUS_AVAILABLE = 1;

    /** Maximum length of a generated product slug. */
    private const PRODUCT_SLUG_LIMIT = 120;

    public function __construct(private readonly TelkomselVoucherService $telkomsel) {}

    /**
     * Validate and persist a single physical voucher into the inventory.
     *
     * @param  float|null  $sellPrice  Price the end user pays for this package (per voucher input).
     *
     * @throws ValidationException When the voucher is invalid, already used or duplicated.
     */
    public function addVoucher(string $serialNumber, string $hrn, ?float $sellPrice = null, ?int $adminId = null): Voucher
    {
        $serialNumber = trim($serialNumber);
        $hrn = trim($hrn);

        if (Voucher::query()->where('serial_number', $serialNumber)->exists()) {
            throw ValidationException::withMessages([
                'serial_number' => "Voucher dengan serial number {$serialNumber} sudah ada di inventori.",
            ]);
        }

        $check = $this->telkomsel->checkVoucher($serialNumber);

        if (! $check['is_valid'] || $check['status_code'] !== self::CHECK_STATUS_AVAILABLE) {
            throw ValidationException::withMessages([
                'serial_number' => sprintf(
                    'Voucher tidak dapat ditambahkan: %s (kode %d).',
                    $check['status_message'],
                    $check['status_code'],
                ),
            ]);
        }

        return DB::transaction(function () use ($check, $serialNumber, $hrn, $sellPrice, $adminId): Voucher {
            $cityId = $this->resolveCityId($check['region'], $adminId);

            $product = $this->resolveProduct($check, $sellPrice, $cityId);

            return Voucher::query()->create([
                'product_id' => $product->id,
                'serial_number' => $check['serial_number'] !== '' ? $check['serial_number'] : $serialNumber,
                'hrn' => $hrn,
                'status' => VoucherStatus::Available,
                'validity' => $check['validity'] !== '' ? $check['validity'] : null,
                'expired_date' => $check['expired_date'] !== '' ? $check['expired_date'] : null,
                'region' => $check['region'] !== '' ? $check['region'] : null,
                'city_id' => $cityId,
                'telkomsel_check_response' => $check['raw'],
                'created_by' => $adminId,
            ]);
        });
    }

    /**
     * Delete a voucher, but only while it is still available for sale.
     *
     * @throws ValidationException When the voucher has been reserved, redeemed or failed.
     */
    public function deleteAvailableVoucher(Voucher $voucher): void
    {
        if ($voucher->status !== VoucherStatus::Available) {
            throw ValidationException::withMessages([
                'voucher' => 'Hanya voucher berstatus TERSEDIA yang dapat dihapus.',
            ]);
        }

        $voucher->delete();
    }

    /**
     * Find or create the sellable product described by the Telkomsel metadata.
     *
     * Vouchers of the same package (same name, quota, validity and region) share
     * a single Product. A supplied sell price updates the product so the mobile
     * catalog always reflects the latest price the admin entered.
     *
     * @param  array{name: string, description: string, validity: string, region: string}  $check
     */
    protected function resolveProduct(array $check, ?float $sellPrice, ?int $cityId = null): Product
    {
        $name = $this->deriveProductName($check);
        $slug = $this->deriveProductSlug($name, $check, $cityId);

        $product = Product::query()->firstOrNew(['slug' => $slug]);

        $product->name = $name;
        $product->quota_description = $check['description'] !== '' ? $check['description'] : $name;
        $product->validity_days = $this->parseValidityDays($check['validity']);
        $product->region = $check['region'] !== '' ? $check['region'] : null;
        $product->hpp_price = $product->hpp_price ?? 0;

        if ($cityId !== null) {
            $product->city_id = $cityId;
        }

        if ($sellPrice !== null) {
            $product->sell_price = $sellPrice;
        }

        $product->sell_price = $product->sell_price ?? 0;
        $product->is_active = true;
        $product->sort_order = $product->sort_order ?? 0;

        $product->save();

        return $product;
    }

    /**
     * Resolve the administrative city for a voucher.
     *
     * The Telkomsel zone returned by /check is looked up in `telkomsel_areas`.
     * When the zone is not mapped, the acting admin's own city is used as the
     * fallback so a kabupaten admin's vouchers are always attributed to them.
     */
    protected function resolveCityId(string $region, ?int $adminId): ?int
    {
        $region = trim($region);

        if ($region !== '') {
            $mapped = TelkomselArea::query()
                ->whereRaw('LOWER(region) = ?', [strtolower($region)])
                ->value('city_id');

            if ($mapped !== null) {
                return (int) $mapped;
            }
        }

        if ($adminId !== null) {
            $cityId = User::query()->whereKey($adminId)->value('city_id');

            if ($cityId !== null) {
                return (int) $cityId;
            }
        }

        return null;
    }

    /**
     * Build a human-readable product name from the Telkomsel package metadata.
     *
     * @param  array{name: string, description: string, validity: string, region: string}  $check
     */
    protected function deriveProductName(array $check): string
    {
        $name = trim($check['name']) !== '' ? trim($check['name']) : 'Paket Telkomsel';

        if ($check['validity'] !== '') {
            $name .= " {$check['validity']} Hari";
        }

        return Str::limit($name, 150, '');
    }

    /**
     * Derive a stable, unique slug that also encodes the package identity.
     *
     * The identity hash guarantees that two packages sharing a display name but
     * differing in quota, region or assigned city never collapse into a single product.
     *
     * @param  array{name: string, description: string, validity: string, region: string}  $check
     */
    protected function deriveProductSlug(string $name, array $check, ?int $cityId = null): string
    {
        $identity = implode('|', [$name, $check['description'], $check['validity'], $check['region'], (string) $cityId]);
        $suffix = substr(sha1($identity), 0, 8);
        $base = Str::limit(Str::slug($name), self::PRODUCT_SLUG_LIMIT, '');

        return $base === '' ? $suffix : "{$base}-{$suffix}";
    }

    /**
     * Convert the Telkomsel validity string into a number of days.
     *
     * @param  string  $validity  Telkomsel returns the validity as a day count (e.g. "5").
     */
    protected function parseValidityDays(string $validity): int
    {
        $days = (int) filter_var($validity, FILTER_SANITIZE_NUMBER_INT);

        return $days > 0 ? $days : 1;
    }
}

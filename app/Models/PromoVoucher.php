<?php

namespace App\Models;

use App\Enums\DiscountType;
use Database\Factories\PromoVoucherFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A marketing discount applied on top of an order, separate from the physical
 * Telkomsel voucher inventory.
 *
 * @property int $id
 * @property string $code
 * @property string $title
 * @property string|null $description
 * @property DiscountType $discount_type
 * @property string $discount_value
 * @property string|null $max_discount_amount
 * @property string $min_spend
 * @property int|null $usage_limit
 * @property int $used_count
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['code', 'title', 'description', 'discount_type', 'discount_value', 'max_discount_amount', 'min_spend', 'usage_limit', 'used_count', 'start_date', 'end_date', 'is_active'])]
class PromoVoucher extends Model
{
    /** @use HasFactory<PromoVoucherFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'discount_type' => DiscountType::class,
            'discount_value' => 'decimal:2',
            'max_discount_amount' => 'decimal:2',
            'min_spend' => 'decimal:2',
            'usage_limit' => 'integer',
            'used_count' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Normalize the code for lookups (uppercase, trimmed).
     */
    public static function normalizeCode(string $code): string
    {
        return strtoupper(trim($code));
    }

    /**
     * Limit the query to vouchers that are usable right now.
     *
     * @param  Builder<PromoVoucher>  $query
     */
    public function scopeUsable(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->whereDate('start_date', '<=', now()->toDateString())
            ->whereDate('end_date', '>=', now()->toDateString())
            ->where(function (Builder $inner): void {
                $inner->whereNull('usage_limit')
                    ->orWhereColumn('used_count', '<', 'usage_limit');
            });
    }

    /**
     * Whether this voucher can still be redeemed today.
     */
    public function isUsable(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $today = now()->startOfDay();

        if ($this->start_date->greaterThan($today) || $this->end_date->lessThan($today)) {
            return false;
        }

        return $this->usage_limit === null || $this->used_count < $this->usage_limit;
    }

    /**
     * Compute the rupiah discount for a given order amount.
     *
     * Percentage vouchers are capped by `max_discount_amount` (when set) and
     * the result is never allowed to exceed the order amount itself.
     */
    public function discountFor(float $amount): float
    {
        $discount = match ($this->discount_type) {
            DiscountType::Percentage => $amount * ((float) $this->discount_value / 100),
            DiscountType::Fixed => (float) $this->discount_value,
        };

        if ($this->discount_type === DiscountType::Percentage && $this->max_discount_amount !== null) {
            $discount = min($discount, (float) $this->max_discount_amount);
        }

        return round(min($discount, $amount), 2);
    }

    /**
     * Whether the given order amount satisfies the minimum spend.
     */
    public function meetsMinSpend(float $amount): bool
    {
        return $amount >= (float) $this->min_spend;
    }
}

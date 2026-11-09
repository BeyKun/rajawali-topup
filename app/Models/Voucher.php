<?php

namespace App\Models;

use App\Enums\VoucherStatus;
use App\Events\CatalogUpdated;
use Database\Factories\VoucherFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $product_id
 * @property string $serial_number
 * @property string $hrn
 * @property VoucherStatus $status
 * @property string|null $validity
 * @property string|null $expired_date
 * @property string|null $region
 * @property int|null $city_id
 * @property array|null $telkomsel_check_response
 * @property Carbon|null $reserved_at
 * @property Carbon|null $redeemed_at
 * @property string|null $redeemed_msisdn
 * @property string|null $telkomsel_trace_id
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['product_id', 'serial_number', 'hrn', 'status', 'validity', 'expired_date', 'region', 'city_id', 'telkomsel_check_response', 'reserved_at', 'redeemed_at', 'redeemed_msisdn', 'telkomsel_trace_id', 'created_by'])]
#[Hidden(['hrn'])]
class Voucher extends Model
{
    /** @use HasFactory<VoucherFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saved(fn () => CatalogUpdated::dispatchSafely('voucher_saved'));
        static::deleted(fn () => CatalogUpdated::dispatchSafely('voucher_deleted'));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'hrn' => 'encrypted',
            'status' => VoucherStatus::class,
            'telkomsel_check_response' => 'array',
            'reserved_at' => 'datetime',
            'redeemed_at' => 'datetime',
        ];
    }

    public function resolveRouteBinding($value, $field = null): ?Model
    {
        return $this->where('serial_number', (string) $value)
            ->orWhere('id', $value)
            ->first();
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function cities(): BelongsToMany
    {
        return $this->belongsToMany(City::class, 'voucher_cities');
    }

    /**
     * Limit the query to a city or list of cities, used to scope kabupaten admins.
     *
     * @param  Builder<Voucher>  $query
     */
    public function scopeForCity(Builder $query, int|array|null $cityId): Builder
    {
        if ($cityId === null) {
            return $query;
        }

        if (is_array($cityId) && empty($cityId)) {
            return $query->whereRaw('1 = 0');
        }

        $cityIds = is_array($cityId) ? $cityId : [$cityId];

        return $query->where(function (Builder $q) use ($cityIds): void {
            $q->whereIn('vouchers.city_id', $cityIds)
                ->orWhereHas('cities', fn (Builder $cityQuery) => $cityQuery->whereIn('cities.id', $cityIds))
                ->orWhere(function (Builder $pq) use ($cityIds): void {
                    $pq->whereNull('vouchers.city_id')
                        ->whereDoesntHave('cities')
                        ->whereHas('product', fn (Builder $prodQuery) => $prodQuery->whereIn('products.city_id', $cityIds));
                });
        });
    }
}

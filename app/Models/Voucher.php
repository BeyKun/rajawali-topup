<?php

namespace App\Models;

use App\Enums\VoucherStatus;
use Database\Factories\VoucherFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function cities(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(City::class, 'voucher_cities');
    }

    /**
     * Limit the query to a single city, used to scope kabupaten admins.
     *
     * @param  Builder<Voucher>  $query
     */
    public function scopeForCity(Builder $query, ?int $cityId): Builder
    {
        if ($cityId === null) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($cityId): void {
            $q->where('vouchers.city_id', $cityId)
                ->orWhereHas('cities', fn (Builder $cityQuery) => $cityQuery->where('cities.id', $cityId));
        });
    }
}

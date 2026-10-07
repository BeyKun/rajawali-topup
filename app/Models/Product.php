<?php

namespace App\Models;

use App\Enums\VoucherStatus;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $category_id
 * @property string $name
 * @property string $slug
 * @property string $quota_description
 * @property int $validity_days
 * @property string|null $region
 * @property int|null $city_id
 * @property string $hpp_price
 * @property string $sell_price
 * @property bool $is_active
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read int $available_stock
 * @property-read int|null $reserved_stock
 * @property-read int|null $total_stock
 * @property-read int|null $stock_count
 */
#[Fillable(['category_id', 'name', 'slug', 'quota_description', 'validity_days', 'region', 'city_id', 'hpp_price', 'sell_price', 'is_active', 'sort_order'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'hpp_price' => 'decimal:2',
            'sell_price' => 'decimal:2',
            'validity_days' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function vouchers(): HasMany
    {
        return $this->hasMany(Voucher::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /**
     * Limit the query to products owned by a city, used to scope kabupaten admins.
     *
     * @param  Builder<Product>  $query
     */
    public function scopeForCity(Builder $query, ?int $cityId): Builder
    {
        return $cityId === null ? $query : $query->where('city_id', $cityId);
    }

    /**
     * Get the number of vouchers currently available for this product.
     */
    protected function availableStock(): Attribute
    {
        return Attribute::get(function ($value, array $attributes): int {
            if (array_key_exists('available_stock', $attributes)) {
                return (int) $attributes['available_stock'];
            }

            return $this->vouchers()
                ->where('status', VoucherStatus::Available)
                ->when($this->city_id, fn ($q) => $q->where('city_id', $this->city_id))
                ->count();
        });
    }
}

<?php

namespace App\Models;

use App\Enums\VoucherStatus;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
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
#[Fillable(['category_id', 'name', 'slug', 'quota_description', 'validity_days', 'region', 'hpp_price', 'sell_price', 'is_active', 'sort_order'])]
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

    /**
     * Get the number of vouchers currently available for this product.
     */
    protected function availableStock(): Attribute
    {
        return Attribute::get(fn (): int => $this->vouchers()
            ->where('status', VoucherStatus::Available)
            ->count());
    }
}

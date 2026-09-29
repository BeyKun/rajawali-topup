<?php

namespace App\Http\Resources;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 *
 * @property-read Category|null $category
 * @property-read int|null $stock_count
 * @property-read int|null $available_stock
 */
class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $stockCount = (int) ($this->stock_count ?? $this->available_stock ?? 0);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'category' => $this->category?->name,
            'quota_description' => $this->quota_description,
            'validity_days' => $this->validity_days,
            'sell_price' => (int) $this->sell_price,
            'is_available' => $stockCount > 0,
            'stock_count' => $stockCount,
        ];
    }
}

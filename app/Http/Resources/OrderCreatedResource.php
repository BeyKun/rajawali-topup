<?php

namespace App\Http\Resources;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Order
 *
 * @property-read Product|null $product
 */
class OrderCreatedResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'order_no' => $this->order_no,
            'msisdn' => $this->msisdn,
            'product_name' => $this->product?->name,
            'total_amount' => (int) $this->total_amount,
            'qris_string' => $this->qris_string,
            'qris_url' => $this->qris_url,
            'expired_at' => $this->qris_expired_at?->toIso8601String(),
        ];
    }
}

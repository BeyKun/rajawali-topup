<?php

namespace App\Http\Resources;

use App\Models\PromoVoucher;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PromoVoucher
 */
class PromoVoucherResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'title' => $this->title,
            'description' => $this->description,
            'discount_type' => $this->discount_type->value,
            'discount_value' => (float) $this->discount_value,
            'max_discount_amount' => $this->max_discount_amount !== null ? (float) $this->max_discount_amount : null,
            'min_spend' => (float) $this->min_spend,
            'start_date' => $this->start_date->toDateString(),
            'end_date' => $this->end_date->toDateString(),
        ];
    }
}

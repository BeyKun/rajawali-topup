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
class OrderHistoryResource extends JsonResource
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
            'product_name' => $this->product?->name,
            'msisdn' => $this->msisdn,
            'total_amount' => (int) $this->total_amount,
            'payment_status' => $this->payment_status->value,
            'redeem_status' => $this->redeem_status->value,
            'sn' => $this->telkomselSn(),
            'expired_at' => $this->qris_expired_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * Extract the Telkomsel serial number without leaking the secret HRN.
     */
    protected function telkomselSn(): ?string
    {
        $raw = $this->redeem_response_raw;

        if (! is_array($raw)) {
            return null;
        }

        $sn = data_get($raw, 'data.sn');

        return is_string($sn) && $sn !== '' ? $sn : null;
    }
}

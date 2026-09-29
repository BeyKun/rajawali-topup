<?php

namespace App\Http\Resources;

use App\Enums\PaymentStatus;
use App\Enums\RedeemStatus;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Order
 *
 * @property-read Product|null $product
 */
class OrderStatusResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * The QRIS payload and order summary are included so the mobile app can
     * resume an unpaid order (for example from the history screen) even after
     * a restart, when no in-memory copy of the order exists. The QRIS fields
     * are only meaningful while the order is still awaiting payment.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $awaitingPayment = $this->payment_status === PaymentStatus::Unpaid;

        return [
            'order_no' => $this->order_no,
            'msisdn' => $this->msisdn,
            'product_name' => $this->product?->name,
            'total_amount' => (int) $this->total_amount,
            'payment_status' => $this->payment_status->value,
            'redeem_status' => $this->redeem_status->value,
            'message' => $this->statusMessage(),
            'sn' => $this->telkomselSn(),
            'qris_string' => $awaitingPayment ? $this->qris_string : null,
            'qris_url' => $awaitingPayment ? $this->qris_url : null,
            'expired_at' => $this->qris_expired_at?->toIso8601String(),
        ];
    }

    /**
     * Build the human-readable status message shown to the mobile user.
     */
    protected function statusMessage(): string
    {
        if ($this->payment_status === PaymentStatus::Unpaid) {
            return 'Menunggu pembayaran QRIS';
        }

        if ($this->payment_status === PaymentStatus::Paid) {
            return match ($this->redeem_status) {
                RedeemStatus::Success => "Paket data berhasil diaktifkan ke {$this->msisdn}",
                RedeemStatus::Failed => 'Transaksi sedang dicek oleh operator',
                default => 'Pembayaran diterima, memproses paket data...',
            };
        }

        if ($this->payment_status === PaymentStatus::Canceled || $this->payment_status === PaymentStatus::Expired) {
            return 'Pesanan dibatalkan atau kedaluwarsa. Silakan buat pesanan baru.';
        }

        if ($this->payment_status === PaymentStatus::Failed) {
            return 'Transaksi sedang dicek oleh operator';
        }

        return 'Menunggu pembayaran QRIS';
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

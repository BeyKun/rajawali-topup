<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Enums\RedeemStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $order_no
 * @property int $user_id
 * @property int $product_id
 * @property int|null $voucher_id
 * @property string $msisdn
 * @property string $amount
 * @property string $admin_fee
 * @property string $total_amount
 * @property string $payment_channel
 * @property PaymentStatus $payment_status
 * @property RedeemStatus $redeem_status
 * @property string|null $qris_string
 * @property string|null $qris_url
 * @property Carbon|null $qris_expired_at
 * @property string|null $payment_ref_id
 * @property Carbon|null $paid_at
 * @property string|null $redeem_response_code
 * @property array|null $redeem_response_raw
 * @property int $retry_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['order_no', 'user_id', 'product_id', 'voucher_id', 'msisdn', 'amount', 'admin_fee', 'total_amount', 'payment_channel', 'payment_status', 'redeem_status', 'qris_string', 'qris_url', 'qris_expired_at', 'payment_ref_id', 'paid_at', 'redeem_response_code', 'redeem_response_raw', 'retry_count'])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'admin_fee' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'payment_status' => PaymentStatus::class,
            'redeem_status' => RedeemStatus::class,
            'qris_expired_at' => 'datetime',
            'paid_at' => 'datetime',
            'redeem_response_raw' => 'array',
            'retry_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }
}

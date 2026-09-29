<?php

namespace App\Models;

use Database\Factories\TelkomselApiLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $order_id
 * @property int|null $voucher_id
 * @property string $endpoint
 * @property array $request_payload
 * @property int $response_code
 * @property array|null $response_payload
 * @property int $duration_ms
 * @property Carbon|null $created_at
 */
#[Fillable(['order_id', 'voucher_id', 'endpoint', 'request_payload', 'response_code', 'response_payload', 'duration_ms'])]
class TelkomselApiLog extends Model
{
    /** @use HasFactory<TelkomselApiLogFactory> */
    use HasFactory;

    public const CREATED_AT = 'created_at';

    public const UPDATED_AT = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'request_payload' => 'array',
            'response_payload' => 'array',
        ];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property int|null $city_id
 * @property string $image_path
 * @property string|null $original_filename
 * @property bool $is_success
 * @property int $vouchers_count
 * @property array|null $extracted_vouchers
 * @property array|null $raw_texts
 * @property string|null $error_message
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'city_id',
    'image_path',
    'original_filename',
    'is_success',
    'vouchers_count',
    'extracted_vouchers',
    'raw_texts',
    'error_message',
])]
class VoucherOcrLog extends Model
{
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_success' => 'boolean',
            'vouchers_count' => 'integer',
            'extracted_vouchers' => 'array',
            'raw_texts' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }
}


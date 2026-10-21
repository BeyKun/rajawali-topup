<?php

namespace App\Models;

use Database\Factories\PromoBannerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $title
 * @property string|null $description
 * @property string $image_path
 * @property string|null $popup_image_path
 * @property string|null $target_url
 * @property bool $is_active
 * @property bool $is_featured
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['title', 'description', 'image_path', 'popup_image_path', 'target_url', 'is_active', 'is_featured', 'sort_order'])]
class PromoBanner extends Model
{
    /** @use HasFactory<PromoBannerFactory> */
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
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Limit the query to banners that should appear in the mobile carousel.
     *
     * @param  Builder<PromoBanner>  $query
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }
}

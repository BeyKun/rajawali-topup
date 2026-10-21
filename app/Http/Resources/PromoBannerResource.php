<?php

namespace App\Http\Resources;

use App\Models\PromoBanner;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin PromoBanner
 */
class PromoBannerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $filename = basename($this->image_path);
        $imageUrl = url("/api/v1/promo-banners/{$filename}");

        if ($request instanceof Request && $request->getHttpHost()) {
            $imageUrl = $request->getSchemeAndHttpHost() . "/api/v1/promo-banners/{$filename}";
        }

        return [
            'id' => $this->id,
            'title' => $this->title,
            'image_url' => $imageUrl,
            'target_url' => $this->target_url,
            'is_featured' => (bool) $this->is_featured,
            'sort_order' => $this->sort_order,
        ];
    }
}

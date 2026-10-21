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
        $path = "/storage/promo-banners/{$filename}";
        $imageUrl = url($path);

        if ($request instanceof Request && $request->getHttpHost()) {
            $imageUrl = $request->getSchemeAndHttpHost() . $path;
        }

        $popupImageUrl = null;
        if ($this->popup_image_path) {
            $popupFilename = basename($this->popup_image_path);
            $popupPath = "/storage/promo-banners/{$popupFilename}";
            $popupImageUrl = url($popupPath);
            if ($request instanceof Request && $request->getHttpHost()) {
                $popupImageUrl = $request->getSchemeAndHttpHost() . $popupPath;
            }
        }

        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'image_url' => $imageUrl,
            'slider_image_url' => $imageUrl,
            'popup_image_url' => $popupImageUrl,
            'target_url' => $this->target_url,
            'is_featured' => (bool) $this->is_featured,
            'sort_order' => $this->sort_order,
        ];
    }
}

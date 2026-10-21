<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PromoBannerRequest;
use App\Models\PromoBanner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

/**
 * Admin CRUD for the promo banner carousel shown on the mobile home screen.
 *
 * The listing itself is rendered by {@see PromoController}, which serves both
 * banners and promo vouchers on the unified "Promo & Voucher" page.
 */
class PromoBannerController extends Controller
{
    /**
     * Upload a new promo banner.
     */
    public function store(PromoBannerRequest $request): RedirectResponse
    {
        $path = $this->storeOptimizedImage($request->file('image'));
        $popupPath = $request->hasFile('popup_image')
            ? $this->storeOptimizedImage($request->file('popup_image'))
            : null;

        $isFeatured = $request->boolean('is_featured');
        if ($isFeatured) {
            PromoBanner::query()->where('is_featured', true)->update(['is_featured' => false]);
        }

        PromoBanner::query()->create([
            'title' => $request->string('title')->toString(),
            'description' => $request->string('description')->toString() ?: null,
            'image_path' => $path,
            'popup_image_path' => $popupPath,
            'target_url' => $request->string('target_url')->toString() ?: null,
            'is_active' => $request->boolean('is_active', true),
            'is_featured' => $isFeatured,
            'sort_order' => $request->integer('sort_order'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Banner promo berhasil diunggah.']);

        return to_route('admin.promos.index');
    }

    /**
     * Update a promo banner, replacing images only when new ones are sent.
     */
    public function update(PromoBannerRequest $request, PromoBanner $banner): RedirectResponse
    {
        $isFeatured = $request->boolean('is_featured');
        if ($isFeatured) {
            PromoBanner::query()->where('id', '!=', $banner->id)->where('is_featured', true)->update(['is_featured' => false]);
        }

        $attributes = [
            'title' => $request->string('title')->toString(),
            'description' => $request->string('description')->toString() ?: null,
            'target_url' => $request->string('target_url')->toString() ?: null,
            'is_active' => $request->boolean('is_active', true),
            'is_featured' => $isFeatured,
            'sort_order' => $request->integer('sort_order'),
        ];

        if ($request->hasFile('image')) {
            Storage::disk('public')->delete($banner->image_path);
            $attributes['image_path'] = $this->storeOptimizedImage($request->file('image'));
        }

        if ($request->hasFile('popup_image')) {
            if ($banner->popup_image_path) {
                Storage::disk('public')->delete($banner->popup_image_path);
            }
            $attributes['popup_image_path'] = $this->storeOptimizedImage($request->file('popup_image'));
        }

        $banner->update($attributes);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Banner promo berhasil diperbarui.']);

        return to_route('admin.promos.index');
    }

    /**
     * Delete a promo banner and its stored images.
     */
    public function destroy(PromoBanner $banner): RedirectResponse
    {
        Storage::disk('public')->delete($banner->image_path);
        if ($banner->popup_image_path) {
            Storage::disk('public')->delete($banner->popup_image_path);
        }

        $banner->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Banner promo berhasil dihapus.']);

        return to_route('admin.promos.index');
    }

    /**
     * Store and optionally resize/compress the uploaded banner for fast mobile loading.
     */
    protected function storeOptimizedImage(\Illuminate\Http\UploadedFile $file): string
    {
        $path = $file->store('promo-banners', 'public');
        $fullPath = Storage::disk('public')->path($path);

        if (extension_loaded('gd') && file_exists($fullPath)) {
            $info = @getimagesize($fullPath);
            if ($info) {
                [$width, $height, $type] = $info;
                $maxDim = 1200;

                $src = match ($type) {
                    IMAGETYPE_JPEG => @imagecreatefromjpeg($fullPath),
                    IMAGETYPE_PNG => @imagecreatefrompng($fullPath),
                    IMAGETYPE_WEBP => @imagecreatefromwebp($fullPath),
                    default => null,
                };

                if ($src) {
                    if ($width > $maxDim || $height > $maxDim) {
                        $ratio = min($maxDim / $width, $maxDim / $height);
                        $newWidth = (int) round($width * $ratio);
                        $newHeight = (int) round($height * $ratio);
                        $dst = imagecreatetruecolor($newWidth, $newHeight);
                        if ($type === IMAGETYPE_PNG) {
                            imagealphablending($dst, false);
                            imagesavealpha($dst, true);
                        }
                        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
                        imagedestroy($src);
                        $src = $dst;
                    }

                    match ($type) {
                        IMAGETYPE_JPEG => imagejpeg($src, $fullPath, 85),
                        IMAGETYPE_PNG => imagepng($src, $fullPath, 8),
                        IMAGETYPE_WEBP => imagewebp($src, $fullPath, 85),
                        default => null,
                    };

                    imagedestroy($src);
                }
            }
        }

        return $path;
    }
}

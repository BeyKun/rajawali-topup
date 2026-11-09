<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VoucherOcrLog;
use App\Services\VoucherOcrService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Throwable;

class VoucherOcrController extends Controller
{
    public function __construct(
        private readonly VoucherOcrService $ocrService,
    ) {}

    /**
     * Scan an uploaded voucher photo using the self-hosted OCR engine.
     */
    public function scanPhoto(Request $request): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'file', 'image', 'mimes:jpeg,png,jpg,webp', 'max:20480'],
        ], [
            'image.required' => 'Foto voucher wajib diunggah.',
            'image.image' => 'Berkas harus berupa gambar valid.',
            'image.mimes' => 'Format gambar harus JPEG, PNG, JPG, atau WEBP.',
            'image.max' => 'Ukuran gambar maksimal 20 MB.',
        ]);

        /** @var UploadedFile $file */
        $file = $request->file('image');
        $storedPath = $file->store('voucher-ocr-logs', 'public');
        $user = $request->user();
        $cityId = $user?->effectiveCityId() ?? ($user?->managedCityIds()[0] ?? null);

        try {
            $result = $this->ocrService->scanPhoto($file);

            VoucherOcrLog::query()->create([
                'user_id' => $user?->id,
                'city_id' => $cityId,
                'image_path' => $storedPath,
                'original_filename' => $file->getClientOriginalName(),
                'is_success' => true,
                'vouchers_count' => count($result['vouchers'] ?? []),
                'extracted_vouchers' => $result['vouchers'] ?? [],
                'raw_texts' => $result['raw_texts'] ?? [],
                'error_message' => null,
            ]);

            return response()->json($result);
        } catch (Throwable $e) {
            VoucherOcrLog::query()->create([
                'user_id' => $user?->id,
                'city_id' => $cityId,
                'image_path' => $storedPath,
                'original_filename' => $file->getClientOriginalName(),
                'is_success' => false,
                'vouchers_count' => 0,
                'extracted_vouchers' => [],
                'raw_texts' => [],
                'error_message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: 'Terjadi kesalahan saat memproses OCR pada foto voucher.',
                'vouchers' => [],
            ], 503);
        }
    }
}

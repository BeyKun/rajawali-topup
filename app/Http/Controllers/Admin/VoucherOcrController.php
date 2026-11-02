<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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

        try {
            $result = $this->ocrService->scanPhoto($file);

            return response()->json($result);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: 'Terjadi kesalahan saat memproses OCR pada foto voucher.',
                'vouchers' => [],
            ], 503);
        }
    }
}

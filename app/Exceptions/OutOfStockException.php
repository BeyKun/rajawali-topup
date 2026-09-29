<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Thrown when an order cannot be created because no voucher is available
 * for the requested product.
 */
class OutOfStockException extends RuntimeException
{
    /**
     * Render the exception as a 422 JSON response for API consumers.
     */
    public function render(Request $request): ?JsonResponse
    {
        if (! $request->is('api/*') && ! $request->expectsJson()) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
        ], 422);
    }
}

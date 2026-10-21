<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Thrown when a promo code cannot be applied to an order.
 *
 * Mirrors {@see OutOfStockException}: it renders the standard mobile API
 * envelope so the app shows the reason (expired, quota exhausted, minimum
 * spend not met, …) instead of a generic validation payload.
 */
class InvalidPromoCodeException extends RuntimeException
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
            'valid' => false,
            'message' => $this->getMessage(),
        ], 422);
    }
}

<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Base form request for the mobile API.
 *
 * Every API error must use the `{"success": false, "message": "..."}` envelope,
 * so validation failures are converted into that shape (with the first error
 * message surfaced) instead of the default Laravel `errors` bag payload.
 */
abstract class ApiFormRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Render a failed validation attempt using the mobile API envelope.
     */
    protected function failedValidation(Validator $validator): void
    {
        $message = $validator->errors()->first();

        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => $message,
        ], 422));
    }
}

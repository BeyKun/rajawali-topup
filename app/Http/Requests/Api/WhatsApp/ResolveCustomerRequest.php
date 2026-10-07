<?php

namespace App\Http\Requests\Api\WhatsApp;

use App\Http\Requests\Api\ApiFormRequest;
use Illuminate\Contracts\Validation\ValidationRule;

class ResolveCustomerRequest extends ApiFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'wa_id' => ['required', 'string', 'max:20'],
            'display_name' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Get custom validation messages in Bahasa Indonesia.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'wa_id.required' => 'Nomor WhatsApp wajib diisi.',
        ];
    }
}

<?php

namespace App\Http\Requests\Api;

use App\Models\Product;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class ValidatePromoRequest extends ApiFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50'],
            'product_id' => ['required', 'integer', Rule::exists(Product::class, 'id')],
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
            'code.required' => 'Kode promo wajib diisi.',
            'code.string' => 'Kode promo tidak valid.',
            'product_id.required' => 'Paket data wajib dipilih.',
            'product_id.exists' => 'Paket data tidak ditemukan.',
        ];
    }
}

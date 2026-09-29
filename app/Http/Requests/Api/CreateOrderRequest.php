<?php

namespace App\Http\Requests\Api;

use App\Models\Product;
use App\Rules\TelkomselMsisdn;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class CreateOrderRequest extends ApiFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'product_id' => [
                'required',
                'integer',
                Rule::exists(Product::class, 'id')->where('is_active', true),
            ],
            'msisdn' => ['required', 'string', new TelkomselMsisdn],
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
            'product_id.required' => 'Paket data wajib dipilih.',
            'product_id.integer' => 'Paket data tidak valid.',
            'product_id.exists' => 'Paket data tidak ditemukan atau sedang tidak tersedia.',
            'msisdn.required' => 'Nomor HP tujuan wajib diisi.',
            'msisdn.string' => 'Nomor HP tujuan tidak valid.',
        ];
    }
}

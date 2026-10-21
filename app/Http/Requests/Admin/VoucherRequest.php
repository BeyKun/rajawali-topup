<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VoucherRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'serial_number' => preg_replace('/\s+/', '', (string) $this->input('serial_number')),
            'hrn' => preg_replace('/\s+/', '', (string) $this->input('hrn')),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'serial_number' => ['required', 'string', 'digits:12', Rule::unique('vouchers', 'serial_number')],
            'hrn' => ['required', 'string', 'digits:17'],
            'sell_price' => ['required', 'numeric', 'min:0'],
            'margin_percentage' => ['nullable', 'numeric', 'min:0'],
            'city_ids' => ['nullable', 'array'],
            'city_ids.*' => ['integer', 'exists:cities,id'],
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
            'serial_number.required' => 'Serial number wajib diisi.',
            'serial_number.digits' => 'Serial number harus terdiri dari 12 digit angka.',
            'serial_number.unique' => 'Voucher dengan nomor seri ini sudah pernah ditambahkan ke inventori.',
            'hrn.required' => 'HRN wajib diisi.',
            'hrn.digits' => 'HRN harus terdiri dari 17 digit angka.',
            'sell_price.required' => 'Harga wajib diisi.',
            'sell_price.numeric' => 'Harga harus berupa angka.',
            'sell_price.min' => 'Harga tidak boleh negatif.',
            'margin_percentage.numeric' => 'Persentase harga jual harus berupa angka.',
            'margin_percentage.min' => 'Persentase harga jual tidak boleh negatif.',
            'city_ids.array' => 'Format pilihan wilayah tidak valid.',
            'city_ids.*.exists' => 'Wilayah yang dipilih tidak valid.',
        ];
    }
}

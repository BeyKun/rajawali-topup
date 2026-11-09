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
        $serial = preg_replace('/\D/', '', (string) $this->input('serial_number'));
        if (strlen($serial) > 12) {
            $serial = substr($serial, 0, 12);
        }

        $this->merge([
            'serial_number' => $serial,
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
        $isSuperAdmin = (bool) $this->user()?->isSuperAdmin();

        return [
            'serial_number' => ['required', 'string', 'digits:12', Rule::unique('vouchers', 'serial_number')],
            'hrn' => ['required', 'string', 'digits_between:14,20'],
            'sell_price' => ['required', 'numeric', 'gt:0'],
            'margin_percentage' => ['nullable', 'numeric', 'min:0'],
            'city_ids' => $isSuperAdmin ? ['required', 'array', 'min:1'] : ['nullable', 'array'],
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
            'hrn.digits' => 'HRN harus terdiri dari 14 hingga 20 digit angka.',
            'hrn.digits_between' => 'HRN harus terdiri dari 14 hingga 20 digit angka.',
            'sell_price.required' => 'Harga wajib diisi.',
            'sell_price.numeric' => 'Harga harus berupa angka.',
            'sell_price.gt' => 'Harga voucher tidak boleh 0 atau kurang.',
            'sell_price.min' => 'Harga voucher tidak boleh 0 atau kurang.',
            'margin_percentage.numeric' => 'Persentase harga jual harus berupa angka.',
            'margin_percentage.min' => 'Persentase harga jual tidak boleh negatif.',
            'city_ids.required' => 'Wilayah berlaku wajib dipilih minimal satu kabupaten/kota.',
            'city_ids.min' => 'Wilayah berlaku wajib dipilih minimal satu kabupaten/kota.',
            'city_ids.array' => 'Format pilihan wilayah tidak valid.',
            'city_ids.*.exists' => 'Wilayah yang dipilih tidak valid.',
        ];
    }
}

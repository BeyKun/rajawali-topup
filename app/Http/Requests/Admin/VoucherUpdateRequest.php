<?php

namespace App\Http\Requests\Admin;

use App\Enums\VoucherStatus;
use App\Models\Voucher;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VoucherUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Voucher|null $voucher */
        $voucher = $this->route('voucher');
        $voucherId = $voucher instanceof Voucher ? $voucher->id : $voucher;

        return [
            'serial_number' => [
                'required',
                'string',
                'digits:12',
                Rule::unique('vouchers', 'serial_number')->ignore($voucherId),
            ],
            'hrn' => ['nullable', 'string', 'digits:17'],
            'status' => ['required', 'string', Rule::enum(VoucherStatus::class)],
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
            'serial_number.unique' => 'Serial number ini sudah digunakan oleh voucher lain.',
            'hrn.digits' => 'HRN harus terdiri dari 17 digit angka.',
            'status.required' => 'Status voucher wajib dipilih.',
            'status.enum' => 'Status voucher tidak valid.',
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

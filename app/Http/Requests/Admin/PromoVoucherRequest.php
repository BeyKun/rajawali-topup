<?php

namespace App\Http\Requests\Admin;

use App\Enums\DiscountType;
use App\Models\PromoVoucher;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PromoVoucherRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalize the voucher code to its canonical uppercase form.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => PromoVoucher::normalizeCode((string) $this->input('code')),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var PromoVoucher|null $voucher */
        $voucher = $this->route('voucher');

        return [
            'code' => [
                'required',
                'string',
                'max:50',
                'regex:/^[A-Z0-9_-]+$/',
                Rule::unique('promo_vouchers', 'code')->ignore($voucher?->id),
            ],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'discount_type' => ['required', Rule::enum(DiscountType::class)],
            'discount_value' => [
                'required',
                'numeric',
                'min:0.01',
                Rule::when(
                    $this->input('discount_type') === DiscountType::Percentage->value,
                    ['max:100']
                ),
            ],
            'max_discount_amount' => ['nullable', 'numeric', 'min:0'],
            'min_spend' => ['nullable', 'numeric', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'is_active' => ['boolean'],
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
            'code.regex' => 'Kode promo hanya boleh berisi huruf, angka, strip, dan garis bawah.',
            'code.unique' => 'Kode promo ini sudah digunakan.',
            'title.required' => 'Judul promo wajib diisi.',
            'title.max' => 'Judul promo maksimal 150 karakter.',
            'discount_type.required' => 'Jenis diskon wajib dipilih.',
            'discount_value.required' => 'Besaran diskon wajib diisi.',
            'discount_value.numeric' => 'Besaran diskon harus berupa angka.',
            'discount_value.min' => 'Besaran diskon harus lebih besar dari 0.',
            'discount_value.max' => 'Besaran diskon persentase maksimal 100%.',
            'max_discount_amount.numeric' => 'Batas maksimal diskon harus berupa angka.',
            'min_spend.numeric' => 'Minimal belanja harus berupa angka.',
            'usage_limit.integer' => 'Kuota pemakaian harus berupa angka bulat.',
            'usage_limit.min' => 'Kuota pemakaian minimal 1.',
            'start_date.required' => 'Tanggal mulai wajib diisi.',
            'end_date.required' => 'Tanggal berakhir wajib diisi.',
            'end_date.after_or_equal' => 'Tanggal berakhir tidak boleh sebelum tanggal mulai.',
        ];
    }
}

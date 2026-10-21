<?php

namespace App\Http\Requests\Api;

use App\Rules\IndonesianPhoneNumber;
use Illuminate\Contracts\Validation\ValidationRule;

class PhoneLoginRequest extends ApiFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', new IndonesianPhoneNumber],
            'password' => ['required', 'string'],
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
            'phone.required' => 'Nomor HP wajib diisi.',
            'phone.string' => 'Nomor HP tidak valid.',
            'password.required' => 'Password wajib diisi.',
            'password.string' => 'Password tidak valid.',
        ];
    }
}

<?php

namespace App\Http\Requests\Admin;

use App\Models\City;
use App\Models\District;
use App\Models\Province;
use App\Models\User;
use App\Models\Village;
use App\Rules\IndonesianPhoneNumber;
use App\Support\Phone;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OutletUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalize the phone number and outlet ID before validation.
     */
    protected function prepareForValidation(): void
    {
        $merge = [
            'phone' => Phone::normalizeTo62((string) $this->input('phone')),
        ];

        if ($this->has('outlet_id')) {
            $merge['outlet_id'] = trim((string) $this->input('outlet_id'));
        }

        $this->merge($merge);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var User|int|string|null $user */
        $user = $this->route('user');
        $userId = $user instanceof User ? $user->id : $user;

        return [
            'outlet_id' => [
                'required',
                'string',
                'max:100',
                Rule::unique('outlet_profiles', 'outlet_id')->ignore($userId, 'user_id'),
            ],
            'name' => ['required', 'string', 'max:255'],
            'phone' => [
                'required',
                'string',
                new IndonesianPhoneNumber,
                Rule::unique('users', 'phone')->ignore($userId),
            ],
            'outlet_name' => ['required', 'string', 'max:150'],
            'whatsapp' => ['nullable', 'string', 'max:20'],
            'province_id' => ['required', 'integer', Rule::exists(Province::class, 'id')],
            'city_id' => ['required', 'integer', Rule::exists(City::class, 'id')],
            'district_id' => ['required', 'integer', Rule::exists(District::class, 'id')],
            'village_id' => ['required', 'integer', Rule::exists(Village::class, 'id')],
            'address' => ['required', 'string', 'max:500'],
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
            'outlet_id.required' => 'ID Outlet wajib diisi.',
            'outlet_id.max' => 'ID Outlet maksimal 100 karakter.',
            'outlet_id.unique' => 'ID Outlet ini sudah terdaftar pada outlet lain.',
            'name.required' => 'Nama pemilik wajib diisi.',
            'phone.required' => 'Nomor HP wajib diisi.',
            'phone.unique' => 'Nomor HP ini sudah terdaftar pada akun lain.',
            'outlet_name.required' => 'Nama outlet wajib diisi.',
            'outlet_name.max' => 'Nama outlet maksimal 150 karakter.',
            'whatsapp.max' => 'Nomor WhatsApp maksimal 20 karakter.',
            'province_id.required' => 'Provinsi wajib dipilih.',
            'province_id.exists' => 'Provinsi tidak ditemukan.',
            'city_id.required' => 'Kabupaten/Kota wajib dipilih.',
            'city_id.exists' => 'Kabupaten/Kota tidak ditemukan.',
            'district_id.required' => 'Kecamatan wajib dipilih.',
            'district_id.exists' => 'Kecamatan tidak ditemukan.',
            'village_id.required' => 'Kelurahan/Desa wajib dipilih.',
            'village_id.exists' => 'Kelurahan/Desa tidak ditemukan.',
            'address.required' => 'Alamat lengkap wajib diisi.',
            'address.max' => 'Alamat lengkap maksimal 500 karakter.',
        ];
    }
}

<?php

namespace App\Http\Requests\Api;

use App\Models\City;
use App\Models\District;
use App\Models\Province;
use App\Models\Village;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class UpdateOutletProfileRequest extends ApiFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'outlet_name' => ['required', 'string', 'max:150'],
            'whatsapp' => ['required', 'string', 'max:20'],
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
            'outlet_name.required' => 'Nama outlet wajib diisi.',
            'outlet_name.max' => 'Nama outlet maksimal 150 karakter.',
            'whatsapp.required' => 'Nomor WhatsApp wajib diisi.',
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

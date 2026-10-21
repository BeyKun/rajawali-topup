<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PromoBannerRequest extends FormRequest
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
     * `image` is required when creating a banner and optional when editing, so
     * an admin can toggle a banner's status without re-uploading artwork.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $isUpdate = $this->route('banner') !== null;

        return [
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'image' => [$isUpdate ? 'nullable' : 'required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'popup_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'target_url' => ['nullable', 'string', 'url', 'max:255'],
            'is_active' => ['boolean'],
            'is_featured' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
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
            'title.required' => 'Judul banner wajib diisi.',
            'title.max' => 'Judul banner maksimal 150 karakter.',
            'description.max' => 'Deskripsi banner maksimal 2000 karakter.',
            'image.required' => 'Gambar banner slider wajib diunggah.',
            'image.image' => 'Berkas banner slider harus berupa gambar.',
            'image.mimes' => 'Format gambar slider harus JPG, JPEG, PNG, atau WEBP.',
            'image.max' => 'Ukuran gambar slider maksimal 4 MB.',
            'popup_image.image' => 'Berkas banner popup harus berupa gambar.',
            'popup_image.mimes' => 'Format gambar popup harus JPG, JPEG, PNG, atau WEBP.',
            'popup_image.max' => 'Ukuran gambar popup maksimal 4 MB.',
            'target_url.url' => 'Tautan tujuan tidak valid.',
            'sort_order.integer' => 'Urutan tampil harus berupa angka.',
            'sort_order.min' => 'Urutan tampil tidak boleh negatif.',
        ];
    }
}

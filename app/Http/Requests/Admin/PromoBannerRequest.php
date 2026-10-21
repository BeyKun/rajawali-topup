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
            'image' => [$isUpdate ? 'nullable' : 'required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
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
            'image.required' => 'Gambar banner wajib diunggah.',
            'image.image' => 'Berkas banner harus berupa gambar.',
            'image.mimes' => 'Format gambar harus JPG, JPEG, PNG, atau WEBP.',
            'image.max' => 'Ukuran gambar maksimal 4 MB.',
            'target_url.url' => 'Tautan tujuan tidak valid.',
            'sort_order.integer' => 'Urutan tampil harus berupa angka.',
            'sort_order.min' => 'Urutan tampil tidak boleh negatif.',
        ];
    }
}

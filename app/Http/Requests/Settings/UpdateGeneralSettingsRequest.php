<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGeneralSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('access-settings');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'app_name' => ['required', 'string', 'max:100'],
            // SVG sengaja tidak diterima: bisa membawa script dan disajikan
            // langsung dari disk public.
            'app_logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
            'remove_logo' => ['nullable', 'boolean'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            // Format internasional tanpa "+", dipakai untuk tautan wa.me.
            'contact_whatsapp' => ['nullable', 'regex:/^[1-9][0-9]{7,15}$/'],
            'contact_address' => ['nullable', 'string', 'max:500'],
            'social_instagram' => ['nullable', 'url:https', 'max:255'],
            'social_facebook' => ['nullable', 'url:https', 'max:255'],
            'social_tiktok' => ['nullable', 'url:https', 'max:255'],
            'social_youtube' => ['nullable', 'url:https', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'contact_whatsapp.regex' => 'Nomor WhatsApp memakai format internasional tanpa "+", mis. 6281234567890.',
            'social_*.url' => 'Tautan sosial media harus diawali https://.',
        ];
    }
}

<?php

namespace App\Http\Requests;

use App\Support\Captcha;
use Illuminate\Foundation\Http\FormRequest;

class StoreContactMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) config('landing.contact_form');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]+$/'],
            'message' => ['required', 'string', 'min:10', 'max:2000'],
            // Honeypot — divalidasi di controller supaya bot tidak mendapat
            // pesan error yang membocorkan jebakannya.
            'website' => ['nullable'],
            ...Captcha::rules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return Captcha::messages();
    }

    /**
     * Error validasi dikembalikan ke section kontak, bukan ke atas halaman.
     */
    protected function getRedirectUrl(): string
    {
        return route('public.home').'#contact';
    }

    public function isSpam(): bool
    {
        return filled($this->input('website'));
    }
}

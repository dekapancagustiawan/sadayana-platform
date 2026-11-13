<?php

namespace App\Http\Requests\Settings;

use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMailSettingsRequest extends FormRequest
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
        $smtp = Rule::requiredIf(fn () => $this->input('mail_mailer') === 'smtp');

        return [
            'mail_mailer' => ['required', Rule::in(SettingsService::MAILERS)],
            'mail_host' => [$smtp, 'nullable', 'string', 'max:255'],
            'mail_port' => [$smtp, 'nullable', 'integer', 'between:1,65535'],
            'mail_scheme' => [$smtp, 'nullable', Rule::in(SettingsService::MAIL_SCHEMES)],
            'mail_username' => ['nullable', 'string', 'max:255'],
            // Kosong = pertahankan password lama.
            'mail_password' => ['nullable', 'string', 'max:255'],
            'mail_from_address' => ['required', 'email', 'max:255'],
            'mail_from_name' => ['required', 'string', 'max:100'],
        ];
    }
}

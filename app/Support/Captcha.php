<?php

namespace App\Support;

/**
 * Titik tunggal untuk memutuskan apakah reCAPTCHA v3 dipakai.
 *
 * Aturan validasi dan partial blade `partials.recaptcha` sama-sama membaca
 * dari sini, jadi form tidak mungkin menampilkan captcha tanpa memvalidasinya
 * (atau sebaliknya).
 */
class Captcha
{
    public const FIELD = 'g-recaptcha-response';

    public static function enabled(): bool
    {
        return (bool) config('captcha.enabled')
            && filled(config('captcha.sitekey'))
            && filled(config('captcha.secret'));
    }

    /**
     * Aturan validasi untuk digabung ke rules form. Kosong saat captcha mati.
     *
     * Saat aktif, field WAJIB ada — jangan pernah memasang aturan ini hanya
     * kalau request mengirim field-nya, karena bot tinggal tidak mengirimnya.
     *
     * @return array<string, list<string>>
     */
    public static function rules(): array
    {
        return self::enabled()
            ? [self::FIELD => ['required', 'captcha']]
            : [];
    }

    /**
     * Pesan validasi untuk field captcha. Tanpa ini Laravel menampilkan key
     * mentah `validation.captcha` (paket no-captcha tidak membawa terjemahan).
     *
     * @return array<string, string>
     */
    public static function messages(): array
    {
        $message = __('Captcha verification failed. Please try again.');

        return [
            self::FIELD.'.required' => $message,
            self::FIELD.'.captcha' => $message,
        ];
    }
}

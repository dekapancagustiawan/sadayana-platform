<?php

return [
    'secret' => env('NOCAPTCHA_SECRET'),
    'sitekey' => env('NOCAPTCHA_SITEKEY'),

    // reCAPTCHA v3 hanya aktif kalau diizinkan DAN kedua key terisi. Tanpa key
    // (mis. development lokal) form tetap jalan tanpa captcha. Baca lewat
    // App\Support\Captcha::enabled(), jangan dari config langsung.
    'enabled' => (bool) env('NOCAPTCHA_ENABLED', true),

    'options' => [
        'timeout' => 30,
    ],
];

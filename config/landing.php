<?php

/*
|--------------------------------------------------------------------------
| Landing Page
|--------------------------------------------------------------------------
|
| Struktur halaman publik. Teksnya ada di lang/{id,en}/landing.php, identitas
| (nama, logo, kontak, sosial media) dari layar General Settings.
|
| Menyesuaikan landing untuk klien baru biasanya cukup:
|   1. atur `sections` di bawah (hapus/urutkan ulang),
|   2. ubah teks di lang/{id,en}/landing.php,
|   3. ganti warna di resources/css/app.css (@theme --color-brand-*).
|
*/

return [

    // Urutan section yang tampil. Hapus baris untuk menyembunyikan section;
    // nama = partial di resources/views/landing/sections/{nama}.blade.php.
    'sections' => [
        'hero',
        'features',
        'about',
        'testimonials',
        'faq',
        'contact',
    ],

    // Gambar hero (path relatif ke public/, mis. 'images/hero.jpg').
    // null = latar gradasi tanpa gambar.
    'hero_image' => env('LANDING_HERO_IMAGE'),

    // Kartu fitur: key → ikon Phosphor (https://phosphoricons.com). Judul dan
    // deskripsinya di landing.features.items.{key}.
    'features' => [
        'fast' => 'ph-lightning',
        'secure' => 'ph-shield-check',
        'support' => 'ph-headset',
        'flexible' => 'ph-puzzle-piece',
        'insight' => 'ph-chart-line-up',
        'mobile' => 'ph-device-mobile',
    ],

    // Angka di section About. Label di landing.about.stats.{key}.
    'stats' => [
        'clients' => '120+',
        'projects' => '350+',
        'years' => '8',
    ],

    // Tampilkan form kontak di section Contact. Pesan dikirim ke "Email
    // kontak" di General Settings (atau MAIL_FROM_ADDRESS bila kosong).
    'contact_form' => true,

];

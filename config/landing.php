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
    // (Partial `testimonials` masih tersedia bila nanti ada testimoni asli.)
    'sections' => [
        'hero',
        'features',
        'workflow',
        'about',
        'roles',
        'faq',
        'contact',
    ],

    // Gambar hero (path relatif ke public/, mis. 'images/hero.jpg').
    // null = ilustrasi logo Sadayana di atas latar gradasi.
    'hero_image' => env('LANDING_HERO_IMAGE'),

    // Logo bawaan bila belum ada logo yang diunggah di General Settings.
    'default_logo' => 'images/sadayana-logo.png',
    'default_mark' => 'images/sadayana-mark.png',

    // Kartu fitur: key → [ikon Phosphor, warna kartu: brand|leaf|sun].
    // Judul dan deskripsi di landing.features.items.{key}.
    'features' => [
        'residents' => ['ph-house-line', 'brand'],
        'water' => ['ph-drop', 'brand'],
        'waste' => ['ph-recycle', 'leaf'],
        'contributions' => ['ph-hand-coins', 'sun'],
        'payments' => ['ph-receipt', 'brand'],
        'transparency' => ['ph-chart-bar', 'leaf'],
    ],

    // Langkah alur kerja (section workflow): key → ikon.
    // Teks di landing.workflow.steps.{key}.
    'workflow' => [
        'record' => 'ph-camera',
        'bill' => 'ph-file-text',
        'pay' => 'ph-upload-simple',
        'verify' => 'ph-seal-check',
    ],

    // Angka di section About. Label di landing.about.stats.{key}.
    'stats' => [
        'modules' => '8',
        'roles' => '6',
        'access' => '24/7',
    ],

    // Peran pengguna (section roles): key → ikon. Teks di landing.roles.items.{key}.
    'roles' => [
        'board' => 'ph-users-three',
        'treasurer' => 'ph-wallet',
        'officer' => 'ph-clipboard-text',
        'resident' => 'ph-house',
    ],

    // Tampilkan form kontak di section Contact. Pesan dikirim ke "Email
    // kontak" di General Settings (atau MAIL_FROM_ADDRESS bila kosong).
    'contact_form' => true,

];

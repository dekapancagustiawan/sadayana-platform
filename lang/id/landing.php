<?php

/**
 * Teks landing page (Bahasa Indonesia).
 *
 * `:app` diganti nama aplikasi dari General Settings. Struktur section dan
 * ikon diatur di config/landing.php.
 */

return [

    'meta' => [
        'title' => ':app — Solusi digital untuk bisnis Anda',
        'description' => ':app membantu bisnis Anda bekerja lebih cepat, rapi, dan terukur.',
    ],

    'nav' => [
        'features' => 'Fitur',
        'about' => 'Tentang',
        'testimonials' => 'Testimoni',
        'faq' => 'FAQ',
        'contact' => 'Kontak',
        'cta' => 'Hubungi Kami',
        'open_menu' => 'Buka menu',
    ],

    'hero' => [
        'eyebrow' => 'Selamat datang di :app',
        'title' => 'Bangun bisnis yang lebih rapi dan terukur',
        'subtitle' => 'Satu platform untuk mengelola operasional, tim, dan pelanggan Anda — tanpa ribet.',
        'cta_primary' => 'Mulai Sekarang',
        'cta_secondary' => 'Lihat Fitur',
    ],

    'features' => [
        'eyebrow' => 'Fitur',
        'title' => 'Semua yang Anda butuhkan, di satu tempat',
        'subtitle' => 'Dirancang untuk tim kecil hingga perusahaan yang sedang tumbuh.',
        'items' => [
            'fast' => ['title' => 'Cepat', 'desc' => 'Halaman ringan dan responsif di perangkat apa pun.'],
            'secure' => ['title' => 'Aman', 'desc' => 'Hak akses per peran dan jejak audit untuk setiap perubahan.'],
            'support' => ['title' => 'Dukungan', 'desc' => 'Tim kami siap membantu saat Anda membutuhkannya.'],
            'flexible' => ['title' => 'Fleksibel', 'desc' => 'Mudah disesuaikan dengan alur kerja bisnis Anda.'],
            'insight' => ['title' => 'Terukur', 'desc' => 'Laporan yang jelas untuk keputusan yang lebih baik.'],
            'mobile' => ['title' => 'Mobile Ready', 'desc' => 'Akses dari ponsel, tablet, maupun desktop.'],
        ],
    ],

    'about' => [
        'eyebrow' => 'Tentang Kami',
        'title' => 'Kami membantu bisnis tumbuh dengan teknologi yang tepat',
        'body' => ':app lahir dari pengalaman mendampingi berbagai bisnis merapikan proses kerjanya. Kami percaya teknologi yang baik adalah teknologi yang tidak terasa rumit.',
        'stats' => [
            'clients' => 'Klien',
            'projects' => 'Proyek',
            'years' => 'Tahun pengalaman',
        ],
    ],

    'testimonials' => [
        'eyebrow' => 'Testimoni',
        'title' => 'Apa kata mereka',
        'items' => [
            ['name' => 'Rina Kusuma', 'role' => 'Pemilik Usaha', 'quote' => 'Operasional kami jauh lebih tertata sejak memakai :app.'],
            ['name' => 'Budi Santoso', 'role' => 'Manajer Operasional', 'quote' => 'Timnya responsif dan paham kebutuhan bisnis kami.'],
            ['name' => 'Sari Wijaya', 'role' => 'Direktur', 'quote' => 'Laporannya jelas, keputusan jadi lebih cepat diambil.'],
        ],
    ],

    'faq' => [
        'eyebrow' => 'FAQ',
        'title' => 'Pertanyaan yang sering diajukan',
        'items' => [
            ['q' => 'Bagaimana cara memulai?', 'a' => 'Hubungi kami lewat form di bawah, tim kami akan menghubungi Anda dalam 1×24 jam kerja.'],
            ['q' => 'Apakah bisa disesuaikan dengan kebutuhan kami?', 'a' => 'Bisa. Kami akan mempelajari alur kerja Anda terlebih dahulu sebelum implementasi.'],
            ['q' => 'Apakah data kami aman?', 'a' => 'Ya. Akses dibatasi per peran dan setiap perubahan penting tercatat di audit log.'],
        ],
    ],

    'contact' => [
        'eyebrow' => 'Kontak',
        'title' => 'Ada pertanyaan? Mari bicara.',
        'subtitle' => 'Kirim pesan dan kami akan membalas secepatnya.',
        'email' => 'Email',
        'phone' => 'Telepon',
        'whatsapp' => 'WhatsApp',
        'address' => 'Alamat',
        'whatsapp_cta' => 'Chat via WhatsApp',
        'form' => [
            'name' => 'Nama',
            'email' => 'Email',
            'phone' => 'Telepon (opsional)',
            'message' => 'Pesan',
            'submit' => 'Kirim Pesan',
            'sent' => 'Terima kasih! Pesan Anda sudah kami terima.',
            'failed' => 'Maaf, pesan belum terkirim. Silakan coba lagi atau hubungi kami langsung.',
        ],
    ],

    'footer' => [
        'tagline' => 'Solusi digital untuk bisnis yang ingin tumbuh.',
        'copyright' => '© :year :app. Seluruh hak cipta dilindungi.',
    ],

    'switcher' => [
        'id' => 'ID',
        'en' => 'EN',
    ],

];

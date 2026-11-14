<?php

/**
 * Teks landing page (Bahasa Indonesia).
 *
 * `:app` diganti nama aplikasi dari General Settings. Struktur section dan
 * ikon diatur di config/landing.php.
 */

return [

    'meta' => [
        'title' => ':app — Platform Digital untuk Warga RT/RW',
        'description' => ':app membantu pengurus dan warga perumahan mengelola data warga, tagihan air, iuran sampah, iuran warga, pembayaran, dan kas lingkungan secara transparan.',
    ],

    'tagline' => 'Silih Asih, Silih Asah, Silih Asuh',

    'nav' => [
        'features' => 'Fitur',
        'workflow' => 'Cara Kerja',
        'about' => 'Tentang',
        'roles' => 'Untuk Siapa',
        'testimonials' => 'Testimoni',
        'faq' => 'FAQ',
        'contact' => 'Kontak',
        'cta' => 'Hubungi Pengurus',
        'open_menu' => 'Buka menu',
    ],

    'hero' => [
        'eyebrow' => 'Silih Asih, Silih Asah, Silih Asuh',
        'title' => 'Kelola lingkungan perumahan jadi lebih rapi, transparan, dan guyub',
        'subtitle' => ':app menyatukan data warga, pencatatan meter air, iuran sampah, iuran warga, pembayaran, dan kas lingkungan dalam satu aplikasi — bisa dibuka dari ponsel siapa saja.',
        'cta_primary' => 'Hubungi Pengurus',
        'cta_secondary' => 'Lihat Fitur',
        'highlights' => [
            'Tanpa instal aplikasi',
            'Bukti bayar tersimpan rapi',
            'Kas tercatat otomatis',
        ],
    ],

    'features' => [
        'eyebrow' => 'Fitur',
        'title' => 'Semua urusan warga, di satu tempat',
        'subtitle' => 'Tidak ada lagi catatan tercecer di spreadsheet, grup WhatsApp, dan tumpukan bukti transfer.',
        'items' => [
            'residents' => ['title' => 'Administrasi Warga', 'desc' => 'Data blok, rumah, dan penghuni beserta riwayat hunian — tetap utuh saat warga pindah atau pengurus berganti.'],
            'water' => ['title' => 'Meter & Tagihan Air', 'desc' => 'Petugas mencatat meter lengkap dengan foto. Pemakaian dihitung otomatis dan angka yang janggal ditandai untuk diperiksa.'],
            'waste' => ['title' => 'Iuran Sampah', 'desc' => 'Nominal tetap per rumah setiap bulan. Perubahan tarif hanya berlaku ke depan, tagihan lama tidak ikut berubah.'],
            'contributions' => ['title' => 'Iuran & Kas Warga', 'desc' => 'Iuran RT, kas warga, hingga iuran kegiatan bisa diatur sendiri oleh pengurus — rutin maupun insidental.'],
            'payments' => ['title' => 'Pembayaran & Verifikasi', 'desc' => 'Warga mengunggah bukti transfer, bendahara memverifikasi. Satu transfer bisa melunasi beberapa tagihan sekaligus.'],
            'transparency' => ['title' => 'Transparansi Keuangan', 'desc' => 'Pemasukan, pengeluaran, dan saldo kas tercatat dengan jejak audit. Setiap pengeluaran disetujui Ketua RT.'],
        ],
    ],

    'workflow' => [
        'eyebrow' => 'Cara Kerja',
        'title' => 'Dari meteran sampai buku kas, tanpa kerja dua kali',
        'subtitle' => 'Setiap langkah tercatat, sehingga siapa pun pengurusnya, riwayatnya bisa ditelusuri.',
        'steps' => [
            'record' => ['title' => 'Petugas mencatat', 'desc' => 'Angka meter air dan fotonya diinput langsung dari ponsel di lokasi.'],
            'bill' => ['title' => 'Tagihan terbit', 'desc' => 'Sistem menghitung pemakaian dan menerbitkan tagihan air, sampah, dan iuran tiap rumah.'],
            'pay' => ['title' => 'Warga membayar', 'desc' => 'Warga melihat tagihannya, transfer, lalu mengunggah bukti pembayaran.'],
            'verify' => ['title' => 'Bendahara memverifikasi', 'desc' => 'Setelah disetujui, tagihan lunas dan pemasukan otomatis masuk buku kas.'],
        ],
    ],

    'about' => [
        'eyebrow' => 'Tentang :app',
        'title' => 'Untuk sadayana — untuk semua warga',
        'body' => 'Dalam bahasa Sunda, "sadayana" berarti semuanya. :app dibuat agar urusan lingkungan menjadi milik bersama: pengurus bekerja lebih ringan, bendahara lebih tertib, dan setiap warga bisa melihat tagihan serta penggunaan kas secara terbuka.',
        'values' => [
            'asih' => ['title' => 'Silih Asih', 'desc' => 'Saling peduli — warga dan pengurus terhubung dalam satu wadah.'],
            'asah' => ['title' => 'Silih Asah', 'desc' => 'Saling mengingatkan — informasi tagihan dan pengumuman tersampaikan jelas.'],
            'asuh' => ['title' => 'Silih Asuh', 'desc' => 'Saling menjaga — data dan keuangan lingkungan terlindungi dan dapat dipertanggungjawabkan.'],
        ],
        'stats' => [
            'modules' => 'Modul terpadu',
            'roles' => 'Peran pengguna',
            'access' => 'Akses dari ponsel',
        ],
    ],

    'roles' => [
        'eyebrow' => 'Untuk Siapa',
        'title' => 'Setiap orang melihat apa yang menjadi bagiannya',
        'subtitle' => 'Hak akses mengikuti tanggung jawab — warga tidak bisa melihat data rumah lain.',
        'items' => [
            'board' => ['title' => 'Ketua RT & Pengurus', 'desc' => 'Pantau dashboard lingkungan, tunggakan, dan laporan; kelola pengumuman serta pengaduan; setujui pengeluaran kas.'],
            'treasurer' => ['title' => 'Bendahara', 'desc' => 'Atur tarif dan iuran, terbitkan tagihan, verifikasi pembayaran, dan catat pemasukan maupun pengeluaran.'],
            'officer' => ['title' => 'Petugas Lapangan', 'desc' => 'Catat meter air dan layanan sampah sesuai rumah yang ditugaskan, langsung dari ponsel.'],
            'resident' => ['title' => 'Warga', 'desc' => 'Lihat tagihan, pemakaian air beserta foto meternya, bayar dan unggah bukti, serta kirim pengaduan.'],
        ],
    ],

    'testimonials' => [
        'eyebrow' => 'Testimoni',
        'title' => 'Apa kata mereka',
        'items' => [],
    ],

    'faq' => [
        'eyebrow' => 'FAQ',
        'title' => 'Pertanyaan yang sering diajukan',
        'items' => [
            ['q' => 'Apakah warga perlu menginstal aplikasi?', 'a' => 'Tidak. :app dibuka lewat browser di ponsel, tablet, maupun komputer.'],
            ['q' => 'Bagaimana warga mendapatkan akun?', 'a' => 'Akun dibuatkan oleh pengurus. Warga masuk memakai email atau nomor HP, lalu wajib mengganti password sementara saat pertama kali masuk.'],
            ['q' => 'Apakah mengunggah bukti transfer langsung membuat tagihan lunas?', 'a' => 'Belum. Tagihan berstatus lunas setelah bendahara memverifikasi pembayaran, dan saat itu juga pemasukan tercatat di buku kas.'],
            ['q' => 'Bisakah membayar beberapa tagihan sekaligus?', 'a' => 'Bisa. Pilih beberapa tagihan, lakukan satu kali transfer sesuai totalnya, lalu unggah buktinya.'],
            ['q' => 'Kalau tarif air atau iuran berubah, apakah tagihan lama ikut berubah?', 'a' => 'Tidak. Tarif baru hanya berlaku untuk periode berikutnya; tagihan yang sudah terbit menyimpan nilai saat diterbitkan.'],
            ['q' => 'Apakah data warga aman?', 'a' => 'Akses dibatasi sesuai peran, foto meter dan bukti transfer disimpan privat, dan setiap perubahan penting tercatat di jejak audit.'],
        ],
    ],

    'contact' => [
        'eyebrow' => 'Kontak',
        'title' => 'Ada pertanyaan? Hubungi pengurus.',
        'subtitle' => 'Pertanyaan seputar tagihan, iuran, atau pendaftaran akun warga — kirim pesan dan pengurus akan membalas secepatnya.',
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
            'sent' => 'Terima kasih! Pesan Anda sudah diterima pengurus.',
            'failed' => 'Maaf, pesan belum terkirim. Silakan coba lagi atau hubungi pengurus langsung.',
        ],
    ],

    'footer' => [
        'tagline' => 'Platform digital untuk warga RT/RW — Silih Asih, Silih Asah, Silih Asuh.',
        'copyright' => '© :year :app. Seluruh hak cipta dilindungi.',
    ],

    'switcher' => [
        'id' => 'ID',
        'en' => 'EN',
    ],

];

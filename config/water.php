<?php

/*
|--------------------------------------------------------------------------
| Air (Fase 3)
|--------------------------------------------------------------------------
*/

return [

    // Pembacaan dianggap "melonjak" bila pemakaian > spike_factor × rata-rata
    // pemakaian 3 bulan terakhir DAN selisihnya ≥ spike_min_volume m³.
    // Pembacaan seperti itu menunggu pemeriksaan pengurus (FR-WAT-002, Q5).
    'spike_factor' => (float) env('WATER_SPIKE_FACTOR', 3),
    'spike_min_volume' => (float) env('WATER_SPIKE_MIN_VOLUME', 10),

    // Petugas mencatat meter di awal bulan untuk pemakaian bulan SEBELUMNYA
    // (tagihan air terbit awal bulan, S-3). Sampai tanggal ini, layar Catat
    // Meter default ke bulan lalu; setelahnya ke bulan berjalan.
    'previous_month_until_day' => (int) env('WATER_PREVIOUS_MONTH_UNTIL_DAY', 20),

    // Prefix nomor pelanggan: AIR-J-12.
    'customer_prefix' => 'AIR',

];

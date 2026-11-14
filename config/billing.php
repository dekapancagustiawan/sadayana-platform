<?php

/*
|--------------------------------------------------------------------------
| Tagihan (Fase 5) — S-3: air ditagih awal bulan, sampah & iuran akhir bulan
|--------------------------------------------------------------------------
|
| Periode tagihan = bulan pemakaian/kewajiban (mis. September 2026). Tagihan
| air September terbit tanggal `water_issue_day` Oktober; sampah & iuran
| September terbit di hari terakhir September.
|
*/

return [
    'water_issue_day' => (int) env('BILLING_WATER_ISSUE_DAY', 1),
    'due_days' => (int) env('BILLING_DUE_DAYS', 10),

    // Pengingat jatuh tempo dikirim N hari sebelum due_date (Fase 8).
    'reminder_days_before' => (int) env('BILLING_REMINDER_DAYS', 3),

    // S-7: tunggakan sebanyak ini (bulan berbeda) ditandai "putus sementara".
    'disconnect_after_months' => (int) env('BILLING_DISCONNECT_MONTHS', 3),
];

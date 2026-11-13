<?php

/*
|--------------------------------------------------------------------------
| Komunitas (perumahan)
|--------------------------------------------------------------------------
|
| Rilis awal berjalan dalam mode SATU komunitas per instalasi (Dok 07 Q1),
| tetapi tabel bisnis sudah memiliki `community_id` supaya kelak bisa
| multi-komunitas tanpa migrasi data. Komunitas default dibuat otomatis oleh
| CurrentCommunity bila belum ada.
|
*/

return [
    'default_code' => env('COMMUNITY_CODE', 'default'),

    'default_name' => env('COMMUNITY_NAME', 'Perumahan Sadayana'),

    'default_timezone' => env('COMMUNITY_TIMEZONE', 'Asia/Jakarta'),
];

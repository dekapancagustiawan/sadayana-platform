<?php

/**
 * Admin sidebar menu translations (Indonesian).
 *
 * The `jeroennoten/laravel-adminlte` package already ships a LangFilter
 * (see config/adminlte.php's 'filters' list, and
 * vendor/jeroennoten/laravel-adminlte/src/Menu/Filters/LangFilter.php) that
 * automatically looks up each menu item's 'header'/'text'/'label' in
 * lang/{locale}/menu.php at render time — keyed by the exact English string
 * used in config/adminlte.php's 'menu' array. No code changes needed beyond
 * this file: it's picked up automatically per-request based on the
 * visitor's session locale (see App\Http\Middleware\SetLocale), and isn't
 * affected by `php artisan config:cache` since it runs on every request,
 * not at config-load time.
 *
 * English admin sessions need no lang/en/menu.php — when a key has no
 * translation, LangFilter falls back to the original (English) string.
 */

return [

    // ── Section headers ──────────────────────────────────────────────
    'SETTINGS' => 'PENGATURAN',
    'COMMUNITY' => 'WARGA',

    // ── Menu items ────────────────────────────────────────────────────
    'Dashboard' => 'Dasbor',
    'My House' => 'Rumah Saya',
    'Houses' => 'Rumah',
    'Residents' => 'Penghuni',
    'Blocks' => 'Blok',
    'Officer Assignments' => 'Penugasan Petugas',
    'Users' => 'Pengguna',
    'Roles & Permissions' => 'Peran & Izin Akses',
    'Audit Logs' => 'Log Audit',
    'General Settings' => 'Pengaturan Umum',
    'Email Log' => 'Log Email',

];

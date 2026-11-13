<?php

/*
|--------------------------------------------------------------------------
| Upload file domain
|--------------------------------------------------------------------------
|
| Dipakai FileService dan App\Support\Uploads (aturan validasi FormRequest).
| SVG sengaja tidak diizinkan (bisa memuat script).
|
*/

return [
    'disk' => env('UPLOADS_DISK', 'private'),

    // Ukuran maksimum dalam kilobyte.
    'max_image_kb' => (int) env('UPLOADS_MAX_IMAGE_KB', 5120),
    'max_document_kb' => (int) env('UPLOADS_MAX_DOCUMENT_KB', 5120),

    'image_mimes' => ['jpg', 'jpeg', 'png', 'webp'],
    'document_mimes' => ['jpg', 'jpeg', 'png', 'webp', 'pdf'],
];

<?php

namespace App\Support;

/**
 * Aturan validasi upload untuk FormRequest. Selalu pakai ini, jangan menulis
 * daftar mimes sendiri, supaya batas ukuran/tipe seragam (FR-WAT-003, NFR-002).
 */
final class Uploads
{
    /**
     * @return list<string>
     */
    public static function imageRules(): array
    {
        return [
            'file',
            'mimes:'.implode(',', config('uploads.image_mimes')),
            'max:'.config('uploads.max_image_kb'),
        ];
    }

    /**
     * Gambar atau PDF (bukti transfer, lampiran transaksi).
     *
     * @return list<string>
     */
    public static function documentRules(): array
    {
        return [
            'file',
            'mimes:'.implode(',', config('uploads.document_mimes')),
            'max:'.config('uploads.max_document_kb'),
        ];
    }
}

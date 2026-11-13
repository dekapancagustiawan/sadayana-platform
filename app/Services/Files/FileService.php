<?php

namespace App\Services\Files;

use App\Models\StoredFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Menyimpan upload domain ke disk privat dan mencatat metadatanya.
 *
 * Validasi tipe/ukuran tetap di FormRequest (App\Support\Uploads); service ini
 * hanya menolak SVG/HTML sebagai lapisan kedua.
 *
 * Bila dipanggil di dalam DB::transaction lalu transaksinya gagal, record
 * `files` ikut di-rollback tetapi file fisik tertinggal di disk — itu
 * disengaja (lebih aman daripada kehilangan bukti); bersihkan lewat proses
 * retensi.
 */
class FileService
{
    /** @var list<string> */
    private const FORBIDDEN_MIME_TYPES = ['image/svg+xml', 'text/html', 'application/xhtml+xml'];

    public function store(UploadedFile $file, string $category): StoredFile
    {
        $mimeType = $file->getMimeType() ?? 'application/octet-stream';

        if (in_array($mimeType, self::FORBIDDEN_MIME_TYPES, true)) {
            throw new RuntimeException('Tipe file tidak diizinkan.');
        }

        $disk = config('uploads.disk');
        $extension = $file->guessExtension() ?: $file->getClientOriginalExtension();
        $directory = sprintf('%s/%s', Str::slug($category, '_'), now()->format('Y/m'));
        $filename = Str::uuid()->toString().($extension ? '.'.strtolower($extension) : '');

        $path = $file->storeAs($directory, $filename, $disk);

        if ($path === false) {
            throw new RuntimeException('Gagal menyimpan file. Silakan coba lagi.');
        }

        return StoredFile::create([
            'uploaded_by' => Auth::id(),
            'category' => $category,
            'storage_disk' => $disk,
            'storage_path' => $path,
            'original_name' => Str::limit($file->getClientOriginalName(), 250, ''),
            'mime_type' => $mimeType,
            'file_size' => $file->getSize(),
            'checksum' => hash_file('sha256', $file->getRealPath()) ?: null,
        ]);
    }
}

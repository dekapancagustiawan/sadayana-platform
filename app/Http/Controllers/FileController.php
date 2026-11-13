<?php

namespace App\Http\Controllers;

use App\Models\StoredFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileController extends Controller
{
    /**
     * Tampilkan file privat setelah lolos FilePolicy. Gambar dan PDF dibuka
     * inline; nosniff mencegah browser menebak tipe lain.
     */
    public function show(StoredFile $file): StreamedResponse
    {
        Gate::authorize('view', $file);

        $disk = Storage::disk($file->storage_disk);

        abort_unless($disk->exists($file->storage_path), 404);

        return $disk->response($file->storage_path, $file->original_name, [
            'Content-Type' => $file->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=0, no-store',
        ], 'inline');
    }
}

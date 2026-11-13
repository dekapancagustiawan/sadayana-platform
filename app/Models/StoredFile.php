<?php

namespace App\Models;

use App\Traits\BelongsToCommunity;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Metadata file upload (tabel `files`). Dinamai StoredFile supaya tidak
 * bentrok dengan facade `File`.
 *
 * Model domain menyimpan `*_file_id` yang menunjuk ke sini; isi file berada di
 * disk privat dan hanya bisa diambil lewat route `files.show`.
 */
class StoredFile extends Model
{
    use BelongsToCommunity, HasUuids;

    protected $table = 'files';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'uploaded_by',
        'category',
        'storage_disk',
        'storage_path',
        'original_name',
        'mime_type',
        'file_size',
        'checksum',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'storage_disk',
        'storage_path',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'uploaded_by' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    public function url(): string
    {
        return route('files.show', $this);
    }
}

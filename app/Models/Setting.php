<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Penyimpanan key-value untuk pengaturan aplikasi.
 *
 * Seluruh baris dibaca sekaligus lalu di-cache, jadi `get()` berulang dalam
 * satu request (atau di AppServiceProvider::boot) tidak memicu query per key.
 * Cache dibuang setiap kali `set()` dipanggil.
 */
class Setting extends Model
{
    private const CACHE_KEY = 'settings.all';

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'key',
        'value',
    ];

    /**
     * Get a setting value by key with fallback to default.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $value = static::allCached()[$key] ?? null;

        return $value === null || $value === '' ? $default : $value;
    }

    /**
     * Set/Update a setting value by key. `null` menghapus nilainya.
     */
    public static function set(string $key, mixed $value): static
    {
        $setting = static::updateOrCreate(
            ['key' => $key],
            ['value' => match (true) {
                $value === null => null,
                is_bool($value) => $value ? '1' : '0',
                default => (string) $value,
            }]
        );

        static::flushCache();

        return $setting;
    }

    /**
     * Semua setting sebagai array key => value.
     *
     * Aman dipanggil sebelum migrasi jalan atau saat database mati: kegagalan
     * apa pun (tabel belum ada, cache store tidak terjangkau) berujung array
     * kosong, sehingga pemanggil jatuh ke nilai default.
     *
     * @return array<string, string|null>
     */
    public static function allCached(): array
    {
        try {
            return Cache::rememberForever(
                self::CACHE_KEY,
                fn () => static::query()->pluck('value', 'key')->all()
            );
        } catch (\Throwable) {
            return [];
        }
    }

    public static function flushCache(): void
    {
        try {
            Cache::forget(self::CACHE_KEY);
        } catch (\Throwable) {
            // Cache store tidak terjangkau — tidak ada yang perlu dibuang.
        }
    }
}

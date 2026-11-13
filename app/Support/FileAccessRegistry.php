<?php

namespace App\Support;

use App\Models\StoredFile;
use App\Models\User;
use Closure;

/**
 * Aturan akses file per kategori.
 *
 * Setiap domain yang menyimpan file mendaftarkan aturannya di service
 * provider, mis.:
 *
 *     $registry->register('meter_photo', fn (User $user, StoredFile $file) => ...);
 *
 * FilePolicy mengizinkan akses bila user adalah pengunggah ATAU salah satu
 * aturan kategori file itu mengembalikan true. Kategori tanpa aturan hanya
 * bisa dibuka pengunggahnya (dan Admin lewat Gate::before).
 */
class FileAccessRegistry
{
    /** @var array<string, list<Closure(User, StoredFile): bool>> */
    private array $rules = [];

    /**
     * @param  Closure(User, StoredFile): bool  $rule
     */
    public function register(string $category, Closure $rule): void
    {
        $this->rules[$category][] = $rule;
    }

    public function allows(User $user, StoredFile $file): bool
    {
        foreach ($this->rules[$file->category] ?? [] as $rule) {
            if ($rule($user, $file) === true) {
                return true;
            }
        }

        return false;
    }
}

<?php

namespace App\Support\Database;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Constraint yang tidak tersedia di schema builder Laravel.
 *
 * Target produksi PostgreSQL; test berjalan di SQLite. Karena itu:
 *  - CHECK constraint hanya dipasang di PostgreSQL (SQLite tidak bisa
 *    menambah CHECK ke tabel yang sudah ada). Aturan yang sama WAJIB juga
 *    divalidasi di service/FormRequest — constraint ini jaring pengaman.
 *  - Partial unique index berjalan di PostgreSQL dan SQLite (sintaks sama),
 *    sehingga aturan keunikan bersyarat ikut teruji. MySQL tidak didukung.
 *
 * Panggil dari migration SETELAH `Schema::create(...)`.
 */
final class Constraints
{
    public static function check(string $table, string $name, string $expression): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(sprintf('ALTER TABLE %s ADD CONSTRAINT %s CHECK (%s)', $table, $name, $expression));
    }

    public static function dropCheck(string $table, string $name): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(sprintf('ALTER TABLE %s DROP CONSTRAINT IF EXISTS %s', $table, $name));
    }

    /**
     * @param  list<string>  $columns
     */
    public static function partialUnique(string $table, string $name, array $columns, string $where): void
    {
        $driver = DB::getDriverName();

        if (! in_array($driver, ['pgsql', 'sqlite'], true)) {
            throw new RuntimeException("Partial unique index tidak didukung driver [{$driver}].");
        }

        DB::statement(sprintf(
            'CREATE UNIQUE INDEX %s ON %s (%s) WHERE %s',
            $name,
            $table,
            implode(', ', $columns),
            $where,
        ));
    }

    public static function dropIndex(string $name): void
    {
        DB::statement(sprintf('DROP INDEX IF EXISTS %s', $name));
    }
}

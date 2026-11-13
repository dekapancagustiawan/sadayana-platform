<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * URUTAN PENTING — jangan diacak:
     *
     * 1. PermissionSeeder lebih dulu, supaya RoleSeeder bisa menempelkan
     *    seluruh permission ke Admin.
     * 2. RoleSeeder berikutnya: role sistem harus ada sebelum seeder
     *    permission lain menempelkan izin ke role.
     * 3. Seeder *PermissionsSeeder setelahnya — masing-masing membuat
     *    permission miliknya lalu menempelkannya ke role terkait.
     */
    public function run(): void
    {
        $this->call([
            CommunitySeeder::class,
            PermissionSeeder::class,
            RoleSeeder::class,
            MenuAccessPermissionsSeeder::class,
            UserSeeder::class,
        ]);
    }
}

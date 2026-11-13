<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            // Dashboard
            'dashboard.view',

            // User Management
            'users.view',
            'users.create',
            'users.edit',
            'users.delete',

            // Role Management
            'roles.view',
            'roles.create',
            'roles.edit',
            'roles.delete',

            // Permission Management
            // `routes/web.php` sudah memagari /permissions dengan
            // `can:permissions.view` padahal izinnya tidak pernah dibuat, jadi
            // halaman itu selama ini hanya terbuka untuk Admin lewat
            // `Gate::before`. Dibuat di sini supaya bisa diberikan ke role lain
            // dari layar Role Management; tidak ditempelkan ke role mana pun
            // selain Admin agar perilakunya tidak berubah.
            'permissions.view',
            'permissions.create',
            'permissions.edit',
            'permissions.delete',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
    }
}

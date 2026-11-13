<?php

namespace Database\Seeders;

use App\Enums\RoleType;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Membuat role sistem dan izin dasarnya.
 *
 * Seluruh pemberian izin di sini memakai `givePermissionTo` (aditif), BUKAN
 * `syncPermissions` (destruktif). Alasannya: sejak daftar menu dikendalikan
 * permission di database, role boleh dikustomisasi lewat layar Role
 * Management — dan seeder yang memakai sync akan menghapus kustomisasi itu
 * setiap kali dijalankan ulang. Konsekuensinya seeder ini tidak lagi bisa
 * dipakai untuk "reset ke kondisi pabrik"; pencabutan izin dilakukan lewat UI.
 *
 * Izin menu (`access-*`) ditempelkan MenuAccessPermissionsSeeder, bukan di sini
 * — lihat catatan urutan di DatabaseSeeder.
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Admin
        $admin = Role::firstOrCreate(['name' => RoleType::Admin->value, 'guard_name' => 'web']);
        // Admin mendapat seluruh permission yang sudah ada saat seeder ini
        // jalan. Permission yang dibuat seeder-seeder setelahnya ditempelkan
        // ke Admin oleh masing-masing seeder itu (lihat
        // MenuAccessPermissionsSeeder). Fungsinya kosmetik: Admin toh sudah
        // lolos semua gate lewat `Gate::before` di AppServiceProvider — daftar
        // eksplisit ini supaya layar Role Management terbaca utuh.
        $adminPermissions = Permission::all();
        if ($adminPermissions->isNotEmpty()) {
            $admin->givePermissionTo($adminPermissions);
        }

        // 2. User — role default untuk akun non-admin.
        $user = Role::firstOrCreate(['name' => RoleType::User->value, 'guard_name' => 'web']);
        $this->grant($user, [
            'dashboard.view',
        ]);
    }

    /**
     * Berikan izin yang memang sudah ada di database, abaikan yang belum
     * dibuat seeder lain.
     *
     * @param  list<string>  $permissionNames
     */
    private function grant(Role $role, array $permissionNames): void
    {
        $permissions = Permission::whereIn('name', $permissionNames)->get();

        if ($permissions->isNotEmpty()) {
            $role->givePermissionTo($permissions);
        }
    }
}

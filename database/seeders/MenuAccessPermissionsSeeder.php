<?php

namespace Database\Seeders;

use App\Enums\RoleType;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Menjadikan seluruh gate menu `access-*` sebagai permission di database.
 *
 * Spatie memasang `Gate::before` yang me-resolve nama ability apa pun ke tabel
 * `permissions` (lihat PermissionRegistrar::registerPermissions), termasuk nama
 * bertanda hubung seperti `access-dashboard`. Jadi begitu permission ini ada dan
 * ditempelkan ke role, `Gate::allows('access-dashboard')`, `@can('access-dashboard')`, dan
 * middleware `can:access-dashboard` berperilaku sama persis seperti closure
 * `Gate::define` di AppServiceProvider — bedanya sumbernya database, sehingga
 * role baru cukup dibuat lewat layar Role Management tanpa menyentuh kode.
 *
 * Matriks di bawah dikunci oleh tests/Feature/RoleMenuAccessTest.php. Seeder ini
 * sengaja dibuat aditif (`givePermissionTo`, bukan `syncPermissions`) supaya
 * boleh dijalankan ulang kapan pun tanpa menghapus kustomisasi yang dibuat
 * lewat UI.
 */
class MenuAccessPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Role di-load sekali lalu dipetakan per nama supaya tidak ada query
        // berulang untuk tiap pasangan permission × role.
        $roles = Role::whereIn('name', array_column(RoleType::cases(), 'value'))
            ->get()
            ->keyBy('name');

        foreach ($this->menuAccessMatrix() as $permissionName => $roleNames) {
            $permission = Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]);

            foreach ($roleNames as $roleName) {
                $role = $roles->get($roleName);

                if ($role) {
                    $role->givePermissionTo($permission);
                }
            }
        }
    }

    /**
     * Gate menu → role yang berhak.
     *
     * Admin sebetulnya sudah lolos lewat `Gate::before`, tapi tetap didaftarkan
     * eksplisit supaya daftar izin per role terbaca utuh di layar Role
     * Management.
     *
     * @return array<string, list<string>>
     */
    private function menuAccessMatrix(): array
    {
        $admin = RoleType::Admin->value;
        $user = RoleType::User->value;

        return [
            // ── Dashboard ──────────────────────────────────────────────
            'access-dashboard' => [$admin, $user],
            'access-notifications' => [$admin],

            // ── Warga (Fase 2). Role komunitas mendapat izin ini dari
            // CommunityRoleSeeder; di sini hanya Admin. ────────────────
            'access-houses' => [$admin],
            'access-officer-assignments' => [$admin],
            'access-my-house' => [],

            // ── Settings ───────────────────────────────────────────────
            'access-user-management' => [$admin],
            'access-audit-logs' => [$admin],
            'access-settings' => [$admin],
        ];
    }
}

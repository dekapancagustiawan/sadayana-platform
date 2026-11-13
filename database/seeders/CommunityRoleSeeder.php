<?php

namespace Database\Seeders;

use App\Enums\CommunityRole;
use App\Enums\RoleType;
use App\Support\Authorization;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Role operasional SRS §2 dan matriks permission domain (Dok 07 §7).
 *
 * SATU tempat untuk izin domain: setiap fase menambahkan permission barunya
 * ke `matrix()` di bawah. Admin mendapat semua izin di matriks KECUALI yang
 * dipisahkan (Authorization::SEGREGATED_ABILITIES).
 *
 * Aditif (`givePermissionTo`) seperti seeder lain — aman dijalankan ulang dan
 * tidak menghapus kustomisasi dari layar Role Management.
 */
class CommunityRoleSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $roles = collect(CommunityRole::cases())->mapWithKeys(fn (CommunityRole $role) => [
            $role->value => Role::firstOrCreate(['name' => $role->value, 'guard_name' => 'web']),
        ]);
        $admin = Role::firstOrCreate(['name' => RoleType::Admin->value, 'guard_name' => 'web']);

        foreach ($this->matrix() as $permissionName => $roleNames) {
            $permission = Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']);

            foreach ($roleNames as $roleName) {
                $roles->get($roleName)?->givePermissionTo($permission);
            }

            if (! Authorization::isSegregated($permissionName)) {
                $admin->givePermissionTo($permission);
            }
        }
    }

    /**
     * Permission → role komunitas yang berhak.
     *
     * @return array<string, list<string>>
     */
    public static function matrix(): array
    {
        $all = array_column(CommunityRole::cases(), 'value');

        return [
            // ── Fase 1: dasar ──────────────────────────────────────────
            'access-dashboard' => $all,
            'dashboard.view' => $all,

            // Fase berikutnya menambahkan blok izinnya di sini, mis.:
            // 'houses.view' => [CommunityRole::KetuaRt->value, CommunityRole::Bendahara->value],
        ];
    }
}

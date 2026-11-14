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
        $ketua = CommunityRole::KetuaRt->value;
        $bendahara = CommunityRole::Bendahara->value;
        $warga = CommunityRole::Warga->value;
        $petugasAir = CommunityRole::PetugasLapangan->value;

        // Matriks kosong ([]) = hanya Admin (Super Admin) — kelola master data
        // rumah & penghuni adalah wewenang ROLE-001 (SRS §2). Pengurus bisa
        // diberi izin tambahan dari layar Role Management.
        return [
            // ── Fase 1: dasar ──────────────────────────────────────────
            'access-dashboard' => $all,
            'dashboard.view' => $all,

            // ── Fase 2: rumah & penghuni (FR-RES-*) ────────────────────
            'access-houses' => [$ketua, $bendahara],
            'houses.view' => [$ketua, $bendahara],
            'houses.manage' => [],
            'residents.view' => [$ketua, $bendahara],
            'residents.manage' => [],
            'residents.view-identity' => [],
            'residents.accounts' => [],
            'residents.import' => [],
            'access-officer-assignments' => [$ketua],
            'officer-assignments.manage' => [$ketua],
            'access-my-house' => [$warga],
            'my-house.view' => [$warga],

            // ── Fase 3: air (FR-WAT-*) ─────────────────────────────────
            'access-water' => [$ketua, $bendahara],
            'meter-readings.view' => [$ketua, $bendahara],
            'meter-readings.validate' => [$ketua, $bendahara],
            'meter-readings.correct' => [$petugasAir, $ketua, $bendahara],
            'access-meter-recording' => [$petugasAir],
            'meter-readings.record' => [$petugasAir],
            'meter-readings.record-any' => [], // catat rumah mana pun, tanpa penugasan
            'water-customers.manage' => [$bendahara],
            'water-tariffs.manage' => [$bendahara],
        ];
    }
}

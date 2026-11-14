<?php

namespace App\Services\UserManagement;

use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;

/**
 * Menyusun daftar permission agar terbaca manusia di layar Role Management.
 *
 * Sejak menu sidebar dikendalikan permission `access-*` (lihat
 * MenuAccessPermissionsSeeder), layar ini menjadi satu-satunya tempat admin
 * mengatur menu apa yang dilihat sebuah role. Tanpa pengelompokan dan label
 * yang jelas, 21 nama `access-*` akan tercampur begitu saja dengan izin
 * fungsional dan admin non-teknis tidak tahu mana yang harus dicentang.
 */
class PermissionPresenter
{
    /**
     * Prefix penanda izin menu.
     */
    private const MENU_PREFIX = 'access-';

    /**
     * Kunci grup untuk izin menu. Dipakai juga sebagai bagian nama class CSS
     * di blade, jadi harus aman (tanpa spasi).
     */
    private const MENU_GROUP_KEY = 'menu-access';

    /**
     * Label tiap izin menu, disamakan dengan teks menu di sidebar
     * (config/adminlte.php) supaya admin bisa mencocokkan langsung dengan apa
     * yang dilihatnya di layar.
     *
     * @var array<string, string>
     */
    private const MENU_LABELS = [
        'access-dashboard' => 'Dashboard',
        'access-notifications' => 'Notifikasi (lonceng navbar)',
        'access-houses' => 'Blok, Rumah & Penghuni',
        'access-officer-assignments' => 'Penugasan Petugas',
        'access-my-house' => 'Rumah Saya (portal warga)',
        'access-water' => 'Air: Pembacaan Meter, Pelanggan & Tarif',
        'access-meter-recording' => 'Catat Meter (petugas)',
        'access-user-management' => 'Users, Roles & Permissions',
        'access-audit-logs' => 'Audit Logs',
        'access-settings' => 'Settings & Email Log',
    ];

    /**
     * Kelompokkan permission untuk ditampilkan: izin menu lebih dulu, lalu
     * modul fungsional urut abjad.
     *
     * @param  Collection<int, Permission>  $permissions
     * @return list<array{key: string, heading: string, permissions: Collection<int, Permission>}>
     */
    public function groups(Collection $permissions): array
    {
        $grouped = $permissions->groupBy(fn ($permission) => $this->groupKey($permission->name));

        $menu = $grouped->pull(self::MENU_GROUP_KEY);

        $groups = [];

        if ($menu !== null) {
            $groups[] = [
                'key' => self::MENU_GROUP_KEY,
                'heading' => 'Menu Access — menu apa yang dilihat role ini',
                'permissions' => $menu->sortBy(fn ($permission) => array_search(
                    $permission->name,
                    array_keys(self::MENU_LABELS)
                ))->values(),
            ];
        }

        foreach ($grouped->sortKeys() as $key => $perms) {
            $groups[] = [
                'key' => $key,
                'heading' => ucfirst(str_replace('_', ' ', $key)).' Module',
                'permissions' => $perms->sortBy('name')->values(),
            ];
        }

        return $groups;
    }

    /**
     * Label satu permission untuk ditampilkan di samping checkbox.
     */
    public function label(string $permissionName): string
    {
        return self::MENU_LABELS[$permissionName] ?? $permissionName;
    }

    /**
     * Tampilkan nama teknisnya sebagai keterangan tambahan, tapi hanya kalau
     * label-nya memang berbeda — supaya izin fungsional (`users.view`) tidak
     * tampil dua kali.
     */
    public function technicalName(string $permissionName): ?string
    {
        return $this->label($permissionName) === $permissionName ? null : $permissionName;
    }

    /**
     * Kunci grup sebuah permission. Nama bertitik dikelompokkan per modul
     * (`users.view` → `users`), nama `access-*` masuk grup menu.
     */
    private function groupKey(string $permissionName): string
    {
        if (str_starts_with($permissionName, self::MENU_PREFIX)) {
            return self::MENU_GROUP_KEY;
        }

        $parts = explode('.', $permissionName);

        return count($parts) > 1 ? $parts[0] : 'general';
    }
}

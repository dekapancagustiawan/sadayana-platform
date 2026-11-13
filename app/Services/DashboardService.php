<?php

namespace App\Services;

use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

class DashboardService
{
    public function __construct(
        protected DashboardNotificationService $notifications,
    ) {}

    /**
     * Kartu ringkasan untuk dashboard, disaring sesuai izin yang melihat.
     *
     * Kartu yang tidak boleh dilihat tidak dihitung sama sekali (bukan hanya
     * disembunyikan di blade), supaya query-nya tidak jalan percuma.
     *
     * @return list<array{label: string, value: int, icon: string, color: string, url: ?string}>
     */
    public function stats(User $viewer): array
    {
        $cards = [];

        if ($viewer->can('access-user-management')) {
            $cards[] = [
                'label' => 'Total User',
                'value' => User::count(),
                'icon' => 'fas fa-users',
                'color' => 'info',
                'url' => route('users.index'),
            ];
            $cards[] = [
                'label' => 'User Aktif',
                'value' => User::where('status', UserStatus::Active)->count(),
                'icon' => 'fas fa-user-check',
                'color' => 'success',
                'url' => route('users.index', ['status' => UserStatus::Active->value]),
            ];
            $cards[] = [
                'label' => 'Role',
                'value' => Role::count(),
                'icon' => 'fas fa-shield-alt',
                'color' => 'primary',
                'url' => route('roles.index'),
            ];
        }

        if ($viewer->can('access-notifications')) {
            $cards[] = [
                'label' => 'Notifikasi Belum Dibaca',
                'value' => $this->notifications->getUnreadCount(),
                'icon' => 'fas fa-bell',
                'color' => 'warning',
                'url' => null,
            ];
        }

        return $cards;
    }

    /**
     * Aktivitas terbaru dari audit log, atau koleksi kosong kalau yang melihat
     * tidak berhak membuka Audit Logs.
     *
     * @return Collection<int, AuditLog>
     */
    public function recentActivity(User $viewer, int $limit = 10): Collection
    {
        if (! $viewer->can('access-audit-logs')) {
            return collect();
        }

        return AuditLog::with('user')->latest('created_at')->limit($limit)->get();
    }
}

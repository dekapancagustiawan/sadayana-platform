<?php

namespace App\Services\Communication;

use App\Enums\Resident\HouseResidentStatus;
use App\Enums\UserStatus;
use App\Models\House;
use App\Models\User;
use App\Notifications\UserNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Kirim notifikasi per user (FR-NOT-001). Kegagalan notifikasi tidak boleh
 * menggagalkan proses bisnis — dipanggil setelah data tersimpan.
 */
class NotificationService
{
    /**
     * Akun aktif yang terhubung ke penghuni aktif rumah ini.
     *
     * @return Collection<int, User>
     */
    public function houseUsers(House $house): Collection
    {
        return User::query()
            ->where('status', UserStatus::Active)
            ->whereHas('residents.activeOccupancies', fn ($q) => $q
                ->where('house_id', $house->getKey())
                ->where('status', HouseResidentStatus::Active))
            ->get();
    }

    public function notifyHouse(House $house, string $title, string $message, ?string $url = null, string $icon = 'fas fa-bell', string $kind = 'general'): void
    {
        $this->notifyUsers($this->houseUsers($house), $title, $message, $url, $icon, $kind);
    }

    /**
     * @param  iterable<User>  $users
     */
    public function notifyUsers(iterable $users, string $title, string $message, ?string $url = null, string $icon = 'fas fa-bell', string $kind = 'general'): void
    {
        $users = collect($users)->filter()->unique('id');

        if ($users->isEmpty()) {
            return;
        }

        rescue(fn () => Notification::send($users, new UserNotification($title, $message, $url, $icon, $kind)), report: true);
    }

    /**
     * Semua user aktif yang memegang izin tertentu (mis. bendahara untuk
     * pembayaran baru).
     */
    public function notifyPermission(string $permission, string $title, string $message, ?string $url = null, string $icon = 'fas fa-bell', string $kind = 'general'): void
    {
        $users = User::where('status', UserStatus::Active)->get()->filter(fn (User $u) => $u->can($permission));

        $this->notifyUsers($users, $title, $message, $url, $icon, $kind);
    }
}

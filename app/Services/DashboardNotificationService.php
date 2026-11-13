<?php

namespace App\Services;

use App\Enums\NotificationType;
use App\Models\DashboardNotification;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class DashboardNotificationService
{
    /**
     * Push a notification to the admin navbar bell.
     *
     * Panggil dari service/listener modul mana pun, mis.:
     *   $notifications->notify(NotificationType::INFO, 'Judul', 'Pesan', route('x.show', $x), $x);
     */
    public function notify(
        NotificationType $type,
        string $title,
        string $message,
        ?string $url = null,
        ?Model $reference = null,
    ): DashboardNotification {
        return DashboardNotification::create([
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'url' => $url,
            'reference_type' => $reference?->getMorphClass(),
            'reference_id' => $reference?->getKey(),
            'is_read' => false,
        ]);
    }

    /**
     * Get latest notifications (read + unread).
     */
    public function getLatest(int $limit = 10)
    {
        return DashboardNotification::latestFirst()->limit($limit)->get();
    }

    /**
     * Get unread notifications (dibatasi supaya tidak memuat seluruh tabel).
     */
    public function getUnread(int $limit = 50)
    {
        return DashboardNotification::unread()->latestFirst()->limit($limit)->get();
    }

    /**
     * Get unread count.
     */
    public function getUnreadCount(): int
    {
        return DashboardNotification::unread()->count();
    }

    /**
     * Mark a single notification as read.
     */
    public function markAsRead(string $id): bool
    {
        $notification = DashboardNotification::findOrFail($id);

        return $notification->update([
            'is_read' => true,
            'read_at' => Carbon::now(),
        ]);
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(): int
    {
        return DashboardNotification::unread()->update([
            'is_read' => true,
            'read_at' => Carbon::now(),
        ]);
    }
}

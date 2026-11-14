<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Notifikasi per user (FR-NOT-001, D-08) lewat channel database. Satu kelas
 * generik: judul, pesan, tautan, ikon — cukup untuk invoice baru, pengingat
 * jatuh tempo, hasil verifikasi pembayaran, pengumuman, dan pengaduan.
 */
class UserNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $title,
        public string $message,
        public ?string $url = null,
        public string $icon = 'fas fa-bell',
        public string $kind = 'general',
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => $this->kind,
            'title' => $this->title,
            'message' => $this->message,
            'url' => $this->url,
            'icon' => $this->icon,
        ];
    }
}

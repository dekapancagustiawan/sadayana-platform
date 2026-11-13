<?php

namespace App\Enums;

enum NotificationType: string
{
    case INFO = 'info';
    case SUCCESS = 'success';
    case WARNING = 'warning';
    case DANGER = 'danger';
    case USER_CREATED = 'user_created';
    case SYSTEM = 'system';

    public function label(): string
    {
        return match ($this) {
            self::INFO => 'Info',
            self::SUCCESS => 'Success',
            self::WARNING => 'Warning',
            self::DANGER => 'Alert',
            self::USER_CREATED => 'New User',
            self::SYSTEM => 'System',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::INFO => 'fas fa-info-circle',
            self::SUCCESS => 'fas fa-check-circle',
            self::WARNING => 'fas fa-exclamation-triangle',
            self::DANGER => 'fas fa-times-circle',
            self::USER_CREATED => 'fas fa-user-plus',
            self::SYSTEM => 'fas fa-cog',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::INFO => 'info',
            self::SUCCESS => 'success',
            self::WARNING => 'warning',
            self::DANGER => 'danger',
            self::USER_CREATED => 'primary',
            self::SYSTEM => 'secondary',
        };
    }
}

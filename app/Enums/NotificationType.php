<?php

namespace App\Enums;

enum NotificationType: string
{
    case Comment = 'comment';
    case Share = 'share';
    case Mention = 'mention';
    case AiDone = 'ai_done';
    case System = 'system';

    public function icon(): string
    {
        return match ($this) {
            self::Comment => '💬',
            self::Share => '🤝',
            self::Mention => '@',
            self::AiDone => '✨',
            self::System => 'ℹ️',
        };
    }

    /** Human label, used by the email-preference switches in settings. */
    public function label(): string
    {
        return match ($this) {
            self::Comment => (string) __('app.notif_comment'),
            self::Share => (string) __('app.notif_share'),
            self::Mention => (string) __('app.notif_mention'),
            self::AiDone => (string) __('app.notif_ai_done'),
            self::System => (string) __('app.notif_system'),
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

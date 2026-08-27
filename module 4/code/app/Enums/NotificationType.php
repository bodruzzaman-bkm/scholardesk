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
}

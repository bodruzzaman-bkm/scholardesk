<?php

namespace App\Enums;

enum UserRole: string
{
    case Researcher = 'researcher';
    case Administrator = 'administrator';

    public function label(): string
    {
        return match ($this) {
            self::Researcher => 'Researcher',
            self::Administrator => 'Administrator',
        };
    }

    public function isAdmin(): bool
    {
        return $this === self::Administrator;
    }
}

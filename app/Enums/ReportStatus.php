<?php

namespace App\Enums;

/**
 * The life of a content report (requirement 22).
 *
 * Resolved and Dismissed are both terminal but mean different things, and an
 * administrator needs to be able to tell them apart later: Resolved says the
 * report was justified and acted on, Dismissed says it was not. Collapsing
 * them into one "closed" state would lose the record of which reporters were
 * right.
 */
enum ReportStatus: string
{
    case Open = 'open';
    case Resolved = 'resolved';
    case Dismissed = 'dismissed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Resolved => 'Resolved',
            self::Dismissed => 'Dismissed',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Open => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
            self::Resolved => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
            self::Dismissed => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

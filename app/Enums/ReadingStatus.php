<?php

namespace App\Enums;

/**
 * Reading status for a paper.
 *
 * The backing values intentionally match the strings already stored in the
 * `papers.reading_status` column ("to read" with a space, not "to_read") so
 * that adding this enum does not require a data migration.
 */
enum ReadingStatus: string
{
    case ToRead = 'to read';
    case Reading = 'reading';
    case Read = 'read';

    public function label(): string
    {
        return match ($this) {
            self::ToRead => 'To read',
            self::Reading => 'Reading',
            self::Read => 'Read',
        };
    }

    /** Tailwind classes for the status badge, so views stay logic-free. */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::ToRead => 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
            self::Reading => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
            self::Read => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        };
    }

    /** @return array<string, string> value => label, for select inputs. */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

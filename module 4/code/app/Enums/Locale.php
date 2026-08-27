<?php

namespace App\Enums;

enum Locale: string
{
    case English = 'en';
    case Bengali = 'bn';

    public function label(): string
    {
        return match ($this) {
            self::English => 'English',
            self::Bengali => 'বাংলা',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $c) => [$c->value => $c->label()])
            ->all();
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

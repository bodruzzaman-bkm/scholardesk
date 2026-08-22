<?php

namespace App\Enums;

/** How a paper entered the library (ScholarDesk's PaperSource). */
enum PaperSource: string
{
    case Upload = 'upload';
    case Doi = 'doi';
    case Url = 'url';

    public function label(): string
    {
        return match ($this) {
            self::Upload => 'Uploaded PDF',
            self::Doi => 'Imported by DOI',
            self::Url => 'Imported from a link',
        };
    }
}

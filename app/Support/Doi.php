<?php

namespace App\Support;

class Doi
{
    /**
     * Reduce the many ways a DOI gets pasted to one canonical, lower-cased form.
     *
     * Accepts "https://doi.org/10.1/x", "http://dx.doi.org/10.1/x", "doi:10.1/x"
     * and bare "10.1/X", all of which become "10.1/x".
     */
    public static function normalize(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $doi = trim($raw);

        if ($doi === '') {
            return null;
        }

        $doi = preg_replace('#^https?://(dx\.)?doi\.org/#i', '', $doi);
        $doi = preg_replace('#^doi:\s*#i', '', $doi);

        return mb_strtolower(trim($doi));
    }

    /** A DOI always starts with a "10." prefix followed by a registrant code. */
    public static function isValid(?string $doi): bool
    {
        $doi = self::normalize($doi);

        return $doi !== null && preg_match('#^10\.\d{4,9}/\S+$#', $doi) === 1;
    }
}

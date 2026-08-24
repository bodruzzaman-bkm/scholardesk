<?php

namespace App\Services;

use App\Models\Paper;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Str;

/**
 * Formats papers as BibTeX, APA and plain text.
 *
 * Pure formatting with no database or HTTP access, which makes it directly
 * unit-testable — see tests/Unit/CitationServiceTest.php.
 */
class CitationService
{
    /** @var list<string> */
    public const FORMATS = ['bibtex', 'apa', 'text'];

    public function format(Paper $paper, string $format): string
    {
        return match ($format) {
            'bibtex' => $this->bibtex($paper),
            'apa' => $this->apa($paper),
            default => $this->plainText($paper),
        };
    }

    /** @param SupportCollection<int, Paper> $papers */
    public function formatMany(SupportCollection $papers, string $format): string
    {
        $separator = $format === 'bibtex' ? "\n\n" : "\n";

        return $papers
            ->map(fn (Paper $paper) => $this->format($paper, $format))
            ->implode($separator);
    }

    public function bibtex(Paper $paper): string
    {
        $key = $this->citationKey($paper);
        $type = $paper->venue ? 'article' : 'misc';

        $fields = array_filter([
            'title' => $paper->title,
            'author' => $this->bibtexAuthors($paper->authors),
            'year' => $paper->year,
            'journal' => $paper->venue,
            'doi' => $paper->doi,
        ], fn ($value) => filled($value));

        $lines = [];
        foreach ($fields as $name => $value) {
            $lines[] = sprintf('  %-8s = {%s},', $name, $this->escapeBibtex((string) $value));
        }

        // Trailing comma on the final field is legal BibTeX and keeps diffs small.
        return sprintf("@%s{%s,\n%s\n}", $type, $key, implode("\n", $lines));
    }

    public function apa(Paper $paper): string
    {
        $parts = [];

        $authors = $this->apaAuthors($paper->authors);
        $parts[] = $authors !== '' ? $authors : ($paper->title ?: 'Untitled');

        $parts[] = '('.($paper->year ?: 'n.d.').').';

        if ($authors !== '') {
            $parts[] = rtrim($paper->title ?: 'Untitled', '.').'.';
        }

        if (filled($paper->venue)) {
            $parts[] = rtrim($paper->venue, '.').'.';
        }

        if (filled($paper->doi)) {
            $parts[] = 'https://doi.org/'.$paper->doi;
        }

        return trim(implode(' ', array_filter($parts)));
    }

    public function plainText(Paper $paper): string
    {
        $segments = array_filter([
            $paper->authors,
            $paper->title,
            $paper->venue,
            $paper->year ? (string) $paper->year : null,
            $paper->doi ? 'doi:'.$paper->doi : null,
        ], fn ($v) => filled($v));

        return implode('. ', $segments).'.';
    }

    /** A stable, readable BibTeX key: firstauthorYEARfirstword. */
    public function citationKey(Paper $paper): string
    {
        // Strip a trailing "et al." first, so the key keys off the real lead
        // author rather than the literal word "al".
        $leadAuthor = preg_replace('/\s+et\s+al\.?$/i', '', trim((string) $paper->authors));

        $firstAuthor = Str::of((string) $leadAuthor)
            ->before(',')
            ->trim()
            ->explode(' ')
            ->filter()
            ->last() ?? '';

        // Cast to string: Str::of() returns a Stringable, which would never
        // compare equal to '' and so would defeat the fallbacks below.
        $surname = (string) Str::of((string) $firstAuthor)->ascii()->replaceMatches('/[^A-Za-z]/', '')->lower();
        $firstWord = (string) (Str::of((string) $paper->title)
            ->ascii()
            ->replaceMatches('/[^A-Za-z ]/', '')
            ->trim()
            ->explode(' ')
            ->filter()
            ->first() ?: 'untitled');

        $key = trim(implode('', [
            $surname !== '' ? $surname : 'anon',
            $paper->year ?: 'nd',
            Str::lower($firstWord),
        ]));

        return $key !== '' ? $key : 'paper'.$paper->id;
    }

    /** BibTeX separates authors with " and ", not commas. */
    private function bibtexAuthors(?string $authors): ?string
    {
        if (blank($authors)) {
            return null;
        }

        return collect(explode(',', $authors))
            ->map(fn (string $a) => trim($a))
            ->filter()
            ->implode(' and ');
    }

    private function apaAuthors(?string $authors): string
    {
        if (blank($authors)) {
            return '';
        }

        // "Vaswani et al." is a collapsed list, not a person: treat the name
        // before "et al." as the sole author and keep the suffix intact,
        // rather than parsing "al." as a surname.
        if (preg_match('/^(.*?)\s+et\s+al\.?$/i', trim($authors), $m) === 1) {
            $lead = trim($m[1]);

            return $lead !== '' ? $this->surnameOf($lead).' et al.' : '';
        }

        $list = collect(explode(',', $authors))
            ->map(fn (string $a) => trim($a))
            ->filter()
            ->map(function (string $name) {
                $bits = preg_split('/\s+/', $name) ?: [];
                $surname = array_pop($bits);
                $initials = collect($bits)
                    ->map(fn (string $b) => mb_strtoupper(mb_substr($b, 0, 1)).'.')
                    ->implode(' ');

                return $initials !== '' ? "{$surname}, {$initials}" : $surname;
            })
            ->values();

        if ($list->isEmpty()) {
            return '';
        }

        if ($list->count() === 1) {
            return rtrim($list->first(), '.').'.';
        }

        // APA joins the final author with an ampersand.
        $last = $list->pop();

        return $list->implode(', ').', & '.$last;
    }

    /** The last whitespace-separated token of a name, treated as the surname. */
    private function surnameOf(string $name): string
    {
        $bits = preg_split('/\s+/', trim($name)) ?: [];

        return (string) (end($bits) ?: $name);
    }

    /** Escape the characters that would otherwise break a BibTeX entry. */
    private function escapeBibtex(string $value): string
    {
        return str_replace(['{', '}'], ['\{', '\}'], $value);
    }
}

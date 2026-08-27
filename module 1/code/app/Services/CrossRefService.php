<?php

namespace App\Services;

use App\Support\Doi;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Looks up paper metadata by DOI via the Crossref REST API.
 *
 * Every failure path returns null rather than throwing, so that adding a paper
 * never breaks just because Crossref is slow or down — the user can still save
 * the paper and type the metadata in by hand.
 */
class CrossRefService
{
    private const TIMEOUT_SECONDS = 8;

    /**
     * @return array{title: ?string, abstract: ?string, venue: ?string, year: ?int, authors: ?string}|null
     */
    public function fetchMetadata(?string $doi): ?array
    {
        $cleanDoi = Doi::normalize($doi);

        if (! Doi::isValid($cleanDoi)) {
            return null;
        }

        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->retry(2, 200, throw: false)
                // Crossref's "polite pool" gives faster, more reliable service
                // to callers that identify themselves.
                ->withHeaders([
                    'User-Agent' => sprintf(
                        'ScholarDesk/1.0 (%s; mailto:%s)',
                        config('app.url'),
                        config('services.crossref.mailto', 'support@example.com')
                    ),
                ])
                ->get('https://api.crossref.org/works/'.rawurlencode($cleanDoi));
        } catch (ConnectionException $e) {
            Log::warning('Crossref lookup failed', ['doi' => $cleanDoi, 'error' => $e->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json('message');

        if (! is_array($data)) {
            return null;
        }

        return [
            'title' => $this->firstString($data['title'] ?? null),
            'abstract' => $this->cleanAbstract($data['abstract'] ?? null),
            'venue' => $this->firstString($data['container-title'] ?? null),
            'year' => $this->extractYear($data),
            'authors' => $this->formatAuthors($data['author'] ?? null),
        ];
    }

    /**
     * Crossref exposes the publication date under several keys depending on
     * whether the work was published in print, online, or is a preprint. The
     * original implementation only read `published-print`, so most records
     * came back with a null year.
     */
    private function extractYear(array $data): ?int
    {
        foreach (['published-print', 'published-online', 'published', 'issued', 'created'] as $key) {
            $year = $data[$key]['date-parts'][0][0] ?? null;

            if (is_numeric($year)) {
                return (int) $year;
            }
        }

        return null;
    }

    private function formatAuthors(mixed $authors): ?string
    {
        if (! is_array($authors) || $authors === []) {
            return null;
        }

        $names = [];

        foreach ($authors as $author) {
            if (! is_array($author)) {
                continue;
            }

            // Organisations have `name` instead of given/family.
            $name = trim(($author['given'] ?? '').' '.($author['family'] ?? ''))
                ?: ($author['name'] ?? '');

            if ($name !== '') {
                $names[] = $name;
            }
        }

        return $names === [] ? null : implode(', ', $names);
    }

    private function cleanAbstract(mixed $abstract): ?string
    {
        if (! is_string($abstract) || trim($abstract) === '') {
            return null;
        }

        // Crossref abstracts arrive as JATS XML.
        $text = strip_tags($abstract);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/^\s*abstract\s*:?\s*/i', '', $text);
        $text = preg_replace('/\s+/u', ' ', $text);

        return trim($text) ?: null;
    }

    private function firstString(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = $value[0] ?? null;
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return trim(strip_tags($value));
    }
}

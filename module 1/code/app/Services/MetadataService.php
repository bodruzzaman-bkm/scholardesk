<?php

namespace App\Services;

use App\Support\Doi;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Resolves paper metadata from a DOI or an article URL.
 *
 * Requirement 3 is "fetch title, authors, year, venue and abstract from a
 * pasted DOI *or URL*", so a single registry lookup is not enough:
 *
 *   DOI  -> Crossref, then OpenAlex, then DataCite. Crossref does not register
 *           arXiv, Zenodo or figshare DOIs; DataCite does.
 *   URL  -> arXiv id, else a DOI embedded in the path, else the page's own
 *           Google Scholar `citation_*` / Dublin Core meta tags. Pages keyed
 *           by a PMID or an internal id carry no DOI at all, so reading the
 *           page is the only way to resolve them.
 *
 * Every path returns null rather than throwing when nothing is found: adding a
 * paper must still succeed so the user can type the details in by hand.
 */
class MetadataService
{
    private const TIMEOUT = 12;

    /** Metadata lives in <head>; cap the read so a huge page cannot stall us. */
    private const MAX_HTML_BYTES = 500000;

    /**
     * Resolve whatever the user pasted.
     *
     * @return array<string, mixed>|null
     */
    public function lookup(?string $input): ?array
    {
        $input = trim((string) $input);

        if ($input === '') {
            return null;
        }

        return $this->looksLikeUrl($input)
            ? $this->lookupByUrl($input)
            : $this->lookupByDoi($input);
    }

    public function looksLikeUrl(string $input): bool
    {
        // A doi.org link is a DOI wearing a URL costume; treat it as a DOI.
        if (preg_match('#^https?://(dx\.)?doi\.org/#i', $input) === 1) {
            return false;
        }

        return preg_match('#^https?://#i', $input) === 1;
    }

    /** DOI -> Crossref, then OpenAlex, then DataCite. */
    public function lookupByDoi(?string $raw): ?array
    {
        foreach ($this->doiCandidates((string) $raw) as $doi) {
            foreach (['crossref', 'openAlex', 'dataCite'] as $registry) {
                $result = $this->{$registry}($doi);

                if ($result !== null) {
                    return $result;
                }
            }
        }

        return null;
    }

    /** URL -> arXiv, then a DOI in the path, then the page's meta tags. */
    public function lookupByUrl(string $url): ?array
    {
        $url = trim($url);

        // 1. arXiv abs/pdf links carry no DOI, so check before the DOI pattern.
        if ($arxivId = $this->extractArxivId($url)) {
            if ($result = $this->arxiv($arxivId)) {
                return $result;
            }
        }

        // 2. Most publisher URLs embed the DOI in the path.
        if ($doi = $this->extractDoi($url)) {
            if ($result = $this->lookupByDoi($doi)) {
                return array_merge($result, ['url' => $url]);
            }
        }

        // 3. Fall back to the page's own citation metadata.
        $fromPage = $this->htmlMeta($url);

        if ($fromPage === null) {
            return null;
        }

        // A DOI found on the page gives a richer, canonical record.
        if (filled($fromPage['doi'])) {
            if ($viaDoi = $this->lookupByDoi($fromPage['doi'])) {
                return array_merge($viaDoi, [
                    'url' => $url,
                    'pdf_url' => $viaDoi['pdf_url'] ?? $fromPage['pdf_url'] ?? null,
                ]);
            }
        }

        return filled($fromPage['title']) ? $fromPage : null;
    }

    // -----------------------------------------------------------------
    // Registries
    // -----------------------------------------------------------------

    private function crossref(string $doi): ?array
    {
        $data = $this->json('https://api.crossref.org/works/'.rawurlencode($doi));

        if (! is_array($data) || ! is_array($data['message'] ?? null)) {
            return null;
        }

        $m = $data['message'];

        return $this->normalize([
            'title' => $this->first($m['title'] ?? null),
            'authors' => $this->names($m['author'] ?? null),
            'year' => $this->crossrefYear($m),
            'venue' => $this->first($m['container-title'] ?? null),
            'abstract' => $this->cleanAbstract($m['abstract'] ?? null),
            'doi' => $m['DOI'] ?? $doi,
            'url' => $m['URL'] ?? null,
        ]);
    }

    private function openAlex(string $doi): ?array
    {
        $data = $this->json('https://api.openalex.org/works/doi:'.rawurlencode($doi));

        if (! is_array($data) || blank($data['title'] ?? null)) {
            return null;
        }

        $authors = collect($data['authorships'] ?? [])
            ->map(fn ($a) => $a['author']['display_name'] ?? null)
            ->filter()
            ->implode(', ');

        return $this->normalize([
            'title' => $data['title'],
            'authors' => $authors ?: null,
            'year' => $data['publication_year'] ?? null,
            'venue' => $data['primary_location']['source']['display_name'] ?? null,
            // OpenAlex ships abstracts as an inverted index, not prose.
            'abstract' => $this->invertedIndexToText($data['abstract_inverted_index'] ?? null),
            'doi' => $doi,
            'url' => $data['doi'] ?? null,
            'pdf_url' => $data['best_oa_location']['pdf_url'] ?? null,
        ]);
    }

    /** DataCite registers arXiv, Zenodo, figshare and most dataset DOIs. */
    private function dataCite(string $doi): ?array
    {
        $data = $this->json('https://api.datacite.org/dois/'.rawurlencode($doi));
        $a = $data['data']['attributes'] ?? null;

        if (! is_array($a)) {
            return null;
        }

        $title = $this->first(collect($a['titles'] ?? [])->pluck('title')->all());

        if (blank($title)) {
            return null;
        }

        $publisher = $a['publisher'] ?? null;

        return $this->normalize([
            'title' => $title,
            'authors' => collect($a['creators'] ?? [])
                ->map(fn ($c) => $c['name'] ?? trim(($c['givenName'] ?? '').' '.($c['familyName'] ?? '')))
                ->filter()
                ->implode(', ') ?: null,
            'year' => $a['publicationYear'] ?? null,
            'venue' => is_array($publisher) ? ($publisher['name'] ?? null) : $publisher,
            'abstract' => $this->cleanAbstract(
                collect($a['descriptions'] ?? [])->firstWhere('descriptionType', 'Abstract')['description'] ?? null
            ),
            'doi' => $doi,
            'url' => $a['url'] ?? null,
        ]);
    }

    /**
     * arXiv's own Atom API. arXiv DOIs are registered with DataCite rather
     * than Crossref, and very recent preprints may not be indexed anywhere
     * else yet, so for anything arXiv-shaped this is the authoritative source.
     */
    private function arxiv(string $id): ?array
    {
        $xml = $this->text('https://export.arxiv.org/api/query?id_list='.rawurlencode($id).'&max_results=1');

        if ($xml === null || preg_match('#<entry>([\s\S]*?)</entry>#', $xml, $entry) !== 1) {
            return null;
        }

        $body = $entry[1];
        $title = $this->decodeXml($this->match('#<title>([\s\S]*?)</title>#', $body));

        if (blank($title)) {
            return null;
        }

        preg_match_all('#<author>\s*<name>([\s\S]*?)</name>#', $body, $authorMatches);
        $published = $this->match('#<published>([^<]+)</published>#', $body);
        $journalRef = $this->match('#<arxiv:journal_ref[^>]*>([\s\S]*?)</arxiv:journal_ref>#', $body);

        return $this->normalize([
            'title' => $title,
            'authors' => collect($authorMatches[1] ?? [])
                ->map(fn ($n) => $this->decodeXml($n))
                ->filter()
                ->implode(', ') ?: null,
            'year' => $published ? (int) substr($published, 0, 4) : null,
            // A published version supersedes the preprint label.
            'venue' => $journalRef ? $this->decodeXml($journalRef) : 'arXiv',
            'abstract' => $this->cleanAbstract($this->match('#<summary>([\s\S]*?)</summary>#', $body)),
            'doi' => '10.48550/arxiv.'.$id,
            'url' => 'https://arxiv.org/abs/'.$id,
            // arXiv is open access, so the PDF can be fetched and read in-app.
            'pdf_url' => 'https://arxiv.org/pdf/'.$id,
        ]);
    }

    /**
     * Reads citation metadata embedded in a publisher's page.
     *
     * Nearly every publisher emits Google Scholar's `citation_*` tags (and
     * often Dublin Core), which is the only practical way to resolve pages
     * identified by a PMID, a PII or an internal document id.
     */
    public function htmlMeta(string $url): ?array
    {
        /*
         | This fetches a URL the user pasted, which makes it the same SSRF
         | primitive downloadPdf() is fenced against — and until now it was the
         | unfenced twin. Without the guard below, pasting
         | http://169.254.169.254/... or http://127.0.0.1:6379/ as an "article
         | link" made the server issue that request, and any internal page that
         | answered had its <title> handed back as the paper's title.
         */
        if (! $this->hostIsPublic($url)) {
            Log::warning('Refused to fetch citation metadata from a non-public host', ['url' => $url]);

            return null;
        }

        try {
            $response = Http::timeout(self::TIMEOUT)
                ->withHeaders([
                    'User-Agent' => $this->userAgent(),
                    'Accept' => 'text/html,application/xhtml+xml',
                ])
                /*
                 | Redirects are followed, because publisher links routinely
                 | bounce through a canonical URL — but a public host is free to
                 | redirect to a private one, so every hop is re-checked rather
                 | than trusting the first URL alone.
                 */
                ->withOptions(['allow_redirects' => [
                    'max' => 3,
                    'strict' => true,
                    'referer' => false,
                    'protocols' => ['http', 'https'],
                    'on_redirect' => function ($request, $response, $uri): void {
                        if (! $this->hostIsPublic((string) $uri)) {
                            throw new RuntimeException("Refused to follow a redirect to {$uri}");
                        }
                    },
                ]])
                ->get($url);
        } catch (ConnectionException $e) {
            Log::warning('Page fetch for citation metadata failed', ['url' => $url, 'error' => $e->getMessage()]);

            return null;
        } catch (Throwable $e) {
            // Covers the redirect guard above, which throws rather than
            // returning, since Guzzle gives on_redirect no way to say "stop".
            Log::warning('Page fetch for citation metadata refused', ['url' => $url, 'error' => $e->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        if (! str_contains(strtolower((string) $response->header('Content-Type')), 'html')) {
            return null;
        }

        $html = substr($response->body(), 0, self::MAX_HTML_BYTES);
        $meta = $this->parseMetaTags($html);

        $title = $this->metaFirst($meta, ['citation_title', 'dc.title', 'og:title'])
            ?? $this->decodeXml($this->match('#<title[^>]*>([\s\S]*?)</title>#i', $html));

        if (blank($title)) {
            return null;
        }

        $date = $this->metaFirst($meta, ['citation_publication_date', 'citation_date', 'dc.date']) ?? '';
        preg_match('/\d{4}/', $date, $yearMatch);

        $authors = array_merge($meta['citation_author'] ?? [], $meta['dc.creator'] ?? []);

        return $this->normalize([
            'title' => $title,
            'authors' => $authors === [] ? null : implode(', ', $authors),
            'year' => $yearMatch[0] ?? null,
            'venue' => $this->metaFirst($meta, [
                'citation_journal_title', 'citation_conference_title', 'citation_inbook_title',
                'dc.source', 'og:site_name',
            ]),
            'abstract' => $this->cleanAbstract(
                $this->metaFirst($meta, ['citation_abstract', 'dc.description', 'og:description'])
            ),
            'doi' => $this->metaFirst($meta, ['citation_doi', 'dc.identifier']) ?? $this->extractDoi($html),
            'url' => $url,
            'pdf_url' => $this->metaFirst($meta, ['citation_pdf_url']),
        ]);
    }

    /**
     * Fetch an open-access PDF so an imported paper is actually readable.
     *
     * Without this, a paper added by link is metadata only: no in-browser
     * reader, no highlights, no summary and no semantic search. arXiv and
     * other OA sources advertise a direct PDF, so it is worth retrieving.
     *
     * This makes the server fetch a user-supplied URL, so it is deliberately
     * narrow: https only, no redirects, a content-type check, and a hard size
     * cap. It refuses hosts that resolve to a private or loopback address,
     * which is the SSRF case that matters here.
     *
     * @return string|null the PDF bytes, or null if it could not be retrieved
     */
    public function downloadPdf(?string $url, int $maxBytes = 20_971_520): ?string
    {
        if (blank($url) || preg_match('#^https://#i', $url) !== 1) {
            return null;
        }

        if (! $this->hostIsPublic($url)) {
            Log::warning('Refused to fetch a PDF from a non-public host', ['url' => $url]);

            return null;
        }

        try {
            $response = Http::timeout(self::TIMEOUT * 2)
                ->withoutRedirecting()
                ->withHeaders(['User-Agent' => $this->userAgent(), 'Accept' => 'application/pdf'])
                ->get($url);
        } catch (ConnectionException $e) {
            Log::warning('PDF download failed', ['url' => $url, 'error' => $e->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $body = $response->body();

        // Trust the magic bytes over the declared content-type; some hosts
        // serve a PDF as application/octet-stream.
        if (! str_starts_with($body, '%PDF-') || strlen($body) > $maxBytes) {
            return null;
        }

        return $body;
    }

    /**
     * Blocks loopback, RFC1918 and link-local targets so an import cannot
     * probe the LAN or a cloud metadata endpoint.
     *
     * Two details matter beyond the obvious address check:
     *
     *   - the scheme is pinned to http/https, because file://, gopher:// and
     *     dict:// are each an SSRF primitive and none of them is something a
     *     citation lookup ever needs;
     *   - *every* address the host resolves to is checked, not just the first.
     *     A name answering with one public and one private A record would
     *     otherwise walk straight through the guard.
     *
     * Resolution failure is treated as unsafe rather than safe: a name we
     * cannot resolve is a name we cannot vouch for.
     */
    private function hostIsPublic(string $url): bool
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        if (! in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return false;
        }

        $addresses = $this->resolveAll($host);

        if ($addresses === []) {
            return false;
        }

        foreach ($addresses as $ip) {
            $isPublic = filter_var(
                $ip,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            );

            if ($isPublic === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Every IP a host resolves to, or the literal itself when the host is
     * already an address.
     *
     * @return list<string>
     */
    private function resolveAll(string $host): array
    {
        // An IPv6 literal arrives bracketed inside a URL: http://[::1]/path
        $literal = trim($host, '[]');

        if (filter_var($literal, FILTER_VALIDATE_IP) !== false) {
            return [$literal];
        }

        $addresses = gethostbynamel($host);

        return $addresses === false ? [] : array_values($addresses);
    }

    // -----------------------------------------------------------------
    // Parsing helpers
    // -----------------------------------------------------------------

    /**
     * DOIs get pasted with brackets, trailing punctuation, ".pdf" suffixes and
     * version markers. Each stripped form is tried in turn.
     *
     * @return list<string>
     */
    public function doiCandidates(string $raw): array
    {
        $doi = Doi::normalize($raw) ?? '';
        $doi = preg_split('/[?#]/', $doi)[0] ?? '';
        $doi = preg_replace('/^[\(\[\{<\x27"]+/', '', $doi) ?? $doi;
        $doi = preg_replace('/[.,;:\)\]\}>\x27"]+$/', '', $doi) ?? $doi;
        $doi = rtrim($doi, '/');

        $out = [];
        $add = function (string $candidate) use (&$out): void {
            $candidate = trim($candidate);

            if ($candidate !== ''
                && preg_match('#^10\.\d{4,}/\S+$#', $candidate) === 1
                && ! in_array($candidate, $out, true)) {
                $out[] = $candidate;
            }
        };

        $add($doi);
        // Strip a format suffix, then a version suffix, then both.
        $noFormat = preg_replace('/\.(full|abstract|long|supplementary)(\.pdf)?$/i', '', $doi) ?? $doi;
        $noFormat = preg_replace('/\.pdf$/i', '', $noFormat) ?? $noFormat;
        $add($noFormat);
        $add(preg_replace('/v\d+$/i', '', $noFormat) ?? $noFormat);
        $add(preg_replace('/v\d+$/i', '', $doi) ?? $doi);

        return $out;
    }

    /** Pulls a DOI out of arbitrary text or a URL. */
    public function extractDoi(string $text): ?string
    {
        if (preg_match('#10\.\d{4,}/[^\s"\x27<>&]+#', $text, $m) !== 1) {
            return null;
        }

        return $this->doiCandidates($m[0])[0] ?? null;
    }

    public function extractArxivId(string $input): ?string
    {
        $patterns = [
            '#(?:^|/|:)10\.48550/arxiv\.([a-z-]+(?:\.[a-z]{2})?/\d{7}|\d{4}\.\d{4,5})#i',
            '#arxiv\.org/(?:abs|pdf)/([a-z-]+(?:\.[a-z]{2})?/\d{7}|\d{4}\.\d{4,5})#i',
            '#^arxiv:\s*([a-z-]+(?:\.[a-z]{2})?/\d{7}|\d{4}\.\d{4,5})#i',
            '#^(\d{4}\.\d{4,5})(?:v\d+)?$#',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, trim($input), $m) === 1 && filled($m[1] ?? null)) {
                return preg_replace('/v\d+$/i', '', $m[1]);
            }
        }

        return null;
    }

    /**
     * Reads <meta name=... content=...> pairs, tolerating either attribute
     * order and both `name` and `property`.
     *
     * @return array<string, list<string>>
     */
    private function parseMetaTags(string $html): array
    {
        $map = [];

        preg_match_all('#<meta\b[^>]*>#i', $html, $tags);

        foreach ($tags[0] ?? [] as $tag) {
            $hasName = preg_match('#\b(?:name|property)\s*=\s*["\x27]([^"\x27]+)["\x27]#i', $tag, $n) === 1;
            $hasContent = preg_match('#\bcontent\s*=\s*["\x27]([^"\x27]*)["\x27]#i', $tag, $c) === 1;

            if ($hasName && $hasContent && trim($c[1]) !== '') {
                $map[strtolower($n[1])][] = $this->decodeXml($c[1]);
            }
        }

        return $map;
    }

    /**
     * @param  array<string, list<string>>  $meta
     * @param  list<string>  $keys
     */
    private function metaFirst(array $meta, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $meta[strtolower($key)][0] ?? null;

            if (filled($value)) {
                return trim($value);
            }
        }

        return null;
    }

    /** OpenAlex ships abstracts as {word: [positions]}; rebuild the prose. */
    private function invertedIndexToText(mixed $index): ?string
    {
        if (! is_array($index) || $index === []) {
            return null;
        }

        $words = [];
        foreach ($index as $word => $positions) {
            foreach ((array) $positions as $position) {
                $words[(int) $position] = $word;
            }
        }

        if ($words === []) {
            return null;
        }

        ksort($words);

        return $this->cleanAbstract(implode(' ', $words));
    }

    /** @param array<string, mixed> $m */
    private function crossrefYear(array $m): ?int
    {
        foreach (['published-print', 'published-online', 'published', 'issued', 'created'] as $key) {
            $year = $m[$key]['date-parts'][0][0] ?? null;

            if (is_numeric($year)) {
                return (int) $year;
            }
        }

        return null;
    }

    private function names(mixed $authors): ?string
    {
        if (! is_array($authors)) {
            return null;
        }

        $names = [];
        foreach ($authors as $author) {
            if (! is_array($author)) {
                continue;
            }
            // Organisations have `name` instead of given/family.
            $name = trim(($author['given'] ?? '').' '.($author['family'] ?? '')) ?: ($author['name'] ?? '');
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
        $text = preg_replace('/^\s*abstract\s*:?\s*/i', '', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text) ?: null;
    }

    private function first(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = $value[0] ?? null;
        }

        return is_string($value) && trim($value) !== '' ? trim(strip_tags($value)) : null;
    }

    private function decodeXml(?string $value): string
    {
        return trim(html_entity_decode((string) $value, ENT_QUOTES | ENT_XML1 | ENT_HTML5, 'UTF-8'));
    }

    private function match(string $pattern, string $subject): ?string
    {
        return preg_match($pattern, $subject, $m) === 1 ? ($m[1] ?? null) : null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalize(array $data): array
    {
        $year = $data['year'] ?? null;

        return [
            'title' => filled($data['title'] ?? null) ? mb_substr(trim($data['title']), 0, 255) : null,
            'authors' => filled($data['authors'] ?? null) ? mb_substr(trim($data['authors']), 0, 1000) : null,
            'year' => is_numeric($year) && $year > 1400 && $year < 2200 ? (int) $year : null,
            'venue' => filled($data['venue'] ?? null) ? mb_substr(trim($data['venue']), 0, 255) : null,
            'abstract' => filled($data['abstract'] ?? null) ? mb_substr($data['abstract'], 0, 10000) : null,
            'doi' => Doi::normalize($data['doi'] ?? null),
            'url' => filled($data['url'] ?? null) ? mb_substr($data['url'], 0, 2048) : null,
            'pdf_url' => filled($data['pdf_url'] ?? null) ? mb_substr($data['pdf_url'], 0, 2048) : null,
        ];
    }

    private function json(string $url): mixed
    {
        try {
            $response = Http::timeout(self::TIMEOUT)
                ->retry(2, 200, throw: false)
                ->withHeaders(['User-Agent' => $this->userAgent(), 'Accept' => 'application/json'])
                ->get($url);
        } catch (ConnectionException $e) {
            Log::warning('Metadata lookup failed', ['url' => $url, 'error' => $e->getMessage()]);

            return null;
        }

        return $response->successful() ? $response->json() : null;
    }

    private function text(string $url): ?string
    {
        try {
            $response = Http::timeout(self::TIMEOUT)
                ->withHeaders(['User-Agent' => $this->userAgent()])
                ->get($url);
        } catch (ConnectionException $e) {
            Log::warning('Metadata lookup failed', ['url' => $url, 'error' => $e->getMessage()]);

            return null;
        }

        return $response->successful() ? $response->body() : null;
    }

    /** Crossref's "polite pool" gives faster service to callers who identify themselves. */
    private function userAgent(): string
    {
        return sprintf(
            'ScholarDesk/1.0 (%s; mailto:%s)',
            config('app.url'),
            config('services.crossref.mailto', 'support@example.com')
        );
    }
}

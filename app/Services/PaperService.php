<?php

namespace App\Services;

use App\Enums\PaperSource;
use App\Enums\ReadingStatus;
use App\Jobs\IndexPaper;
use App\Models\Paper;
use App\Support\Doi;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Business logic for the paper library.
 *
 * Controllers call these methods; they never build queries or touch storage
 * directly. This mirrors the service layer ScholarDesk uses and keeps the
 * controllers under a screenful.
 */
class PaperService
{
    public function __construct(private MetadataService $metadata) {}

    /**
     * Build the filtered, sorted, paginated library listing.
     *
     * `with()` eager-loads tags to avoid an N+1 query when the index renders a
     * tag pill row for every paper.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginateLibrary(int $userId, array $filters, int $perPage = 12): LengthAwarePaginator
    {
        return Paper::query()
            ->ownedBy($userId)
            ->with('tags')
            ->search($filters['q'] ?? null)
            ->withTag($filters['tag'] ?? null)
            ->withStatus($filters['status'] ?? null)
            ->withYear($filters['year'] ?? null)
            ->withAuthor($filters['author'] ?? null)
            ->withVenue($filters['venue'] ?? null)
            ->inCollection($filters['collection'] ?? null)
            ->sorted($filters['sort'] ?? null)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Create a paper from an uploaded PDF and/or a DOI or article URL.
     *
     * Metadata supplied by the user always wins over the registry lookup, so a
     * manual correction is never silently overwritten.
     *
     * @param  array<string, mixed>  $input
     */
    public function createForUser(int $userId, array $input, ?UploadedFile $file): Paper
    {
        $filePath = $file?->store('papers', 'public');

        /*
         | The user pastes one field ("identifier") holding either a DOI or an
         | article URL; MetadataService works out which and resolves it through
         | the appropriate registry. A lookup miss is not an error - the paper
         | is still created so the details can be typed in by hand.
         */
        $identifier = $input['identifier'] ?? $input['doi'] ?? null;
        $metadata = filled($identifier) ? ($this->metadata->lookup($identifier) ?? []) : [];

        $isUrl = filled($identifier) && $this->metadata->looksLikeUrl((string) $identifier);

        /*
         | A pasted URL can resolve to a DOI the user already holds, which the
         | request could not check because the DOI was unknown at validation
         | time. Catching it here turns a unique-constraint crash into an
         | ordinary field error.
         */
        $resolvedDoi = $metadata['doi'] ?? null;

        if (filled($resolvedDoi) && Paper::query()->ownedBy($userId)->where('doi', $resolvedDoi)->exists()) {
            throw ValidationException::withMessages([
                'identifier' => 'That paper is already in your library.',
            ]);
        }

        /*
         | An open-access PDF makes the difference between importing a citation
         | and importing a readable paper: the reader, highlights, summaries
         | and semantic search all need the file. Failure is fine - the paper
         | is still created with its metadata.
         */
        if ($filePath === null && filled($metadata['pdf_url'] ?? null)) {
            $filePath = $this->storeFetchedPdf($metadata['pdf_url'], $metadata['title'] ?? null);
        }

        $paper = Paper::create([
            'user_id' => $userId,
            'title' => $this->firstFilled(
                $input['title'] ?? null,
                $metadata['title'] ?? null,
                $this->titleFromFilename($file)
            ),
            'authors' => $metadata['authors'] ?? null,
            'year' => $metadata['year'] ?? null,
            'venue' => $metadata['venue'] ?? null,
            'abstract' => $this->firstFilled($input['abstract'] ?? null, $metadata['abstract'] ?? null),
            // A URL that resolved to a DOI is stored under both, so citation
            // export still gets a DOI even though the user pasted a link.
            'doi' => $metadata['doi'] ?? ($isUrl ? null : Doi::normalize($identifier)),
            'url' => $metadata['url'] ?? ($isUrl ? $identifier : null),
            'source' => $this->sourceFor($file, $identifier, $isUrl),
            'file_path' => $filePath,
            'reading_status' => ReadingStatus::ToRead,
        ]);

        $this->queueIndexing($paper);

        return $paper;
    }

    /**
     * Save a fetched open-access PDF onto the public disk.
     *
     * @return string|null the storage path, or null if it could not be fetched
     */
    private function storeFetchedPdf(string $pdfUrl, ?string $title): ?string
    {
        $bytes = $this->metadata->downloadPdf($pdfUrl);

        if ($bytes === null) {
            return null;
        }

        $name = str($title ?: 'paper')->slug()->limit(60, '')->value() ?: 'paper';
        $path = 'papers/'.$name.'-'.Str::random(8).'.pdf';

        return Storage::disk('public')->put($path, $bytes) ? $path : null;
    }

    private function sourceFor(?UploadedFile $file, ?string $identifier, bool $isUrl): PaperSource
    {
        if ($file !== null) {
            return PaperSource::Upload;
        }

        return $isUrl ? PaperSource::Url : PaperSource::Doi;
    }

    /**
     * Create several papers from a multi-file upload.
     *
     * One bad file must not sink the batch, so failures are collected and
     * reported rather than thrown. Each successful paper is queued for
     * indexing individually.
     *
     * @param  list<UploadedFile>  $files
     * @return array{created: list<Paper>, failed: list<array{name: string, error: string}>}
     */
    public function createManyFromUploads(int $userId, array $files): array
    {
        $created = [];
        $failed = [];

        foreach ($files as $file) {
            try {
                $created[] = $this->createForUser($userId, [], $file);
            } catch (\Throwable $e) {
                $failed[] = [
                    'name' => $file->getClientOriginalName(),
                    'error' => $e->getMessage(),
                ];
            }
        }

        return ['created' => $created, 'failed' => $failed];
    }

    /**
     * Queue text extraction + embedding.
     *
     * Dispatched after the response so a large PDF never delays the upload.
     * A dispatch failure is swallowed: the paper is saved either way, it just
     * will not be searchable until re-indexed.
     */
    public function queueIndexing(Paper $paper): void
    {
        if (! $paper->hasPdf()) {
            return;
        }

        try {
            IndexPaper::dispatch($paper->id)->afterResponse();
        } catch (\Throwable $e) {
            Log::warning('Could not queue paper indexing', [
                'paper_id' => $paper->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Update metadata and re-sync the collection/tag pivots in one transaction,
     * so a failure part-way through cannot leave the pivots inconsistent.
     *
     * @param  array<string, mixed>  $data
     * @param  list<int>|null  $collectionIds
     * @param  list<int>|null  $tagIds
     */
    public function update(Paper $paper, array $data, ?array $collectionIds, ?array $tagIds): Paper
    {
        DB::transaction(function () use ($paper, $data, $collectionIds, $tagIds) {
            $paper->update($data);

            // A missing key means "the form did not offer this control", which
            // must not be confused with "the user cleared every checkbox".
            if ($collectionIds !== null) {
                $paper->collections()->sync($collectionIds);
            }

            if ($tagIds !== null) {
                $paper->tags()->sync($tagIds);
            }
        });

        return $paper->refresh();
    }

    /** Delete the paper and its stored PDF together. */
    public function delete(Paper $paper): void
    {
        $path = $paper->file_path;

        DB::transaction(fn () => $paper->delete());

        // Only remove the file once the row is gone, so a failed delete never
        // orphans a paper that points at a missing file.
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }

    public function setStatus(Paper $paper, ReadingStatus $status): Paper
    {
        $paper->update(['reading_status' => $status]);

        return $paper;
    }

    /**
     * Distinct years and venues in this user's library, for the filter dropdowns.
     *
     * @return array{years: list<int>, venues: list<string>, authors: list<string>}
     */
    public function filterOptions(int $userId): array
    {
        $years = Paper::query()->ownedBy($userId)
            ->whereNotNull('year')
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year')
            ->all();

        $venues = Paper::query()->ownedBy($userId)
            ->whereNotNull('venue')
            ->where('venue', '!=', '')
            ->distinct()
            ->orderBy('venue')
            ->pluck('venue')
            ->all();

        return ['years' => $years, 'venues' => $venues, 'authors' => $this->distinctAuthors($userId)];
    }

    /**
     * Individual author names across the library, for the author filter.
     *
     * `authors` is stored as one comma-separated string, so the list has to be
     * split and de-duplicated in PHP; there is no author table to select from.
     *
     * @return list<string>
     */
    public function distinctAuthors(int $userId, int $limit = 200): array
    {
        return Paper::query()
            ->ownedBy($userId)
            ->whereNotNull('authors')
            ->where('authors', '!=', '')
            ->pluck('authors')
            ->flatMap(fn (string $authors) => explode(',', $authors))
            // "Vaswani et al." is a collapsed list, not a person: keep the
            // lead author so the option reads as a name. A substring match
            // still finds the paper it came from.
            ->map(fn (string $name) => trim(preg_replace('/\s+et\.?\s*al\.?$/i', '', trim($name)) ?? $name))
            // Drop a bare "et al." and anything too short to be a name.
            ->reject(fn (string $name) => $name === ''
                || mb_strlen($name) < 2
                || preg_match('/^et\.?\s*al\.?$/i', $name) === 1)
            ->unique()
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * Reading-status counts for this user, as one grouped query rather than
     * three separate COUNT statements.
     *
     * @return array<string, int>
     */
    public function statusCounts(int $userId): array
    {
        $counts = Paper::query()
            ->ownedBy($userId)
            ->groupBy('reading_status')
            ->select('reading_status', DB::raw('count(*) as aggregate'))
            ->pluck('aggregate', 'reading_status')
            ->all();

        $result = [];
        foreach (ReadingStatus::cases() as $case) {
            $result[$case->value] = (int) ($counts[$case->value] ?? 0);
        }

        return $result;
    }

    private function firstFilled(?string ...$candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (filled($candidate)) {
                return trim($candidate);
            }
        }

        return null;
    }

    /** Fall back to the uploaded filename rather than a generic "Untitled Paper". */
    private function titleFromFilename(?UploadedFile $file): string
    {
        if ($file === null) {
            return 'Untitled paper';
        }

        $name = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $name = trim(preg_replace('/[_\-]+/', ' ', $name));

        return $name !== '' ? mb_substr($name, 0, 255) : 'Untitled paper';
    }
}

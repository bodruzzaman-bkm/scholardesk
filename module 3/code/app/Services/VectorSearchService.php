<?php

namespace App\Services;

use App\Models\Collection;
use App\Models\Paper;
use App\Models\PaperChunk;
use Illuminate\Support\Collection as SupportCollection;

/**
 * Similarity retrieval over paper chunks.
 *
 * ScholarDesk runs this as a pgvector query ordered by `<=>`. SQLite has no
 * vector type, so chunks are loaded and scored in PHP. That is O(n) per query
 * rather than index-assisted, but it is *exact* rather than approximate and
 * stays well under a second for a personal library of a few hundred papers.
 * If this ever moves to PostgreSQL, only this class needs rewriting.
 *
 * The access boundary is resolved to a concrete paper-id list *before* any
 * scoring happens. Retrieval is the last step before text reaches a language
 * model, so an unscoped query here would leak one user's papers into another
 * user's answer.
 */
class VectorSearchService
{
    /**
     * Anything scoring above this shares *some* signal with the query and is
     * worth handing to the language model, which is instructed to say when the
     * excerpts do not answer the question.
     */
    private const MIN_RETRIEVAL_SCORE = 0.01;

    /**
     * Floor for results shown directly to the user.
     *
     * Feature-hashed embeddings have a baseline similarity from character
     * trigrams shared by any two pieces of English prose, so an unrelated
     * query never scores zero: off-topic queries land around 0.26-0.29 against
     * this kind of corpus.
     *
     * A floor above that band was tried and rejected. It did suppress
     * off-topic queries, but it also dropped short on-topic ones — "tumour
     * detection in scans" scores 0.28 against a paper that is plainly about
     * exactly that, because a four-word query shares little surface with a
     * long passage. Losing a paper the user knows is there is worse than
     * showing one they can dismiss at a glance, so the floor only removes
     * genuine noise and weak matches are LABELLED instead (see isWeak()).
     */
    private const MIN_DISPLAY_SCORE = 0.15;

    /**
     * Below this a match is real but thin, and the UI says so rather than
     * presenting it with the same confidence as a strong hit.
     */
    public const WEAK_MATCH_SCORE = 0.32;

    /** True when a result should be shown but flagged as a weak match. */
    public static function isWeak(float $score): bool
    {
        return $score < self::WEAK_MATCH_SCORE;
    }

    public function __construct(private EmbeddingService $embeddings) {}

    /**
     * Top-k chunks most similar to a query string.
     *
     * @return SupportCollection<int, array{chunk: PaperChunk, paper: Paper, score: float}>
     */
    public function search(
        string $query,
        int $userId,
        string $scope = 'library',
        ?int $scopeId = null,
        int $k = 6,
        ?int $excludePaperId = null,
    ): SupportCollection {
        $queryVector = $this->embeddings->embed($query);

        if ($this->isZero($queryVector)) {
            return collect();
        }

        return $this->rank($queryVector, $userId, $scope, $scopeId, $k, $excludePaperId);
    }

    /**
     * Papers most similar to a given paper, by comparing chunk centroids.
     *
     * The source paper is excluded up front rather than filtered afterwards:
     * a paper's own chunks are always its nearest neighbours, so post-filtering
     * would need unbounded headroom on k.
     *
     * @return SupportCollection<int, array{paper: Paper, score: float}>
     */
    public function relatedPapers(Paper $paper, int $userId, int $limit = 5): SupportCollection
    {
        $vectors = $paper->chunks()->whereNotNull('embedding')->pluck('embedding')->all();

        if ($vectors === []) {
            return collect();
        }

        $centroid = $this->embeddings->centroid($vectors);

        if ($this->isZero($centroid)) {
            return collect();
        }

        // Pull more chunks than needed, because several may belong to one paper.
        $hits = $this->rank($centroid, $userId, 'library', null, $limit * 6, $paper->id);

        return $hits
            ->groupBy(fn (array $hit) => $hit['paper']->id)
            ->map(fn (SupportCollection $group) => [
                'paper' => $group->first()['paper'],
                // A paper's score is its single best-matching chunk.
                'score' => $group->max('score'),
            ])
            ->filter(fn (array $hit) => $hit['score'] >= self::MIN_DISPLAY_SCORE)
            ->sortByDesc('score')
            ->take($limit)
            ->values();
    }

    /**
     * Semantic search across the library, collapsed to one row per paper.
     *
     * @return SupportCollection<int, array{paper: Paper, score: float, snippet: string}>
     */
    public function searchPapers(string $query, int $userId, int $limit = 10): SupportCollection
    {
        return $this->search($query, $userId, 'library', null, $limit * 6)
            ->groupBy(fn (array $hit) => $hit['paper']->id)
            ->map(function (SupportCollection $group) {
                $best = $group->sortByDesc('score')->first();

                return [
                    'paper' => $best['paper'],
                    'score' => $best['score'],
                    'snippet' => $this->snippet($best['chunk']->content),
                ];
            })
            // Shown to the user directly, so weak matches are dropped rather
            // than presented as results.
            ->filter(fn (array $hit) => $hit['score'] >= self::MIN_DISPLAY_SCORE)
            ->sortByDesc('score')
            ->take($limit)
            ->values();
    }

    /**
     * Score every accessible chunk against a query vector.
     *
     * @param  list<float>  $queryVector
     * @return SupportCollection<int, array{chunk: PaperChunk, paper: Paper, score: float}>
     */
    private function rank(
        array $queryVector,
        int $userId,
        string $scope,
        ?int $scopeId,
        int $k,
        ?int $excludePaperId,
    ): SupportCollection {
        $paperIds = $this->accessiblePaperIds($scope, $scopeId, $userId);

        if ($excludePaperId !== null) {
            $paperIds = array_values(array_diff($paperIds, [$excludePaperId]));
        }

        if ($paperIds === []) {
            return collect();
        }

        $papers = Paper::query()->whereIn('id', $paperIds)->get()->keyBy('id');

        return PaperChunk::query()
            ->whereIn('paper_id', $paperIds)
            ->whereNotNull('embedding')
            ->get()
            ->map(function (PaperChunk $chunk) use ($queryVector, $papers) {
                return [
                    'chunk' => $chunk,
                    'paper' => $papers[$chunk->paper_id] ?? null,
                    'score' => $this->embeddings->similarity($queryVector, $chunk->embedding ?? []),
                ];
            })
            // A non-positive score means no shared signal at all.
            ->filter(fn (array $hit) => $hit['paper'] !== null && $hit['score'] > self::MIN_RETRIEVAL_SCORE)
            ->sortByDesc('score')
            ->take($k)
            ->values();
    }

    /**
     * The papers this user may retrieve from, for a given scope.
     *
     * @return list<int>
     */
    private function accessiblePaperIds(string $scope, ?int $scopeId, int $userId): array
    {
        if ($scope === 'paper' && $scopeId !== null) {
            $paper = Paper::query()->accessibleBy($userId)->whereKey($scopeId)->first(['id']);

            return $paper ? [$paper->id] : [];
        }

        if ($scope === 'collection' && $scopeId !== null) {
            $collection = Collection::query()->accessibleBy($userId)->whereKey($scopeId)->first();

            if ($collection === null) {
                return [];
            }

            return $collection->papers()->pluck('papers.id')->all();
        }

        return Paper::query()->accessibleBy($userId)->pluck('id')->all();
    }

    /** A readable excerpt for a search result. */
    private function snippet(string $content, int $length = 240): string
    {
        $text = preg_replace('/\s+/u', ' ', $content) ?? $content;

        return mb_strimwidth(trim($text), 0, $length, '…');
    }

    /** @param list<float> $vector */
    private function isZero(array $vector): bool
    {
        foreach ($vector as $value) {
            if ($value != 0.0) {
                return false;
            }
        }

        return true;
    }
}

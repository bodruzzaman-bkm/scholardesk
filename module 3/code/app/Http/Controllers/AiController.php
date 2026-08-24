<?php

namespace App\Http\Controllers;

use App\Exceptions\AiUnavailableException;
use App\Models\Collection;
use App\Models\Paper;
use App\Services\RagService;
use App\Services\VectorSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * JSON endpoints for the AI panel.
 *
 * Every handler converts AiUnavailableException into a 503 with a readable
 * message, so the front end shows a non-blocking error state and the rest of
 * the page keeps working — the product rule that AI never becomes a hard
 * dependency.
 */
class AiController extends Controller
{
    public function __construct(
        private RagService $rag,
        private VectorSearchService $vectors,
    ) {}

    public function summarize(Paper $paper): JsonResponse
    {
        $this->authorize('useAi', $paper);

        return $this->guard(fn () => [
            'summary' => $this->rag->summarizePaper($paper),
        ]);
    }

    public function askPaper(Request $request, Paper $paper): JsonResponse
    {
        $this->authorize('useAi', $paper);

        $validated = $request->validate([
            'question' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        return $this->guard(function () use ($paper, $request, $validated) {
            $result = $this->rag->askPaper($paper, $request->user(), $validated['question']);

            return [
                'answer' => $result['answer'],
                'citations' => $result['citations'],
            ];
        });
    }

    /** Ask across everything the user can read — no collection required. */
    public function askLibrary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'question' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        return $this->guard(function () use ($request, $validated) {
            $result = $this->rag->askLibrary($request->user(), $validated['question']);

            return [
                'answer' => $result['answer'],
                'citations' => $result['citations'],
            ];
        });
    }

    public function askCollection(Request $request, Collection $collection): JsonResponse
    {
        $this->authorize('useAi', $collection);

        $validated = $request->validate([
            'question' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        return $this->guard(function () use ($collection, $request, $validated) {
            $result = $this->rag->askCollection($collection, $request->user(), $validated['question']);

            return [
                'answer' => $result['answer'],
                'citations' => $result['citations'],
            ];
        });
    }

    /**
     * Draft a literature review from selected papers, or the whole collection
     * when no selection is given (requirement 14).
     */
    public function review(Request $request, Collection $collection): JsonResponse
    {
        $this->authorize('useAi', $collection);

        $validated = $request->validate([
            'paper_ids' => ['nullable', 'array'],
            // Restricted to papers actually in this collection, so a crafted
            // id cannot pull an unrelated paper into the draft.
            'paper_ids.*' => [
                'integer',
                Rule::exists('collection_paper', 'paper_id')->where('collection_id', $collection->id),
            ],
        ]);

        $paperIds = $validated['paper_ids'] ?? null;

        return $this->guard(function () use ($collection, $request, $paperIds) {
            $review = $this->rag->draftReview($collection, $request->user(), $paperIds ?: null);

            return [
                'review_id' => $review->id,
                'title' => $review->title,
                'content' => $review->content,
                'paper_count' => count($review->paper_ids ?? []),
            ];
        });
    }

    /** Related papers — pure vector similarity, so it works with no API key. */
    public function related(Request $request, Paper $paper): JsonResponse
    {
        $this->authorize('view', $paper);

        $related = $this->vectors->relatedPapers($paper, $request->user()->id)
            ->map(fn (array $hit) => [
                'id' => $hit['paper']->id,
                'title' => $hit['paper']->title,
                'authors' => $hit['paper']->authors,
                'year' => $hit['paper']->year,
                'score' => round($hit['score'], 3),
                'url' => route('papers.show', $hit['paper']),
            ])
            ->values();

        return response()->json([
            'related' => $related,
            'indexed' => $paper->isIndexed(),
        ]);
    }

    /**
     * Run a closure, translating an AI failure into a 503 the UI can render.
     *
     * @param  callable(): array<string, mixed>  $work
     */
    private function guard(callable $work): JsonResponse
    {
        try {
            return response()->json($work());
        } catch (AiUnavailableException $e) {
            return response()->json(['error' => $e->getMessage()], 503);
        }
    }
}

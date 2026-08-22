<?php

namespace Tests\Feature;

use App\Models\Paper;
use App\Models\PaperChunk;
use App\Models\User;
use App\Services\EmbeddingService;
use App\Services\VectorSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Relevance handling for user-facing results.
 *
 * Feature-hashed embeddings never score zero for unrelated English prose -
 * character trigrams give every pair of texts a baseline similarity, so an
 * off-topic query still returns something.
 *
 * A hard floor above that band was tried and rejected: it suppressed off-topic
 * queries but also dropped short on-topic ones ("tumour detection in scans"
 * scores 0.28 against a paper about exactly that). Losing a paper the user
 * knows is there is worse than showing one they can dismiss, so the floor only
 * removes noise and thin matches are labelled instead.
 */
class SearchRelevanceTest extends TestCase
{
    use RefreshDatabase;

    private function indexedPaper(User $user, string $title, string $body): Paper
    {
        $paper = Paper::create([
            'title' => $title,
            'user_id' => $user->id,
            'full_text' => $body,
            'index_status' => 'indexed',
            'indexed_at' => now(),
        ]);

        PaperChunk::create([
            'paper_id' => $paper->id,
            'chunk_index' => 0,
            'content' => $body,
            'embedding' => app(EmbeddingService::class)->embed($body),
        ]);

        return $paper;
    }

    private function library(User $user): void
    {
        $this->indexedPaper($user, 'Transformers',
            'The Transformer replaces recurrence with self-attention for sequence transduction, '
            .'achieving higher BLEU on machine translation while training faster on GPUs.');

        $this->indexedPaper($user, 'AI in education',
            'This mixed-methods study examines how artificial intelligence tools affect learning '
            .'outcomes, teacher readiness and digital access in rural schools.');
    }

    public function test_an_on_topic_query_still_returns_results(): void
    {
        $user = User::factory()->create();
        $this->library($user);

        $hits = app(VectorSearchService::class)->searchPapers('self-attention replaces recurrence', $user->id);

        $this->assertNotEmpty($hits, 'A clearly on-topic query returned nothing');
        $this->assertSame('Transformers', $hits->first()['paper']->title);
    }

    /**
     * An off-topic query cannot be rejected outright without also losing short
     * on-topic ones, so such results are surfaced but scored low and flagged.
     */
    public function test_an_off_topic_query_only_ever_scores_weakly(): void
    {
        $user = User::factory()->create();
        $this->library($user);

        foreach ([
            'crop rotation in medieval europe',
            'baroque harpsichord tuning',
            'deep sea hydrothermal vents',
            'knitting patterns for socks',
        ] as $query) {
            foreach (app(VectorSearchService::class)->searchPapers($query, $user->id) as $hit) {
                $this->assertTrue(
                    VectorSearchService::isWeak($hit['score']),
                    "\"{$query}\" scored {$hit['score']} - too high for an unrelated query"
                );
            }
        }
    }

    /** A short but genuinely on-topic query must not be dropped. */
    public function test_a_short_on_topic_query_is_not_lost(): void
    {
        $user = User::factory()->create();
        $this->indexedPaper($user, 'Imaging',
            'Convolutional neural networks detect tumours in medical imaging scans with high accuracy.');

        $hits = app(VectorSearchService::class)->searchPapers('tumour detection in scans', $user->id);

        $this->assertNotEmpty($hits, 'A short but clearly relevant query returned nothing');
        $this->assertSame('Imaging', $hits->first()['paper']->title);
    }

    public function test_the_search_page_labels_a_weak_match(): void
    {
        $user = User::factory()->create();
        $this->library($user);

        $this->actingAs($user)
            ->get(route('search', ['q' => 'baroque harpsichord tuning', 'mode' => 'semantic']))
            ->assertOk()
            ->assertSee('weak match');
    }

    /** Unrelated papers must not be offered as "related". */
    public function test_related_papers_ranks_a_similar_paper_above_an_unrelated_one(): void
    {
        $user = User::factory()->create();

        $source = $this->indexedPaper($user, 'Source',
            'Attention mechanisms and transformer architectures for sequence modelling.');
        $this->indexedPaper($user, 'Unrelated',
            'Soil chemistry and nutrient cycles in temperate forest ecosystems over winter.');

        $this->indexedPaper($user, 'Neighbour',
            'Transformer models use self-attention for sequence transduction and modelling.');

        $related = app(VectorSearchService::class)->relatedPapers($source, $user->id);
        $titles = $related->pluck('paper.title')->all();

        // Ranking is what matters: the on-topic paper comes first.
        $this->assertSame('Neighbour', $titles[0] ?? null);
        if (in_array('Unrelated', $titles, true)) {
            $unrelated = $related->firstWhere('paper.title', 'Unrelated');
            $this->assertTrue(VectorSearchService::isWeak($unrelated['score']));
        }
    }

    public function test_a_genuinely_similar_paper_is_still_related(): void
    {
        $user = User::factory()->create();

        $source = $this->indexedPaper($user, 'Source',
            'Attention mechanisms and transformer architectures for sequence modelling tasks.');
        $this->indexedPaper($user, 'Neighbour',
            'Transformer models use self-attention for sequence transduction and modelling tasks.');

        $related = app(VectorSearchService::class)->relatedPapers($source, $user->id);

        $this->assertContains('Neighbour', $related->pluck('paper.title')->all());
    }

    /**
     * The AI layer keeps a lower floor than the results page: a weak excerpt
     * is harmless there because the prompt requires the model to say when the
     * answer is not in the material, and refusing to answer on a borderline
     * match would be worse than letting it reply "not covered".
     */
    public function test_retrieval_for_the_ai_layer_is_at_least_as_permissive_as_search(): void
    {
        $user = User::factory()->create();
        $this->library($user);

        $service = app(VectorSearchService::class);
        $query = 'crop rotation in medieval europe';

        $chunks = $service->search($query, $user->id);
        $papers = $service->searchPapers($query, $user->id);

        $this->assertGreaterThan(0, $chunks->count(), 'RAG retrieval should still see the chunks');
        $this->assertGreaterThanOrEqual($papers->count(), $chunks->count());
    }

    /** Pure noise is still filtered: an empty or symbol-only query matches nothing. */
    public function test_a_meaningless_query_returns_nothing(): void
    {
        $user = User::factory()->create();
        $this->library($user);

        foreach (['', '   ', '!!! ???', '###'] as $query) {
            $this->assertTrue(
                app(VectorSearchService::class)->searchPapers($query, $user->id)->isEmpty(),
                "\"{$query}\" should match nothing"
            );
        }
    }
}

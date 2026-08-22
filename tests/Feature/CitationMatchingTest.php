<?php

namespace Tests\Feature;

use App\Models\Collection;
use App\Models\CollectionMember;
use App\Models\Paper;
use App\Models\PaperChunk;
use App\Models\User;
use App\Services\EmbeddingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Citation extraction from a model's answer.
 *
 * The prompt asks for "[P12]", but models drift: gpt-oss emits fullwidth
 * brackets, others use parentheses or bold the marker. Matching the literal
 * string missed genuine citations and fell back to listing every retrieved
 * source, which makes the citation chips meaningless.
 */
class CitationMatchingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Collection $collection;

    /** @var array<int, Paper> */
    private array $papers = [];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.ai.provider' => 'groq',
            'services.groq.key' => 'test-key',
            'services.groq.model' => 'test-model',
        ]);

        $this->user = User::factory()->create();
        $this->collection = Collection::create(['name' => 'Set', 'user_id' => $this->user->id]);
        CollectionMember::create([
            'collection_id' => $this->collection->id,
            'user_id' => $this->user->id,
            'role' => \App\Enums\MemberRole::Owner,
        ]);

        $embeddings = app(EmbeddingService::class);

        foreach (['Robot navigation with reinforcement learning', 'Reinforcement learning for robot control'] as $i => $body) {
            $paper = Paper::create([
                'title' => 'Paper '.($i + 1),
                'user_id' => $this->user->id,
                'full_text' => $body,
                'index_status' => 'indexed',
                'indexed_at' => now(),
            ]);

            PaperChunk::create([
                'paper_id' => $paper->id,
                'chunk_index' => 0,
                'content' => $body,
                'embedding' => $embeddings->embed($body),
            ]);

            $this->collection->papers()->attach($paper->id);
            $this->papers[] = $paper;
        }
    }

    private function fakeAnswer(string $text): void
    {
        Http::fake(['api.groq.com/*' => Http::response([
            'choices' => [['message' => ['content' => $text]]],
        ], 200)]);
    }

    private function ask(): array
    {
        return $this->actingAs($this->user)
            ->postJson(route('ai.collection.ask', $this->collection), [
                'question' => 'What do these papers have in common?',
            ])
            ->assertOk()
            ->json();
    }

    public function test_a_standard_bracket_citation_is_matched(): void
    {
        $id = $this->papers[0]->id;
        $this->fakeAnswer("Both use reinforcement learning [P{$id}].");

        $citations = $this->ask()['citations'];

        $this->assertCount(1, $citations);
        $this->assertSame($id, $citations[0]['paper_id']);
    }

    /** gpt-oss emits fullwidth brackets rather than ASCII ones. */
    public function test_a_fullwidth_bracket_citation_is_matched(): void
    {
        $id = $this->papers[0]->id;
        $this->fakeAnswer("Both use reinforcement learning \u{3010}P{$id}\u{3011}.");

        $citations = $this->ask()['citations'];

        $this->assertCount(1, $citations);
        $this->assertSame($id, $citations[0]['paper_id']);
    }

    public function test_a_parenthesised_or_bold_citation_is_matched(): void
    {
        $id = $this->papers[1]->id;
        $this->fakeAnswer("Control is the shared theme (P{$id}).");

        $this->assertSame($id, $this->ask()['citations'][0]['paper_id']);

        $this->fakeAnswer("Control is the shared theme **P{$id}**.");

        $this->assertSame($id, $this->ask()['citations'][0]['paper_id']);
    }

    public function test_only_the_referenced_paper_is_cited(): void
    {
        [$first, $second] = $this->papers;
        $this->fakeAnswer("Only the first is relevant [P{$first->id}].");

        $citations = $this->ask()['citations'];

        $this->assertCount(1, $citations);
        $this->assertSame($first->id, $citations[0]['paper_id']);
        $this->assertNotContains($second->id, array_column($citations, 'paper_id'));
    }

    public function test_several_citations_are_all_matched(): void
    {
        [$first, $second] = $this->papers;
        $this->fakeAnswer("Both agree [P{$first->id}] and [P{$second->id}].");

        $this->assertCount(2, $this->ask()['citations']);
    }

    /**
     * P1 must not match P12 - the label needs a word boundary, or a two-digit
     * id would light up an unrelated single-digit paper.
     */
    public function test_a_label_does_not_match_a_longer_id(): void
    {
        $paper = Paper::create([
            'title' => 'Paper twelve',
            'user_id' => $this->user->id,
            'index_status' => 'indexed',
        ]);

        $this->assertFalse(
            (new \ReflectionMethod(\App\Services\RagService::class, 'answerCites'))
                ->invoke(app(\App\Services\RagService::class), 'See [P12] for details.', 1)
        );

        $this->assertTrue(
            (new \ReflectionMethod(\App\Services\RagService::class, 'answerCites'))
                ->invoke(app(\App\Services\RagService::class), 'See [P12] for details.', 12)
        );

        $paper->delete();
    }
}

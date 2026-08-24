<?php

namespace Tests\Feature;

use App\Enums\MemberRole;
use App\Models\Collection;
use App\Models\CollectionMember;
use App\Models\Paper;
use App\Models\PaperChunk;
use App\Models\User;
use App\Services\EmbeddingService;
use App\Services\VectorSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SemanticSearchTest extends TestCase
{
    use RefreshDatabase;

    private EmbeddingService $embeddings;

    private VectorSearchService $vectors;

    protected function setUp(): void
    {
        parent::setUp();
        $this->embeddings = app(EmbeddingService::class);
        $this->vectors = app(VectorSearchService::class);
    }

    /** Create a paper with one embedded chunk of the given text. */
    private function indexedPaper(User $user, string $title, string $body): Paper
    {
        $paper = Paper::create([
            'title' => $title,
            'user_id' => $user->id,
            'index_status' => 'indexed',
            'indexed_at' => now(),
            'full_text' => $body,
        ]);

        PaperChunk::create([
            'paper_id' => $paper->id,
            'chunk_index' => 0,
            'content' => $body,
            'embedding' => $this->embeddings->embed($body),
        ]);

        return $paper;
    }

    public function test_semantic_search_finds_the_topically_relevant_paper(): void
    {
        $user = User::factory()->create();

        $this->indexedPaper($user, 'Robotics',
            'Reinforcement learning is used for robot arm control and navigation in warehouses.');
        $this->indexedPaper($user, 'Agriculture',
            'Crop rotation practices in medieval northern europe and soil nutrient cycles.');

        $results = $this->vectors->searchPapers('robot control with reinforcement learning', $user->id);

        $this->assertNotEmpty($results);
        $this->assertSame('Robotics', $results->first()['paper']->title);
    }

    public function test_search_results_include_the_matched_snippet(): void
    {
        $user = User::factory()->create();
        $this->indexedPaper($user, 'Imaging',
            'Convolutional neural networks detect tumours in medical imaging scans with high accuracy.');

        $results = $this->vectors->searchPapers('tumour detection in scans', $user->id);

        $this->assertNotEmpty($results);
        $this->assertNotEmpty($results->first()['snippet']);
    }

    /**
     * The retrieval boundary is a security boundary: it is the last step
     * before text would be sent to a language model.
     */
    public function test_search_never_returns_another_users_papers(): void
    {
        $mine = User::factory()->create();
        $theirs = User::factory()->create();

        $this->indexedPaper($theirs, 'Secret study',
            'Reinforcement learning is used for robot arm control and navigation.');

        $results = $this->vectors->searchPapers('robot arm reinforcement learning', $mine->id);

        $this->assertTrue($results->isEmpty(), 'Another user\'s paper leaked into search results');
    }

    public function test_a_paper_shared_via_a_collection_becomes_searchable(): void
    {
        $owner = User::factory()->create();
        $collaborator = User::factory()->create();

        $paper = $this->indexedPaper($owner, 'Shared study',
            'Reinforcement learning is used for robot arm control and navigation.');

        $collection = Collection::create(['name' => 'Team', 'user_id' => $owner->id]);
        $collection->papers()->attach($paper->id);
        CollectionMember::create([
            'collection_id' => $collection->id,
            'user_id' => $collaborator->id,
            'role' => MemberRole::Viewer,
        ]);

        $results = $this->vectors->searchPapers('robot arm reinforcement learning', $collaborator->id);

        $this->assertNotEmpty($results);
        $this->assertSame('Shared study', $results->first()['paper']->title);
    }

    public function test_related_papers_excludes_the_source_paper(): void
    {
        $user = User::factory()->create();

        $source = $this->indexedPaper($user, 'Source',
            'Attention mechanisms and transformer architectures for sequence modelling.');
        $this->indexedPaper($user, 'Neighbour',
            'Transformer models use self-attention for sequence transduction tasks.');
        $this->indexedPaper($user, 'Unrelated',
            'Soil chemistry and nutrient cycles in temperate forest ecosystems.');

        $related = $this->vectors->relatedPapers($source, $user->id);

        $titles = $related->pluck('paper.title')->all();

        $this->assertNotContains('Source', $titles);
        $this->assertContains('Neighbour', $titles);
    }

    public function test_related_papers_is_empty_for_an_unindexed_paper(): void
    {
        $user = User::factory()->create();
        $paper = Paper::create(['title' => 'No text', 'user_id' => $user->id]);

        $this->assertTrue($this->vectors->relatedPapers($paper, $user->id)->isEmpty());
    }

    public function test_the_search_page_renders_both_modes(): void
    {
        $user = User::factory()->create();
        $this->indexedPaper($user, 'Findable',
            'Reinforcement learning is used for robot arm control and navigation.');

        $this->actingAs($user)->get(route('search'))->assertOk()->assertSee('Semantic');

        $this->actingAs($user)
            ->get(route('search', ['q' => 'robot arm control', 'mode' => 'semantic']))
            ->assertOk()
            ->assertSee('Findable');

        $this->actingAs($user)
            ->get(route('search', ['q' => 'Findable', 'mode' => 'keyword']))
            ->assertOk()
            ->assertSee('Findable');
    }

    public function test_the_related_endpoint_reports_when_a_paper_is_not_indexed(): void
    {
        $user = User::factory()->create();
        $paper = Paper::create(['title' => 'Bare', 'user_id' => $user->id]);

        $this->actingAs($user)
            ->getJson(route('ai.paper.related', $paper))
            ->assertOk()
            ->assertJson(['related' => [], 'indexed' => false]);
    }

    public function test_the_related_endpoint_refuses_another_users_paper(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $paper = Paper::create(['title' => 'Private', 'user_id' => $owner->id]);

        $this->actingAs($stranger)
            ->getJson(route('ai.paper.related', $paper))
            ->assertForbidden();
    }
}

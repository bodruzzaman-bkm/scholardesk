<?php

namespace Tests\Feature;

use App\Models\Collection;
use App\Models\Paper;
use App\Models\PaperChunk;
use App\Models\User;
use App\Services\AiService;
use App\Services\EmbeddingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The AI layer must degrade gracefully: with no API key configured, every AI
 * endpoint returns a readable 503 and the rest of the app is untouched.
 */
class AiLayerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Default to "not configured" so tests never call a real provider.
        // Both drivers are cleared, since the active one comes from
        // services.ai.provider.
        config([
            'services.ai.provider' => 'groq',
            'services.groq.key' => null,
            'services.gemini.key' => null,
        ]);
    }

    /** Configure the active provider with a dummy key. */
    private function configureAi(): void
    {
        config([
            'services.ai.provider' => 'groq',
            'services.groq.key' => 'test-key',
            'services.groq.model' => 'test-model',
        ]);
    }

    /** A faked Groq chat-completion response. */
    private function fakeProvider(string $text, int $status = 200): void
    {
        Http::fake(['api.groq.com/*' => Http::response(
            $status === 200
                ? ['choices' => [['message' => ['content' => $text]]]]
                : ['error' => ['message' => $text]],
            $status
        )]);
    }

    private function indexedPaper(User $user): Paper
    {
        $body = 'This study evaluates transformer architectures for sequence modelling tasks.';

        $paper = Paper::create([
            'title' => 'Study',
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

    public function test_the_service_reports_when_it_is_not_configured(): void
    {
        $this->assertFalse(app(AiService::class)->isConfigured());

        $this->configureAi();
        $this->assertTrue((new AiService)->isConfigured());
    }

    /**
     * Guard against the suite picking up real credentials from .env: an
     * un-faked request would then hit a live provider and spend real quota.
     */
    public function test_the_test_environment_carries_no_real_api_key(): void
    {
        $this->assertEmpty(env('GROQ_API_KEY'), 'A real GROQ_API_KEY leaked into the test environment');
        $this->assertEmpty(env('GEMINI_API_KEY'), 'A real GEMINI_API_KEY leaked into the test environment');
    }

    public function test_ai_endpoints_return_503_with_a_readable_message_when_unconfigured(): void
    {
        $user = User::factory()->create();
        $paper = $this->indexedPaper($user);

        $this->actingAs($user)
            ->postJson(route('ai.paper.summary', $paper))
            ->assertStatus(503)
            ->assertJsonStructure(['error']);

        $this->actingAs($user)
            ->postJson(route('ai.paper.ask', $paper), ['question' => 'What method is used?'])
            ->assertStatus(503)
            ->assertJsonStructure(['error']);
    }

    /** The core product keeps working with AI switched off entirely. */
    public function test_the_core_app_is_unaffected_when_ai_is_unconfigured(): void
    {
        $user = User::factory()->create();
        $paper = $this->indexedPaper($user);

        $this->actingAs($user)->get(route('papers.show', $paper))->assertOk()->assertSee('Study');
        $this->actingAs($user)->get(route('papers.index'))->assertOk();
        $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $this->actingAs($user)->get(route('search', ['q' => 'transformer', 'mode' => 'semantic']))->assertOk();
    }

    public function test_the_paper_page_says_ai_is_not_configured(): void
    {
        $user = User::factory()->create();
        $paper = $this->indexedPaper($user);

        // Each AI card states the same thing independently, so the paper page
        // is never silently missing a capability.
        $this->actingAs($user)->get(route('papers.show', $paper))
            ->assertOk()
            ->assertSee('AI summary')
            ->assertSee('Ask this paper')
            ->assertSee('Related in your library')
            ->assertSee('Add an API key');
    }

    public function test_a_question_is_validated_before_any_provider_call(): void
    {
        $user = User::factory()->create();
        $paper = $this->indexedPaper($user);

        $this->actingAs($user)
            ->postJson(route('ai.paper.ask', $paper), ['question' => 'no'])
            ->assertStatus(422);
    }

    public function test_ai_endpoints_refuse_another_users_paper(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $paper = $this->indexedPaper($owner);

        $this->actingAs($stranger)
            ->postJson(route('ai.paper.summary', $paper))
            ->assertForbidden();
    }

    public function test_a_viewer_cannot_run_ai_over_a_shared_collection(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();

        $collection = Collection::create(['name' => 'Shared', 'user_id' => $owner->id]);
        \App\Models\CollectionMember::create([
            'collection_id' => $collection->id,
            'user_id' => $viewer->id,
            'role' => \App\Enums\MemberRole::Viewer,
        ]);

        $this->actingAs($viewer)
            ->postJson(route('ai.collection.ask', $collection), ['question' => 'What is common here?'])
            ->assertForbidden();
    }

    /** With a key configured and the provider faked, a summary comes back. */
    public function test_a_summary_is_returned_when_the_provider_answers(): void
    {
        $this->configureAi();
        $this->fakeProvider("## TL;DR\n\nIt works.");

        $user = User::factory()->create();
        $paper = $this->indexedPaper($user);

        $this->actingAs($user)
            ->postJson(route('ai.paper.summary', $paper))
            ->assertOk()
            ->assertJsonPath('summary', "## TL;DR\n\nIt works.");
    }

    public function test_a_rate_limited_provider_becomes_a_readable_503(): void
    {
        $this->configureAi();
        $this->fakeProvider('quota exceeded', 429);

        $user = User::factory()->create();
        $paper = $this->indexedPaper($user);

        $response = $this->actingAs($user)
            ->postJson(route('ai.paper.summary', $paper))
            ->assertStatus(503);

        $this->assertStringContainsString('busy', strtolower($response->json('error')));
    }

    public function test_asking_a_collection_question_cites_its_sources(): void
    {
        $this->configureAi();

        $user = User::factory()->create();
        $paper = $this->indexedPaper($user);
        $collection = Collection::create(['name' => 'Set', 'user_id' => $user->id]);
        \App\Models\CollectionMember::create([
            'collection_id' => $collection->id,
            'user_id' => $user->id,
            'role' => \App\Enums\MemberRole::Owner,
        ]);
        $collection->papers()->attach($paper->id);

        $this->fakeProvider("Transformers are used [P{$paper->id}].");

        $response = $this->actingAs($user)
            ->postJson(route('ai.collection.ask', $collection), ['question' => 'What architecture is used?'])
            ->assertOk();

        $citations = $response->json('citations');

        $this->assertNotEmpty($citations);
        $this->assertSame($paper->id, $citations[0]['paper_id']);
        // The turn is persisted so the conversation survives a reload.
        $this->assertDatabaseHas('chat_messages', ['role' => 'assistant']);
    }
}

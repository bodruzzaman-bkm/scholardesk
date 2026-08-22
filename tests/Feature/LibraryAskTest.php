<?php

namespace Tests\Feature;

use App\Enums\MemberRole;
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
 * Library-wide Q&A.
 *
 * Cross-paper Q&A is the product's differentiator, but it used to live only on
 * a collection page - so a user with no collections never saw that it existed.
 * This entry point answers across everything they can read.
 */
class LibraryAskTest extends TestCase
{
    use RefreshDatabase;

    private function configureAi(): void
    {
        config([
            'services.ai.provider' => 'groq',
            'services.groq.key' => 'test-key',
            'services.groq.model' => 'test-model',
        ]);
    }

    private function fakeAnswer(string $text): void
    {
        Http::fake(['api.groq.com/*' => Http::response([
            'choices' => [['message' => ['content' => $text]]],
        ], 200)]);
    }

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

    public function test_a_user_with_no_collections_can_still_ask_across_their_library(): void
    {
        $this->configureAi();

        $user = User::factory()->create();
        $paper = $this->indexedPaper($user, 'Adoption study',
            'Rural connectivity and teacher readiness limit adoption of new tools.');

        $this->assertSame(0, Collection::where('user_id', $user->id)->count());

        $this->fakeAnswer("Connectivity is the recurring barrier [P{$paper->id}].");

        $response = $this->actingAs($user)
            ->postJson(route('ai.ask'), ['question' => 'What barriers recur?'])
            ->assertOk();

        $this->assertNotEmpty($response->json('citations'));
        $this->assertSame($paper->id, $response->json('citations.0.paper_id'));
    }

    public function test_the_dashboard_shows_the_assistant(): void
    {
        $this->configureAi();
        $user = User::factory()->create();
        // With something indexed, the ask box is offered.
        $this->indexedPaper($user, 'Something', 'Rural connectivity limits adoption.');

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Ask your library')
            ->assertSee('your indexed papers')
            ->assertSee('ai-chat-library', false);
    }

    /** With nothing indexed the box is withheld and the reason given. */
    public function test_the_dashboard_assistant_explains_an_empty_index(): void
    {
        $this->configureAi();
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Ask your library')
            ->assertSee('No papers have indexed text yet')
            ->assertDontSee('ai-chat-library', false);
    }

    public function test_the_dashboard_says_so_when_ai_is_unconfigured(): void
    {
        config(['services.ai.provider' => 'groq', 'services.groq.key' => null]);
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Add an API key');
    }

    /** A library with papers but no indexed text gets a pointed hint. */
    public function test_the_dashboard_warns_when_nothing_is_indexed(): void
    {
        $this->configureAi();
        $user = User::factory()->create();
        Paper::create(['title' => 'No text', 'user_id' => $user->id]);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('None of your papers have indexed text yet');
    }

    public function test_the_empty_collections_page_explains_what_collections_unlock(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('collections.index'))
            ->assertOk()
            ->assertSee('Generate a literature-review draft')
            ->assertSee('ask across your entire library');
    }

    /** Retrieval stays scoped: another user's papers must never be answered from. */
    public function test_library_ask_never_reaches_another_users_papers(): void
    {
        $this->configureAi();

        $mine = User::factory()->create();
        $theirs = User::factory()->create();

        $this->indexedPaper($theirs, 'Their secret',
            'Rural connectivity and teacher readiness limit adoption of new tools.');

        // No accessible chunks -> a clear error, and no provider call at all.
        Http::fake();

        $this->actingAs($mine)
            ->postJson(route('ai.ask'), ['question' => 'What barriers recur?'])
            ->assertStatus(503);

        Http::assertNothingSent();
    }

    public function test_a_shared_paper_is_answerable_from_the_library_scope(): void
    {
        $this->configureAi();

        $owner = User::factory()->create();
        $collaborator = User::factory()->create();

        $paper = $this->indexedPaper($owner, 'Shared study',
            'Rural connectivity and teacher readiness limit adoption of new tools.');

        $collection = Collection::create(['name' => 'Team', 'user_id' => $owner->id]);
        $collection->papers()->attach($paper->id);
        CollectionMember::create([
            'collection_id' => $collection->id,
            'user_id' => $collaborator->id,
            'role' => MemberRole::Viewer,
        ]);

        $this->fakeAnswer("Connectivity [P{$paper->id}].");

        $this->actingAs($collaborator)
            ->postJson(route('ai.ask'), ['question' => 'What barriers recur?'])
            ->assertOk()
            ->assertJsonPath('citations.0.paper_id', $paper->id);
    }

    public function test_an_empty_library_reports_that_there_is_nothing_to_search(): void
    {
        $this->configureAi();
        Http::fake();

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson(route('ai.ask'), ['question' => 'Anything at all?'])
            ->assertStatus(503);

        $this->assertStringContainsString('library', strtolower($response->json('error')));
    }

    public function test_the_question_is_validated(): void
    {
        $this->configureAi();
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('ai.ask'), ['question' => 'no'])->assertStatus(422);
    }

    public function test_guests_cannot_ask(): void
    {
        $this->postJson(route('ai.ask'), ['question' => 'What is here?'])->assertUnauthorized();
    }
}

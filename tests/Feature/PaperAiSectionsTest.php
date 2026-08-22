<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\Paper;
use App\Models\PaperChunk;
use App\Models\User;
use App\Services\EmbeddingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * AI assistance on the paper page, split into separate sections - Summary,
 * Ask, Related - as in the original ScholarDesk right rail, rather than one
 * combined panel.
 */
class PaperAiSectionsTest extends TestCase
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

    private function indexedPaper(User $user): Paper
    {
        $body = 'Transformer architectures rely on self-attention for sequence modelling.';

        $paper = Paper::create([
            'title' => 'Indexed paper',
            'user_id' => $user->id,
            'file_path' => 'papers/x.pdf',
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

    public function test_the_paper_page_shows_three_separate_ai_sections(): void
    {
        $this->configureAi();
        $user = User::factory()->create();
        $paper = $this->indexedPaper($user);

        $this->actingAs($user)->get(route('papers.show', $paper))
            ->assertOk()
            ->assertSee('AI summary')
            ->assertSee('Ask this paper')
            ->assertSee('Related in your library')
            // Each is its own Alpine component, not one shared panel.
            ->assertSee('aiSummary(', false)
            ->assertSee('aiChat(', false)
            ->assertSee('aiRelated(', false);
    }

    public function test_the_summary_button_is_offered_for_an_indexed_paper(): void
    {
        $this->configureAi();
        $user = User::factory()->create();
        $paper = $this->indexedPaper($user);

        $this->actingAs($user)->get(route('papers.show', $paper))
            ->assertOk()
            ->assertSee('Generate summary');
    }

    /**
     * Summarising and asking both need extracted text. Rather than offering a
     * button that always fails, the section explains why it is unavailable.
     */
    public function test_an_unindexed_paper_explains_why_ai_is_unavailable(): void
    {
        $this->configureAi();
        $user = User::factory()->create();
        $paper = Paper::create([
            'title' => 'Scanned',
            'user_id' => $user->id,
            'file_path' => 'papers/scan.pdf',
            'index_status' => 'no_text',
            'indexed_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('papers.show', $paper))->assertOk();

        $response->assertSee('no extractable text');
        $response->assertSee('no indexed text yet');
        // The button is withheld rather than offered and then failing.
        $response->assertDontSee('Generate summary');
    }

    public function test_related_papers_renders_even_when_ai_is_unconfigured(): void
    {
        config(['services.ai.provider' => 'groq', 'services.groq.key' => null]);

        $user = User::factory()->create();
        $paper = $this->indexedPaper($user);

        // Similarity is local, so this section works with no API key at all.
        $this->actingAs($user)->get(route('papers.show', $paper))
            ->assertOk()
            ->assertSee('Related in your library')
            ->assertSee('aiRelated(', false);
    }

    /**
     * Turns were persisted but never read back, so reloading the page threw
     * the conversation away.
     */
    public function test_a_previous_conversation_is_restored_on_reload(): void
    {
        $this->configureAi();
        $user = User::factory()->create();
        $paper = $this->indexedPaper($user);

        $session = ChatSession::create([
            'user_id' => $user->id,
            'scope' => 'paper',
            'paper_id' => $paper->id,
            'title' => 'Earlier chat',
        ]);
        ChatMessage::create([
            'chat_session_id' => $session->id,
            'role' => 'user',
            'content' => 'What is self-attention?',
        ]);
        ChatMessage::create([
            'chat_session_id' => $session->id,
            'role' => 'assistant',
            'content' => 'It relates every token to every other token.',
        ]);

        $this->actingAs($user)->get(route('papers.show', $paper))
            ->assertOk()
            ->assertSee('What is self-attention?', false)
            ->assertSee('It relates every token to every other token.', false);
    }

    public function test_another_users_conversation_is_never_shown(): void
    {
        $this->configureAi();
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $paper = $this->indexedPaper($owner);

        $session = ChatSession::create([
            'user_id' => $owner->id,
            'scope' => 'paper',
            'paper_id' => $paper->id,
        ]);
        ChatMessage::create([
            'chat_session_id' => $session->id,
            'role' => 'user',
            'content' => 'A private question',
        ]);

        // The intruder cannot even reach the paper.
        $this->actingAs($intruder)->get(route('papers.show', $paper))->assertForbidden();

        // And the owner's own view carries only their own turns.
        $this->actingAs($owner)->get(route('papers.show', $paper))
            ->assertOk()
            ->assertSee('A private question', false);
    }

    /** Asking twice builds a conversation rather than replacing the answer. */
    public function test_successive_questions_accumulate_in_one_session(): void
    {
        $this->configureAi();
        $user = User::factory()->create();
        $paper = $this->indexedPaper($user);

        Http::fake(['api.groq.com/*' => Http::response([
            'choices' => [['message' => ['content' => 'An answer.']]],
        ], 200)]);

        $this->actingAs($user)->postJson(route('ai.paper.ask', $paper), ['question' => 'First question?'])->assertOk();
        $this->actingAs($user)->postJson(route('ai.paper.ask', $paper), ['question' => 'Second question?'])->assertOk();

        $session = ChatSession::where('paper_id', $paper->id)->firstOrFail();

        // 2 questions + 2 answers, all in one session.
        $this->assertSame(4, $session->messages()->count());
        $this->assertSame(1, ChatSession::where('paper_id', $paper->id)->count());
    }
}

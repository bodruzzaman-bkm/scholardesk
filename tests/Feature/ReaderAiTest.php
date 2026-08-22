<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\Paper;
use App\Models\PaperChunk;
use App\Models\User;
use App\Services\EmbeddingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The AI assistant inside the PDF reader.
 *
 * Summary and Q&A previously lived only on the paper detail page, so a
 * question that occurred to you mid-read meant leaving the reader.
 */
class ReaderAiTest extends TestCase
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

    private function readablePaper(User $user, bool $indexed = true): Paper
    {
        $body = 'Transformer architectures rely on self-attention for sequence modelling.';

        $paper = Paper::create([
            'title' => 'Readable paper',
            'user_id' => $user->id,
            'file_path' => 'papers/x.pdf',
            'full_text' => $indexed ? $body : null,
            'index_status' => $indexed ? 'indexed' : null,
            'indexed_at' => $indexed ? now() : null,
        ]);

        if ($indexed) {
            PaperChunk::create([
                'paper_id' => $paper->id,
                'chunk_index' => 0,
                'content' => $body,
                'embedding' => app(EmbeddingService::class)->embed($body),
            ]);
        }

        return $paper;
    }

    public function test_the_reader_offers_highlights_and_ai_as_tabs(): void
    {
        $this->configureAi();
        $user = User::factory()->create();
        $paper = $this->readablePaper($user);

        $this->actingAs($user)->get(route('papers.read', $paper))
            ->assertOk()
            ->assertSee('Highlights')
            ->assertSee('AI assistant')
            // The highlight machinery is still present, not replaced.
            ->assertSee('highlights-list', false)
            ->assertSee('Select text on the PDF');
    }

    public function test_the_reader_offers_a_summary_and_a_question_box(): void
    {
        $this->configureAi();
        $user = User::factory()->create();
        $paper = $this->readablePaper($user);

        $this->actingAs($user)->get(route('papers.read', $paper))
            ->assertOk()
            ->assertSee('Generate summary')
            ->assertSee('Ask about this paper')
            ->assertSee('aiSummary(', false)
            ->assertSee('aiChat(', false);
    }

    /** One conversation per paper, shared between the reader and the detail page. */
    public function test_the_reader_shows_the_same_conversation_as_the_detail_page(): void
    {
        $this->configureAi();
        $user = User::factory()->create();
        $paper = $this->readablePaper($user);

        $session = ChatSession::create([
            'user_id' => $user->id,
            'scope' => 'paper',
            'paper_id' => $paper->id,
        ]);
        ChatMessage::create([
            'chat_session_id' => $session->id,
            'role' => 'user',
            'content' => 'Asked while reading',
        ]);

        $this->actingAs($user)->get(route('papers.read', $paper))
            ->assertOk()
            ->assertSee('Asked while reading', false);

        $this->actingAs($user)->get(route('papers.show', $paper))
            ->assertOk()
            ->assertSee('Asked while reading', false);
    }

    /**
     * A scanned PDF can still be highlighted and annotated, so the reader must
     * stay usable and say why the assistant cannot help.
     */
    public function test_an_unindexed_paper_explains_itself_without_breaking_the_reader(): void
    {
        $this->configureAi();
        $user = User::factory()->create();
        $paper = $this->readablePaper($user, indexed: false);

        $response = $this->actingAs($user)->get(route('papers.read', $paper))->assertOk();

        $response->assertSee('not indexed yet');
        $response->assertSee('Index it now');
        // Highlighting is unaffected.
        $response->assertSee('Select text on the PDF');
        $response->assertDontSee('Generate summary');
    }

    public function test_the_reader_says_when_ai_is_unconfigured(): void
    {
        config(['services.ai.provider' => 'groq', 'services.groq.key' => null]);

        $user = User::factory()->create();
        $paper = $this->readablePaper($user);

        $this->actingAs($user)->get(route('papers.read', $paper))
            ->assertOk()
            ->assertSee('Add an API key')
            // and the reader itself still works
            ->assertSee('Select text on the PDF');
    }

    public function test_another_user_cannot_open_the_reader(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $paper = $this->readablePaper($owner);

        $this->actingAs($intruder)->get(route('papers.read', $paper))->assertForbidden();
    }

    public function test_a_paper_without_a_pdf_is_redirected_out_of_the_reader(): void
    {
        $user = User::factory()->create();
        $paper = Paper::create(['title' => 'No file', 'user_id' => $user->id]);

        $this->actingAs($user)->get(route('papers.read', $paper))
            ->assertRedirect(route('papers.show', $paper));
    }
}

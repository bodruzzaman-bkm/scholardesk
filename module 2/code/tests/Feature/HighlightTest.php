<?php

namespace Tests\Feature;

use App\Models\Highlight;
use App\Models\Paper;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PDF highlights.
 *
 * The payloads here are copied from what resources/views/papers/read.blade.php
 * actually sends. An earlier version of the validation invented a different
 * rect schema ({x,y,w,h}) from the one the reader uses
 * ({left,top,width,height}), which rejected every save with a 422 - and
 * because the reader ignored the response, highlighting silently did nothing.
 */
class HighlightTest extends TestCase
{
    use RefreshDatabase;

    private function paper(User $user): Paper
    {
        return Paper::create([
            'title' => 'Readable paper',
            'user_id' => $user->id,
            'file_path' => 'papers/example.pdf',
        ]);
    }

    /** Exactly the shape produced by saveHighlight() in the reader. */
    private function readerPayload(array $overrides = []): array
    {
        return array_merge([
            'color' => '#FFEB3B',
            'text' => 'Attention mechanisms replace recurrence.',
            'note' => 'Core claim, worth citing.',
            'position' => [
                'page' => 1,
                'rects' => [
                    ['left' => 91.5, 'top' => 204.25, 'width' => 320.75, 'height' => 12.5],
                    ['left' => 91.5, 'top' => 220.0, 'width' => 180.0, 'height' => 12.5],
                ],
            ],
        ], $overrides);
    }

    public function test_a_highlight_from_the_reader_is_accepted_and_stored(): void
    {
        $user = User::factory()->create();
        $paper = $this->paper($user);

        $response = $this->actingAs($user)
            ->postJson(route('highlights.store', $paper), $this->readerPayload())
            ->assertCreated();

        $highlight = Highlight::firstOrFail();

        $this->assertSame('#FFEB3B', $highlight->color);
        $this->assertSame('Core claim, worth citing.', $highlight->note);
        $this->assertSame(1, $highlight->position['page']);
        $this->assertCount(2, $highlight->position['rects']);

        // The rect keys must survive the round trip unchanged, because the
        // reader reads rect.left / rect.top back when re-rendering.
        $this->assertSame(91.5, $highlight->position['rects'][0]['left']);
        $this->assertSame(204.25, $highlight->position['rects'][0]['top']);
        $this->assertSame(320.75, $highlight->position['rects'][0]['width']);
        $this->assertSame(12.5, $highlight->position['rects'][0]['height']);

        $response->assertJsonPath('position.rects.0.left', 91.5);
    }

    public function test_a_highlight_without_a_note_is_allowed(): void
    {
        $user = User::factory()->create();
        $paper = $this->paper($user);

        // The reader sends an empty string when the note box is left blank.
        $this->actingAs($user)
            ->postJson(route('highlights.store', $paper), $this->readerPayload(['note' => '']))
            ->assertCreated();

        $this->assertSame(1, Highlight::count());
    }

    public function test_highlights_are_listed_for_the_reader(): void
    {
        $user = User::factory()->create();
        $paper = $this->paper($user);

        $this->actingAs($user)->postJson(route('highlights.store', $paper), $this->readerPayload());

        $this->actingAs($user)->getJson(route('highlights.index', $paper))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.position.rects.0.left', 91.5);
    }

    public function test_a_margin_note_can_be_edited_after_the_fact(): void
    {
        $user = User::factory()->create();
        $paper = $this->paper($user);

        $this->actingAs($user)->postJson(route('highlights.store', $paper), $this->readerPayload());
        $highlight = Highlight::firstOrFail();

        $this->actingAs($user)
            ->patchJson(route('highlights.update', $highlight), ['note' => 'Revised thought.'])
            ->assertOk();

        $this->assertSame('Revised thought.', $highlight->fresh()->note);
    }

    public function test_a_highlight_colour_can_be_changed(): void
    {
        $user = User::factory()->create();
        $paper = $this->paper($user);

        $this->actingAs($user)->postJson(route('highlights.store', $paper), $this->readerPayload());
        $highlight = Highlight::firstOrFail();

        $this->actingAs($user)
            ->patchJson(route('highlights.update', $highlight), ['color' => '#4CAF50'])
            ->assertOk();

        $this->assertSame('#4CAF50', $highlight->fresh()->color);
    }

    public function test_a_highlight_can_be_deleted(): void
    {
        $user = User::factory()->create();
        $paper = $this->paper($user);

        $this->actingAs($user)->postJson(route('highlights.store', $paper), $this->readerPayload());
        $highlight = Highlight::firstOrFail();

        $this->actingAs($user)->deleteJson(route('highlights.destroy', $highlight))->assertOk();

        $this->assertSame(0, Highlight::count());
    }

    public function test_a_malformed_position_is_still_rejected(): void
    {
        $user = User::factory()->create();
        $paper = $this->paper($user);

        // No page number.
        $this->actingAs($user)
            ->postJson(route('highlights.store', $paper), $this->readerPayload([
                'position' => ['rects' => [['left' => 1, 'top' => 1, 'width' => 1, 'height' => 1]]],
            ]))
            ->assertStatus(422);

        // No rects at all.
        $this->actingAs($user)
            ->postJson(route('highlights.store', $paper), $this->readerPayload([
                'position' => ['page' => 1, 'rects' => []],
            ]))
            ->assertStatus(422);

        $this->assertSame(0, Highlight::count());
    }

    public function test_an_invalid_colour_is_rejected(): void
    {
        $user = User::factory()->create();
        $paper = $this->paper($user);

        $this->actingAs($user)
            ->postJson(route('highlights.store', $paper), $this->readerPayload(['color' => 'red; background:url(x)']))
            ->assertStatus(422);
    }

    /** Highlights are private to their author, even on a shared paper. */
    public function test_another_user_cannot_create_or_read_highlights(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $paper = $this->paper($owner);

        $this->actingAs($stranger)
            ->postJson(route('highlights.store', $paper), $this->readerPayload())
            ->assertForbidden();

        $this->actingAs($stranger)->getJson(route('highlights.index', $paper))->assertForbidden();
    }

    public function test_the_reader_page_loads_for_a_paper_with_a_pdf(): void
    {
        $user = User::factory()->create();
        $paper = $this->paper($user);

        $this->actingAs($user)->get(route('papers.read', $paper))
            ->assertOk()
            ->assertSee('Select text on the PDF');
    }
}

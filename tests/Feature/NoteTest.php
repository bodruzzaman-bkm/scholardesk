<?php

namespace Tests\Feature;

use App\Enums\ActivityType;
use App\Models\Note;
use App\Models\Paper;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Markdown notes attached to a paper (proposal requirement 8).
 * Create, read, edit, delete - all wired to the UI and authorised.
 */
class NoteTest extends TestCase
{
    use RefreshDatabase;

    private function paper(User $user): Paper
    {
        return Paper::create(['title' => 'Annotated paper', 'user_id' => $user->id]);
    }

    public function test_a_note_can_be_written_and_appears_on_the_paper(): void
    {
        $user = User::factory()->create();
        $paper = $this->paper($user);

        $this->actingAs($user)
            ->post(route('notes.store', $paper), ['content' => "## Key idea\n\nSelf-attention scales."])
            ->assertRedirect();

        $this->assertDatabaseHas('notes', ['paper_id' => $paper->id, 'user_id' => $user->id]);

        // Rendered as markdown on the detail page.
        $this->actingAs($user)->get(route('papers.show', $paper))
            ->assertOk()
            ->assertSee('Key idea')
            ->assertSee('<h2>', false)
            ->assertSee('Self-attention scales.');
    }

    public function test_multiple_notes_per_paper_are_supported_newest_first(): void
    {
        $user = User::factory()->create();
        $paper = $this->paper($user);

        foreach (['First note', 'Second note', 'Third note'] as $content) {
            $this->actingAs($user)->post(route('notes.store', $paper), ['content' => $content]);
        }

        $this->assertSame(3, $paper->fresh()->notes()->count());

        // notes() orders latest-first, so the most recent is at the top.
        $this->assertSame('Third note', $paper->fresh()->notes->first()->content);
    }

    public function test_an_empty_note_is_rejected(): void
    {
        $user = User::factory()->create();
        $paper = $this->paper($user);

        $this->actingAs($user)
            ->post(route('notes.store', $paper), ['content' => ''])
            ->assertSessionHasErrors('content');

        $this->assertSame(0, Note::count());
    }

    public function test_the_edit_page_loads_with_the_existing_content(): void
    {
        $user = User::factory()->create();
        $paper = $this->paper($user);
        $note = Note::create(['paper_id' => $paper->id, 'user_id' => $user->id, 'content' => 'Original text']);

        $this->actingAs($user)->get(route('notes.edit', $note))
            ->assertOk()
            ->assertSee('Original text');
    }

    public function test_a_note_can_be_edited(): void
    {
        $user = User::factory()->create();
        $paper = $this->paper($user);
        $note = Note::create(['paper_id' => $paper->id, 'user_id' => $user->id, 'content' => 'Before']);

        $this->actingAs($user)
            ->put(route('notes.update', $note), ['content' => 'After'])
            ->assertRedirect(route('papers.show', $paper->id));

        $this->assertSame('After', $note->fresh()->content);
    }

    public function test_a_note_can_be_deleted(): void
    {
        $user = User::factory()->create();
        $paper = $this->paper($user);
        $note = Note::create(['paper_id' => $paper->id, 'user_id' => $user->id, 'content' => 'Delete me']);

        $this->actingAs($user)->delete(route('notes.destroy', $note))->assertRedirect();

        $this->assertDatabaseMissing('notes', ['id' => $note->id]);
    }

    /** Notes belong to their author, not to whoever can see the paper. */
    public function test_another_user_cannot_read_edit_or_delete_a_note(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $paper = $this->paper($owner);
        $note = Note::create(['paper_id' => $paper->id, 'user_id' => $owner->id, 'content' => 'Private thought']);

        $this->actingAs($stranger)->get(route('notes.edit', $note))->assertForbidden();
        $this->actingAs($stranger)->put(route('notes.update', $note), ['content' => 'x'])->assertForbidden();
        $this->actingAs($stranger)->delete(route('notes.destroy', $note))->assertForbidden();

        $this->assertSame('Private thought', $note->fresh()->content);
    }

    public function test_writing_a_note_is_recorded_in_the_activity_feed_of_its_collections(): void
    {
        $user = User::factory()->create();
        $paper = $this->paper($user);

        $collection = \App\Models\Collection::create(['name' => 'Feed', 'user_id' => $user->id]);
        \App\Models\CollectionMember::create([
            'collection_id' => $collection->id,
            'user_id' => $user->id,
            'role' => \App\Enums\MemberRole::Owner,
        ]);
        $collection->papers()->attach($paper->id);

        $this->actingAs($user)->post(route('notes.store', $paper), ['content' => 'Shared insight']);

        $this->assertDatabaseHas('activities', [
            'collection_id' => $collection->id,
            'user_id' => $user->id,
            'type' => ActivityType::NoteAdded->value,
        ]);
    }

    public function test_a_very_long_note_is_rejected(): void
    {
        $user = User::factory()->create();
        $paper = $this->paper($user);

        $this->actingAs($user)
            ->post(route('notes.store', $paper), ['content' => str_repeat('a', 20_001)])
            ->assertSessionHasErrors('content');
    }
}

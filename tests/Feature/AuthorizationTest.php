<?php

namespace Tests\Feature;

use App\Models\Collection;
use App\Models\Highlight;
use App\Models\Note;
use App\Models\Paper;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cross-user isolation. Every one of these resources is owned by exactly one
 * user, and no route may leak or mutate another user's data.
 */
class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $intruder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->intruder = User::factory()->create();
    }

    public function test_a_user_cannot_view_edit_or_delete_another_users_paper(): void
    {
        $paper = Paper::create(['title' => 'Private', 'user_id' => $this->owner->id]);

        $this->actingAs($this->intruder)->get("/papers/{$paper->id}")->assertForbidden();
        $this->actingAs($this->intruder)->get("/papers/{$paper->id}/edit")->assertForbidden();
        $this->actingAs($this->intruder)->get("/papers/{$paper->id}/read")->assertForbidden();

        $this->actingAs($this->intruder)->put("/papers/{$paper->id}", [
            'title' => 'Hijacked',
            'reading_status' => 'read',
        ])->assertForbidden();

        $this->actingAs($this->intruder)->delete("/papers/{$paper->id}")->assertForbidden();

        $this->assertSame('Private', $paper->fresh()->title);
    }

    public function test_the_library_only_lists_papers_owned_by_the_current_user(): void
    {
        Paper::create(['title' => 'Owner Paper', 'user_id' => $this->owner->id]);
        Paper::create(['title' => 'Intruder Paper', 'user_id' => $this->intruder->id]);

        $this->actingAs($this->owner)->get('/papers')
            ->assertOk()
            ->assertSee('Owner Paper')
            ->assertDontSee('Intruder Paper');
    }

    public function test_a_user_cannot_touch_another_users_collection(): void
    {
        $collection = Collection::create(['name' => 'Private', 'user_id' => $this->owner->id]);

        $this->actingAs($this->intruder)->get("/collections/{$collection->id}")->assertForbidden();
        $this->actingAs($this->intruder)->put("/collections/{$collection->id}", ['name' => 'Hijacked'])->assertForbidden();
        $this->actingAs($this->intruder)->delete("/collections/{$collection->id}")->assertForbidden();

        $this->assertSame('Private', $collection->fresh()->name);
    }

    public function test_a_user_cannot_add_their_paper_to_another_users_collection(): void
    {
        $collection = Collection::create(['name' => 'Theirs', 'user_id' => $this->owner->id]);
        $paper = Paper::create(['title' => 'Mine', 'user_id' => $this->intruder->id]);

        $this->actingAs($this->intruder)
            ->post("/collections/{$collection->id}/papers", ['paper_id' => $paper->id])
            ->assertForbidden();

        $this->assertCount(0, $collection->fresh()->papers);
    }

    public function test_a_user_cannot_read_or_write_another_users_highlights(): void
    {
        $paper = Paper::create(['title' => 'Private', 'user_id' => $this->owner->id]);
        $highlight = Highlight::create([
            'paper_id' => $paper->id,
            'user_id' => $this->owner->id,
            'color' => '#FFEB3B',
            'text' => 'secret',
            'position' => ['page' => 1, 'rects' => [['left' => 10, 'top' => 20, 'width' => 100, 'height' => 12]]],
        ]);

        $this->actingAs($this->intruder)->getJson("/papers/{$paper->id}/highlights")->assertForbidden();

        $this->actingAs($this->intruder)->postJson("/papers/{$paper->id}/highlights", [
            'color' => '#FF0000',
            'position' => ['page' => 1, 'rects' => [['left' => 10, 'top' => 20, 'width' => 100, 'height' => 12]]],
        ])->assertForbidden();

        $this->actingAs($this->intruder)->deleteJson("/highlights/{$highlight->id}")->assertForbidden();

        $this->assertDatabaseHas('highlights', ['id' => $highlight->id]);
    }

    public function test_a_user_cannot_edit_or_delete_another_users_note(): void
    {
        $paper = Paper::create(['title' => 'Private', 'user_id' => $this->owner->id]);
        $note = Note::create([
            'paper_id' => $paper->id,
            'user_id' => $this->owner->id,
            'content' => 'my thoughts',
        ]);

        $this->actingAs($this->intruder)->get("/notes/{$note->id}/edit")->assertForbidden();
        $this->actingAs($this->intruder)->put("/notes/{$note->id}", ['content' => 'hijacked'])->assertForbidden();
        $this->actingAs($this->intruder)->delete("/notes/{$note->id}")->assertForbidden();

        $this->assertSame('my thoughts', $note->fresh()->content);
    }

    public function test_a_user_cannot_post_a_note_to_another_users_paper(): void
    {
        $paper = Paper::create(['title' => 'Private', 'user_id' => $this->owner->id]);

        $this->actingAs($this->intruder)
            ->post("/papers/{$paper->id}/notes", ['content' => 'intruding'])
            ->assertForbidden();

        $this->assertCount(0, $paper->fresh()->notes);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $paper = Paper::create(['title' => 'Private', 'user_id' => $this->owner->id]);

        $this->get('/papers')->assertRedirect('/login');
        $this->get("/papers/{$paper->id}")->assertRedirect('/login');
        $this->get('/collections')->assertRedirect('/login');
        $this->get('/tags')->assertRedirect('/login');
    }
}

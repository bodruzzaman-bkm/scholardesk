<?php

namespace Tests\Feature;

use App\Enums\MemberRole;
use App\Models\Collection;
use App\Models\CollectionMember;
use App\Models\Comment;
use App\Models\InAppNotification;
use App\Models\Paper;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CollaborationTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $editor;

    private User $viewer;

    private User $stranger;

    private Collection $collection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['name' => 'Owner']);
        $this->editor = User::factory()->create(['name' => 'Editor', 'email' => 'editor@example.com']);
        $this->viewer = User::factory()->create(['name' => 'Viewer', 'email' => 'viewer@example.com']);
        $this->stranger = User::factory()->create(['name' => 'Stranger']);

        $this->collection = Collection::create(['name' => 'Shared work', 'user_id' => $this->owner->id]);
        CollectionMember::create([
            'collection_id' => $this->collection->id,
            'user_id' => $this->owner->id,
            'role' => MemberRole::Owner,
        ]);
    }

    private function addMember(User $user, MemberRole $role): void
    {
        CollectionMember::create([
            'collection_id' => $this->collection->id,
            'user_id' => $user->id,
            'role' => $role,
        ]);
    }

    public function test_the_owner_can_invite_a_collaborator_by_email(): void
    {
        $this->actingAs($this->owner)
            ->post(route('collections.members.add', $this->collection), [
                'email' => 'editor@example.com',
                'role' => 'editor',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('collection_members', [
            'collection_id' => $this->collection->id,
            'user_id' => $this->editor->id,
            'role' => 'editor',
        ]);
    }

    public function test_inviting_someone_notifies_them(): void
    {
        $this->actingAs($this->owner)->post(route('collections.members.add', $this->collection), [
            'email' => 'editor@example.com',
            'role' => 'editor',
        ]);

        $this->assertSame(1, InAppNotification::where('user_id', $this->editor->id)->count());
    }

    public function test_inviting_an_unknown_email_is_rejected(): void
    {
        $this->actingAs($this->owner)
            ->post(route('collections.members.add', $this->collection), [
                'email' => 'nobody@example.com',
                'role' => 'editor',
            ])
            ->assertSessionHasErrors('email');
    }

    /** A viewer may read, but every mutation must be refused. */
    public function test_a_viewer_can_read_but_not_mutate(): void
    {
        $this->addMember($this->viewer, MemberRole::Viewer);
        $paper = Paper::create(['title' => 'Shared paper', 'user_id' => $this->owner->id]);

        $this->actingAs($this->viewer)->get(route('collections.show', $this->collection))->assertOk();

        $this->actingAs($this->viewer)
            ->post(route('collections.papers.add', $this->collection), ['paper_id' => $paper->id])
            ->assertForbidden();

        $this->actingAs($this->viewer)
            ->post(route('comments.store', $this->collection), ['content' => 'hello'])
            ->assertForbidden();

        $this->actingAs($this->viewer)
            ->put(route('collections.update', $this->collection), ['name' => 'Renamed'])
            ->assertForbidden();

        $this->actingAs($this->viewer)
            ->delete(route('collections.destroy', $this->collection))
            ->assertForbidden();
    }

    public function test_an_editor_can_comment_and_manage_papers_but_not_members(): void
    {
        $this->addMember($this->editor, MemberRole::Editor);

        $this->actingAs($this->editor)
            ->post(route('comments.store', $this->collection), ['content' => 'Good find'])
            ->assertRedirect();

        $this->assertDatabaseHas('comments', ['content' => 'Good find', 'user_id' => $this->editor->id]);

        // Managing members is owner-only.
        $this->actingAs($this->editor)
            ->post(route('collections.members.add', $this->collection), [
                'email' => 'viewer@example.com',
                'role' => 'viewer',
            ])
            ->assertForbidden();
    }

    public function test_a_non_member_cannot_see_the_collection_at_all(): void
    {
        $this->actingAs($this->stranger)
            ->get(route('collections.show', $this->collection))
            ->assertForbidden();
    }

    public function test_shared_collections_appear_in_the_members_index(): void
    {
        $this->addMember($this->viewer, MemberRole::Viewer);

        $this->actingAs($this->viewer)->get(route('collections.index'))
            ->assertOk()
            ->assertSee('Shared work')
            ->assertSee('Shared');

        $this->actingAs($this->stranger)->get(route('collections.index'))
            ->assertOk()
            ->assertDontSee('Shared work');
    }

    public function test_the_owner_cannot_be_removed_or_re_roled(): void
    {
        $ownerMember = CollectionMember::where('collection_id', $this->collection->id)
            ->where('user_id', $this->owner->id)
            ->firstOrFail();

        $this->actingAs($this->owner)
            ->delete(route('collections.members.remove', [$this->collection, $ownerMember]))
            ->assertSessionHasErrors();

        $this->assertDatabaseHas('collection_members', ['id' => $ownerMember->id]);
    }

    public function test_a_member_id_from_another_collection_is_rejected(): void
    {
        $other = Collection::create(['name' => 'Other', 'user_id' => $this->owner->id]);
        $foreignMember = CollectionMember::create([
            'collection_id' => $other->id,
            'user_id' => $this->viewer->id,
            'role' => MemberRole::Viewer,
        ]);

        // Route-model binding resolves the member independently of the
        // collection, so the pairing must be verified.
        $this->actingAs($this->owner)
            ->delete(route('collections.members.remove', [$this->collection, $foreignMember]))
            ->assertNotFound();
    }

    public function test_comments_thread_with_replies(): void
    {
        $this->addMember($this->editor, MemberRole::Editor);

        $this->actingAs($this->editor)->post(route('comments.store', $this->collection), [
            'content' => 'Top level',
        ]);

        $parent = Comment::where('content', 'Top level')->firstOrFail();

        $this->actingAs($this->owner)->post(route('comments.store', $this->collection), [
            'content' => 'A reply',
            'parent_id' => $parent->id,
        ])->assertRedirect();

        $this->assertSame(1, $parent->fresh()->replies->count());

        $this->actingAs($this->owner)->get(route('collections.show', $this->collection))
            ->assertOk()
            ->assertSee('Top level')
            ->assertSee('A reply');
    }

    public function test_a_reply_cannot_be_grafted_onto_another_collections_thread(): void
    {
        $other = Collection::create(['name' => 'Other', 'user_id' => $this->owner->id]);
        $foreignComment = Comment::create([
            'collection_id' => $other->id,
            'user_id' => $this->owner->id,
            'content' => 'Elsewhere',
        ]);

        $this->actingAs($this->owner)
            ->post(route('comments.store', $this->collection), [
                'content' => 'Sneaky',
                'parent_id' => $foreignComment->id,
            ])
            ->assertSessionHasErrors('parent_id');
    }

    public function test_only_the_author_can_edit_their_comment(): void
    {
        $this->addMember($this->editor, MemberRole::Editor);

        $comment = Comment::create([
            'collection_id' => $this->collection->id,
            'user_id' => $this->editor->id,
            'content' => 'Mine',
        ]);

        $this->actingAs($this->owner)
            ->put(route('comments.update', $comment), ['content' => 'Hijacked'])
            ->assertForbidden();

        $this->assertSame('Mine', $comment->fresh()->content);

        $this->actingAs($this->editor)
            ->put(route('comments.update', $comment), ['content' => 'Edited'])
            ->assertRedirect();

        $this->assertSame('Edited', $comment->fresh()->content);
    }

    public function test_activity_is_recorded_and_shown_in_the_feed(): void
    {
        $this->addMember($this->editor, MemberRole::Editor);
        $paper = Paper::create(['title' => 'Feed paper', 'user_id' => $this->owner->id]);

        $this->actingAs($this->owner)->post(route('collections.papers.add', $this->collection), [
            'paper_id' => $paper->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('activities', [
            'collection_id' => $this->collection->id,
            'type' => 'paper_added',
        ]);

        $this->actingAs($this->owner)->get(route('collections.show', $this->collection))
            ->assertOk()
            ->assertSee('added “Feed paper”', false);
    }
}

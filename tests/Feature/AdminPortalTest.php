<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Collection;
use App\Models\Comment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPortalTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Administrator]);
    }

    public function test_a_researcher_cannot_reach_the_admin_portal(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('admin.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.users'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.comments'))->assertForbidden();
    }

    public function test_guests_are_redirected_from_the_admin_portal(): void
    {
        $this->get(route('admin.index'))->assertRedirect('/login');
    }

    public function test_an_administrator_sees_system_statistics(): void
    {
        $admin = $this->admin();
        User::factory()->count(3)->create();

        $this->actingAs($admin)->get(route('admin.index'))
            ->assertOk()
            ->assertSee('Users')
            ->assertSee('Papers');
    }

    public function test_an_administrator_can_change_another_users_role(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();

        $this->actingAs($admin)
            ->patch(route('admin.users.role', $user), ['role' => 'administrator'])
            ->assertRedirect();

        $this->assertSame(UserRole::Administrator, $user->fresh()->role);
    }

    /** Self-demotion could lock the last administrator out of the portal. */
    public function test_an_administrator_cannot_change_their_own_role(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->patch(route('admin.users.role', $admin), ['role' => 'researcher'])
            ->assertRedirect();

        $this->assertSame(UserRole::Administrator, $admin->fresh()->role);
    }

    public function test_users_can_be_searched(): void
    {
        $admin = $this->admin();
        User::factory()->create(['name' => 'Findable Person', 'email' => 'findable@example.com']);
        User::factory()->create(['name' => 'Someone Else', 'email' => 'other@example.com']);

        $this->actingAs($admin)->get(route('admin.users', ['q' => 'Findable']))
            ->assertOk()
            ->assertSee('Findable Person')
            ->assertDontSee('Someone Else');
    }

    public function test_an_administrator_can_hide_and_restore_a_comment(): void
    {
        $admin = $this->admin();
        $author = User::factory()->create();
        $collection = Collection::create(['name' => 'C', 'user_id' => $author->id]);

        $comment = Comment::create([
            'collection_id' => $collection->id,
            'user_id' => $author->id,
            'content' => 'Something objectionable',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.comments.visibility', $comment))
            ->assertRedirect();

        $this->assertTrue($comment->fresh()->is_hidden);

        // Hiding is reversible.
        $this->actingAs($admin)->patch(route('admin.comments.visibility', $comment));
        $this->assertFalse($comment->fresh()->is_hidden);
    }

    /** A hidden comment leaves a tombstone rather than vanishing. */
    public function test_a_hidden_comment_shows_a_tombstone_not_its_text(): void
    {
        $author = User::factory()->create();
        $collection = Collection::create(['name' => 'C', 'user_id' => $author->id]);
        \App\Models\CollectionMember::create([
            'collection_id' => $collection->id,
            'user_id' => $author->id,
            'role' => \App\Enums\MemberRole::Owner,
        ]);

        Comment::create([
            'collection_id' => $collection->id,
            'user_id' => $author->id,
            'content' => 'Objectionable text here',
            'is_hidden' => true,
        ]);

        $this->actingAs($author)->get(route('collections.show', $collection))
            ->assertOk()
            ->assertDontSee('Objectionable text here')
            ->assertSee('hidden by a moderator');
    }

    public function test_the_admin_link_only_appears_for_administrators(): void
    {
        $this->actingAs($this->admin())->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('admin.index'));

        $this->actingAs(User::factory()->create())->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(route('admin.index'));
    }
}

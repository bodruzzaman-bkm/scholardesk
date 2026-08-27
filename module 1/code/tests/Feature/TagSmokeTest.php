<?php

namespace Tests\Feature;

use App\Models\Paper;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TagSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_tag_apply_it_and_filter_library(): void
    {
        $user = User::factory()->create();

        // 1. Create a tag
        $this->actingAs($user)
            ->post('/tags', ['name' => 'Machine Learning', 'color' => '#FF0000'])
            ->assertRedirect();

        $tag = Tag::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('Machine Learning', $tag->name);
        $this->assertSame('#FF0000', $tag->color);

        $tagged = Paper::create(['title' => 'Tagged Paper', 'user_id' => $user->id]);
        Paper::create(['title' => 'Untagged Paper', 'user_id' => $user->id]);

        // 2. Apply the tag through the paper update form
        $this->actingAs($user)->put("/papers/{$tagged->id}", [
            'title' => 'Tagged Paper',
            'reading_status' => 'to read',
            'tags' => [$tag->id],
        ])->assertRedirect(route('papers.show', $tagged));

        $this->assertEquals([$tag->id], $tagged->fresh()->tags->pluck('id')->all());

        // 3. Filter the library by tag
        $this->actingAs($user)->get('/papers?tag='.$tag->id)
            ->assertOk()
            ->assertSee('Tagged Paper')
            ->assertDontSee('Untagged Paper')
            ->assertSee('background-color: #FF0000', false);

        // 4. No filter shows everything
        $this->actingAs($user)->get('/papers')
            ->assertOk()
            ->assertSee('Tagged Paper')
            ->assertSee('Untagged Paper');

        // 5. Unchecking every tag clears them
        $this->actingAs($user)->put("/papers/{$tagged->id}", [
            'title' => 'Tagged Paper',
            'reading_status' => 'to read',
        ])->assertRedirect(route('papers.show', $tagged));

        $this->assertCount(0, $tagged->fresh()->tags);
    }

    /**
     * A tag id belonging to another user must never be attached. Validation
     * rejects the whole request rather than silently dropping the id, so the
     * user finds out their submission was not saved as sent.
     */
    public function test_a_tag_owned_by_another_user_cannot_be_attached(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $paper = Paper::create(['title' => 'Mine', 'user_id' => $user->id]);
        $foreignTag = Tag::create(['name' => 'Not Mine', 'color' => '#000000', 'user_id' => $other->id]);

        $this->actingAs($user)->put("/papers/{$paper->id}", [
            'title' => 'Mine',
            'reading_status' => 'to read',
            'tags' => [$foreignTag->id],
        ])->assertSessionHasErrors('tags.0');

        $this->assertCount(0, $paper->fresh()->tags);
    }

    public function test_tag_names_are_unique_per_user_but_not_across_users(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user)->post('/tags', ['name' => 'Survey', 'color' => '#FF0000'])->assertRedirect();

        // Same name again for the same user is rejected.
        $this->actingAs($user)
            ->post('/tags', ['name' => 'Survey', 'color' => '#00FF00'])
            ->assertSessionHasErrors('name');

        // A different user may use the same name.
        $this->actingAs($other)->post('/tags', ['name' => 'Survey', 'color' => '#0000FF'])->assertRedirect();

        $this->assertSame(1, Tag::where('user_id', $user->id)->count());
        $this->assertSame(1, Tag::where('user_id', $other->id)->count());
    }

    public function test_user_can_rename_and_delete_their_tag(): void
    {
        $user = User::factory()->create();
        $tag = Tag::create(['name' => 'Old', 'color' => '#FF0000', 'user_id' => $user->id]);

        $this->actingAs($user)
            ->put("/tags/{$tag->id}", ['name' => 'New', 'color' => '#00FF00'])
            ->assertRedirect();

        $this->assertSame('New', $tag->fresh()->name);

        $this->actingAs($user)->delete("/tags/{$tag->id}")->assertRedirect();
        $this->assertDatabaseMissing('tags', ['id' => $tag->id]);
    }

    public function test_user_cannot_modify_another_users_tag(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $tag = Tag::create(['name' => 'Theirs', 'color' => '#FF0000', 'user_id' => $other->id]);

        $this->actingAs($user)->put("/tags/{$tag->id}", ['name' => 'Hijacked', 'color' => '#000000'])
            ->assertForbidden();

        $this->actingAs($user)->delete("/tags/{$tag->id}")->assertForbidden();

        $this->assertSame('Theirs', $tag->fresh()->name);
    }

    public function test_edit_page_renders_tag_controls(): void
    {
        $user = User::factory()->create();
        $paper = Paper::create(['title' => 'Some Paper', 'user_id' => $user->id]);
        Tag::create(['name' => 'Survey', 'color' => '#00FF00', 'user_id' => $user->id]);

        $this->actingAs($user)->get("/papers/{$paper->id}/edit")
            ->assertOk()
            ->assertSee('Apply tags')
            ->assertSee('Create a new tag')
            ->assertSee('Survey');
    }
}

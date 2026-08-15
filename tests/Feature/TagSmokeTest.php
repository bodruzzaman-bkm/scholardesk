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
        $other = User::factory()->create();

        // 1. Create a tag
        $this->actingAs($user)
            ->post('/tags', ['name' => 'Machine Learning', 'color' => '#FF0000'])
            ->assertRedirect();

        $tag = Tag::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('Machine Learning', $tag->name);
        $this->assertSame('#FF0000', $tag->color);

        $tagged = Paper::create(['title' => 'Tagged Paper', 'user_id' => $user->id]);
        $untagged = Paper::create(['title' => 'Untagged Paper', 'user_id' => $user->id]);
        $foreignTag = Tag::create(['name' => 'Not Mine', 'color' => '#000000', 'user_id' => $other->id]);

        // 2. Apply the tag through the paper update form
        $this->actingAs($user)->put("/papers/{$tagged->id}", [
            'title' => 'Tagged Paper',
            'reading_status' => 'to read',
            'tags' => [$tag->id, $foreignTag->id], // foreign tag must be ignored
        ])->assertRedirect(route('papers.index'));

        $this->assertEquals([$tag->id], $tagged->fresh()->tags->pluck('id')->toArray());

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
        ])->assertRedirect(route('papers.index'));

        $this->assertCount(0, $tagged->fresh()->tags);
    }

    public function test_edit_page_renders_tag_controls(): void
    {
        $user = User::factory()->create();
        $paper = Paper::create(['title' => 'Some Paper', 'user_id' => $user->id]);
        Tag::create(['name' => 'Survey', 'color' => '#00FF00', 'user_id' => $user->id]);

        $this->actingAs($user)->get("/papers/{$paper->id}/edit")
            ->assertOk()
            ->assertSee('Apply Tags')
            ->assertSee('Create a New Tag')
            ->assertSee('Survey');
    }
}

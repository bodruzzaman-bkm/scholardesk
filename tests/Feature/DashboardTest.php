<?php

namespace Tests\Feature;

use App\Enums\ReadingStatus;
use App\Models\Highlight;
use App\Models\Note;
use App\Models\Paper;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_dashboard_renders_for_a_new_user_with_no_data(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertSee('No papers yet');
    }

    /**
     * Regression test: topTags used having() on a withCount subquery, which
     * SQLite rejects ("HAVING clause on a non-aggregate query"), 500-ing the
     * whole dashboard as soon as a user had a tagged paper.
     */
    public function test_the_dashboard_renders_once_the_user_has_tagged_papers(): void
    {
        $user = User::factory()->create();
        $paper = Paper::create(['title' => 'Tagged', 'user_id' => $user->id, 'year' => 2020]);
        $tag = Tag::create(['name' => 'Method', 'color' => '#123456', 'user_id' => $user->id]);
        $paper->tags()->attach($tag->id);

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertSee('Method')
            ->assertSee('Tagged');
    }

    /**
     * The card is labelled "Highlights & notes" and must count both. The
     * original controller counted highlights only.
     */
    public function test_the_highlights_and_notes_card_counts_both(): void
    {
        $user = User::factory()->create();
        $paper = Paper::create(['title' => 'P', 'user_id' => $user->id]);

        Highlight::create([
            'paper_id' => $paper->id,
            'user_id' => $user->id,
            'color' => '#FFEB3B',
            'text' => 'x',
            'position' => ['page' => 1, 'rects' => [['left' => 10, 'top' => 20, 'width' => 100, 'height' => 12]]],
        ]);
        Note::create(['paper_id' => $paper->id, 'user_id' => $user->id, 'content' => 'a note']);

        $response = $this->actingAs($user)->get('/dashboard')->assertOk();

        $stats = $response->viewData('stats');
        $this->assertSame(1, $stats['highlights']);
        $this->assertSame(1, $stats['notes']);
        // Rendered card shows the sum.
        $this->assertSame(2, $stats['highlights'] + $stats['notes']);
    }

    public function test_reading_status_breakdown_counts_each_status(): void
    {
        $user = User::factory()->create();

        Paper::create(['title' => 'A', 'user_id' => $user->id, 'reading_status' => ReadingStatus::ToRead]);
        Paper::create(['title' => 'B', 'user_id' => $user->id, 'reading_status' => ReadingStatus::Reading]);
        Paper::create(['title' => 'C', 'user_id' => $user->id, 'reading_status' => ReadingStatus::Read]);
        Paper::create(['title' => 'D', 'user_id' => $user->id, 'reading_status' => ReadingStatus::Read]);

        $breakdown = $this->actingAs($user)->get('/dashboard')->assertOk()->viewData('readingStatus');

        $this->assertSame(1, $breakdown['to read']);
        $this->assertSame(1, $breakdown['reading']);
        $this->assertSame(2, $breakdown['read']);
    }

    public function test_the_dashboard_never_shows_another_users_data(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Paper::create(['title' => 'Mine', 'user_id' => $user->id]);
        Paper::create(['title' => 'Theirs', 'user_id' => $other->id]);

        $response = $this->actingAs($user)->get('/dashboard')->assertOk();

        $this->assertSame(1, $response->viewData('stats')['papers']);
        $response->assertSee('Mine')->assertDontSee('Theirs');
    }

    public function test_continue_reading_surfaces_a_paper_in_progress(): void
    {
        $user = User::factory()->create();
        Paper::create(['title' => 'Queued up', 'user_id' => $user->id, 'reading_status' => ReadingStatus::ToRead]);
        Paper::create(['title' => 'Halfway through', 'user_id' => $user->id, 'reading_status' => ReadingStatus::Reading]);

        $continue = $this->actingAs($user)->get('/dashboard')->assertOk()->viewData('continueReading');

        $this->assertNotNull($continue);
        $this->assertSame('Halfway through', $continue->title);
    }
}

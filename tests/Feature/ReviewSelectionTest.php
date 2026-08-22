<?php

namespace Tests\Feature;

use App\Enums\MemberRole;
use App\Models\Collection;
use App\Models\CollectionMember;
use App\Models\LiteratureReview;
use App\Models\Paper;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Requirement 14: generate a literature-review draft "from selected papers or
 * a whole collection, and save it".
 */
class ReviewSelectionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Collection $collection;

    /** @var list<Paper> */
    private array $papers = [];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.ai.provider' => 'groq',
            'services.groq.key' => 'test-key',
            'services.groq.model' => 'test-model',
        ]);

        Http::fake(['api.groq.com/*' => Http::response([
            'choices' => [['message' => ['content' => '## Introduction

A drafted review.']]],
        ], 200)]);

        $this->user = User::factory()->create();
        $this->collection = Collection::create(['name' => 'Review set', 'user_id' => $this->user->id]);
        CollectionMember::create([
            'collection_id' => $this->collection->id,
            'user_id' => $this->user->id,
            'role' => MemberRole::Owner,
        ]);

        foreach (['First', 'Second', 'Third'] as $title) {
            $paper = Paper::create([
                'title' => $title,
                'user_id' => $this->user->id,
                'abstract' => "Abstract of {$title}.",
            ]);
            $this->collection->papers()->attach($paper->id);
            $this->papers[] = $paper;
        }
    }

    public function test_a_review_can_be_drafted_from_the_whole_collection(): void
    {
        $this->actingAs($this->user)
            ->postJson(route('ai.collection.review', $this->collection))
            ->assertOk()
            ->assertJsonPath('paper_count', 3);

        $review = LiteratureReview::firstOrFail();

        $this->assertCount(3, $review->paper_ids);
        $this->assertStringContainsString('A drafted review.', $review->content);
    }

    public function test_a_review_can_be_drafted_from_a_selection_of_papers(): void
    {
        $selected = [$this->papers[0]->id, $this->papers[2]->id];

        $this->actingAs($this->user)
            ->postJson(route('ai.collection.review', $this->collection), ['paper_ids' => $selected])
            ->assertOk()
            ->assertJsonPath('paper_count', 2);

        $review = LiteratureReview::firstOrFail();

        $this->assertEqualsCanonicalizing($selected, $review->paper_ids);
    }

    public function test_the_draft_is_saved_against_the_collection_and_its_author(): void
    {
        $this->actingAs($this->user)->postJson(route('ai.collection.review', $this->collection))->assertOk();

        $this->assertDatabaseHas('literature_reviews', [
            'collection_id' => $this->collection->id,
            'user_id' => $this->user->id,
        ]);
    }

    /** A crafted id must not pull an unrelated paper into the draft. */
    public function test_a_paper_outside_the_collection_is_rejected(): void
    {
        $outsider = Paper::create(['title' => 'Elsewhere', 'user_id' => $this->user->id]);

        $this->actingAs($this->user)
            ->postJson(route('ai.collection.review', $this->collection), ['paper_ids' => [$outsider->id]])
            ->assertStatus(422);

        $this->assertSame(0, LiteratureReview::count());
    }

    public function test_generating_a_review_is_recorded_in_the_activity_feed(): void
    {
        $this->actingAs($this->user)->postJson(route('ai.collection.review', $this->collection))->assertOk();

        $this->assertDatabaseHas('activities', [
            'collection_id' => $this->collection->id,
            'type' => 'review_generated',
        ]);
    }

    public function test_a_viewer_cannot_generate_a_review(): void
    {
        $viewer = User::factory()->create();
        CollectionMember::create([
            'collection_id' => $this->collection->id,
            'user_id' => $viewer->id,
            'role' => MemberRole::Viewer,
        ]);

        $this->actingAs($viewer)
            ->postJson(route('ai.collection.review', $this->collection))
            ->assertForbidden();
    }

    public function test_the_collection_page_offers_a_paper_picker_for_the_draft(): void
    {
        $this->actingAs($this->user)->get(route('collections.show', $this->collection))
            ->assertOk()
            ->assertSee('Literature review draft')
            // Each paper is selectable, defaulting to all.
            ->assertSee('Generate draft from')
            ->assertSee('First')
            ->assertSee('Third');
    }

    public function test_saved_drafts_are_listed_on_the_collection(): void
    {
        $this->actingAs($this->user)->postJson(route('ai.collection.review', $this->collection))->assertOk();

        $this->actingAs($this->user)->get(route('collections.show', $this->collection))
            ->assertOk()
            ->assertSee('A drafted review.');
    }
}

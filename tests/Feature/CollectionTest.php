<?php

namespace Tests\Feature;

use App\Models\Collection;
use App\Models\Paper;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CollectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_create_rename_and_delete_a_collection(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/collections', ['name' => 'Thesis', 'description' => 'Chapter 2'])
            ->assertRedirect();

        $collection = Collection::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('Thesis', $collection->name);

        $this->actingAs($user)
            ->put("/collections/{$collection->id}", ['name' => 'Thesis v2', 'description' => 'Chapter 3'])
            ->assertRedirect();

        $this->assertSame('Thesis v2', $collection->fresh()->name);

        $this->actingAs($user)->delete("/collections/{$collection->id}")->assertRedirect();
        $this->assertDatabaseMissing('collections', ['id' => $collection->id]);
    }

    /**
     * Regression test. CollectionController referenced `Paper` without
     * importing App\Models\Paper, so route-model binding resolved the type as
     * App\Http\Controllers\Paper and this route fatally errored on every call.
     */
    public function test_removing_a_paper_from_a_collection_works(): void
    {
        $user = User::factory()->create();
        $collection = Collection::create(['name' => 'Reading list', 'user_id' => $user->id]);
        $paper = Paper::create(['title' => 'A paper', 'user_id' => $user->id]);

        $collection->papers()->attach($paper->id);
        $this->assertCount(1, $collection->fresh()->papers);

        $this->actingAs($user)
            ->delete("/collections/{$collection->id}/papers/{$paper->id}")
            ->assertRedirect();

        $this->assertCount(0, $collection->fresh()->papers);

        // Detaching must not delete the paper itself.
        $this->assertDatabaseHas('papers', ['id' => $paper->id]);
    }

    public function test_deleting_a_collection_keeps_its_papers(): void
    {
        $user = User::factory()->create();
        $collection = Collection::create(['name' => 'Temp', 'user_id' => $user->id]);
        $paper = Paper::create(['title' => 'Survivor', 'user_id' => $user->id]);
        $collection->papers()->attach($paper->id);

        $this->actingAs($user)->delete("/collections/{$collection->id}")->assertRedirect();

        $this->assertDatabaseMissing('collections', ['id' => $collection->id]);
        $this->assertDatabaseHas('papers', ['id' => $paper->id, 'title' => 'Survivor']);
    }

    public function test_adding_the_same_paper_twice_does_not_duplicate_it(): void
    {
        $user = User::factory()->create();
        $collection = Collection::create(['name' => 'Dedup', 'user_id' => $user->id]);
        $paper = Paper::create(['title' => 'Once', 'user_id' => $user->id]);

        $this->actingAs($user)->post("/collections/{$collection->id}/papers", ['paper_id' => $paper->id]);
        $this->actingAs($user)->post("/collections/{$collection->id}/papers", ['paper_id' => $paper->id]);

        $this->assertCount(1, $collection->fresh()->papers);
    }

    public function test_collection_names_are_unique_per_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user)->post('/collections', ['name' => 'Shared name'])->assertRedirect();
        $this->actingAs($user)->post('/collections', ['name' => 'Shared name'])->assertSessionHasErrors('name');

        // Another user may reuse the name.
        $this->actingAs($other)->post('/collections', ['name' => 'Shared name'])->assertRedirect();

        $this->assertSame(1, Collection::where('user_id', $user->id)->count());
        $this->assertSame(1, Collection::where('user_id', $other->id)->count());
    }
}

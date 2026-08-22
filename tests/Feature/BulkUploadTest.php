<?php

namespace Tests\Feature;

use App\Enums\MemberRole;
use App\Models\Collection;
use App\Models\CollectionMember;
use App\Models\Paper;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BulkUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_several_pdfs_can_be_uploaded_at_once(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('papers.storeBatch'), [
            'files' => [
                UploadedFile::fake()->create('first paper.pdf', 100, 'application/pdf'),
                UploadedFile::fake()->create('second-paper.pdf', 120, 'application/pdf'),
                UploadedFile::fake()->create('third_paper.pdf', 90, 'application/pdf'),
            ],
        ])->assertRedirect(route('papers.index'));

        $this->assertSame(3, Paper::where('user_id', $user->id)->count());
    }

    /** Each paper is titled from its filename rather than "Untitled". */
    public function test_titles_are_derived_from_filenames(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('papers.storeBatch'), [
            'files' => [UploadedFile::fake()->create('attention_is-all you need.pdf', 100, 'application/pdf')],
        ])->assertRedirect();

        $this->assertSame(
            'attention is all you need',
            Paper::where('user_id', $user->id)->value('title')
        );
    }

    public function test_every_uploaded_file_is_stored(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('papers.storeBatch'), [
            'files' => [
                UploadedFile::fake()->create('a.pdf', 50, 'application/pdf'),
                UploadedFile::fake()->create('b.pdf', 50, 'application/pdf'),
            ],
        ]);

        foreach (Paper::where('user_id', $user->id)->pluck('file_path') as $path) {
            Storage::disk('public')->assertExists($path);
        }
    }

    public function test_a_non_pdf_in_the_batch_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('papers.storeBatch'), [
            'files' => [
                UploadedFile::fake()->create('ok.pdf', 50, 'application/pdf'),
                UploadedFile::fake()->create('bad.exe', 50, 'application/x-msdownload'),
            ],
        ])->assertSessionHasErrors('files.1');

        $this->assertSame(0, Paper::count());
    }

    public function test_an_empty_batch_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('papers.storeBatch'), ['files' => []])
            ->assertSessionHasErrors('files');
    }

    public function test_pdfs_can_be_uploaded_straight_into_a_collection(): void
    {
        $user = User::factory()->create();
        $collection = Collection::create(['name' => 'Reading list', 'user_id' => $user->id]);
        CollectionMember::create([
            'collection_id' => $collection->id,
            'user_id' => $user->id,
            'role' => MemberRole::Owner,
        ]);

        $this->actingAs($user)->post(route('collections.papers.upload', $collection), [
            'files' => [
                UploadedFile::fake()->create('one.pdf', 60, 'application/pdf'),
                UploadedFile::fake()->create('two.pdf', 60, 'application/pdf'),
            ],
        ])->assertRedirect();

        $this->assertSame(2, Paper::where('user_id', $user->id)->count());
        // And they were filed into the collection, not just the library.
        $this->assertSame(2, $collection->fresh()->papers()->count());
    }

    public function test_several_existing_papers_can_be_filed_at_once(): void
    {
        $user = User::factory()->create();
        $collection = Collection::create(['name' => 'Batch', 'user_id' => $user->id]);
        CollectionMember::create([
            'collection_id' => $collection->id,
            'user_id' => $user->id,
            'role' => MemberRole::Owner,
        ]);

        $ids = collect(range(1, 3))
            ->map(fn ($i) => Paper::create(['title' => "Paper {$i}", 'user_id' => $user->id])->id)
            ->all();

        $this->actingAs($user)
            ->post(route('collections.papers.add', $collection), ['paper_ids' => $ids])
            ->assertRedirect();

        $this->assertSame(3, $collection->fresh()->papers()->count());
    }

    /** A crafted id belonging to someone else must not be filed. */
    public function test_filing_rejects_papers_the_user_cannot_access(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $collection = Collection::create(['name' => 'Mine', 'user_id' => $user->id]);
        CollectionMember::create([
            'collection_id' => $collection->id,
            'user_id' => $user->id,
            'role' => MemberRole::Owner,
        ]);

        $foreign = Paper::create(['title' => 'Not mine', 'user_id' => $other->id]);

        $this->actingAs($user)
            ->post(route('collections.papers.add', $collection), ['paper_ids' => [$foreign->id]])
            ->assertSessionHasErrors('paper_id');

        $this->assertSame(0, $collection->fresh()->papers()->count());
    }
}

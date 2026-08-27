<?php

namespace Tests\Feature;

use App\Enums\ReadingStatus;
use App\Models\Paper;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaperLibraryTest extends TestCase
{
    use RefreshDatabase;

    private function paper(User $user, array $attributes = []): Paper
    {
        return Paper::create(array_merge([
            'title' => 'Untitled',
            'user_id' => $user->id,
            'reading_status' => ReadingStatus::ToRead,
        ], $attributes));
    }

    public function test_keyword_search_matches_title_authors_abstract_and_venue(): void
    {
        $user = User::factory()->create();

        $this->paper($user, ['title' => 'Attention Is All You Need']);
        $this->paper($user, ['title' => 'Other work', 'authors' => 'Ada Lovelace']);
        $this->paper($user, ['title' => 'Third', 'abstract' => 'A study of neural attention.']);
        $this->paper($user, ['title' => 'Fourth', 'venue' => 'NeurIPS']);
        $this->paper($user, ['title' => 'Unrelated zebra']);

        $this->actingAs($user)->get('/papers?q=attention')
            ->assertOk()
            ->assertSee('Attention Is All You Need')
            ->assertSee('Third')
            ->assertDontSee('Unrelated zebra');

        $this->actingAs($user)->get('/papers?q=Lovelace')
            ->assertOk()
            ->assertSee('Other work')
            ->assertDontSee('Unrelated zebra');

        $this->actingAs($user)->get('/papers?q=NeurIPS')
            ->assertOk()
            ->assertSee('Fourth');
    }

    public function test_search_treats_percent_as_a_literal_character(): void
    {
        $user = User::factory()->create();

        $this->paper($user, ['title' => 'Growth of 50% in yield']);
        $this->paper($user, ['title' => 'Nothing special']);

        // A bare "%" must not behave as a wildcard matching every paper.
        $this->actingAs($user)->get('/papers?q=%25')
            ->assertOk()
            ->assertSee('Growth of 50% in yield')
            ->assertDontSee('Nothing special');
    }

    public function test_library_can_be_filtered_by_reading_status(): void
    {
        $user = User::factory()->create();

        $this->paper($user, ['title' => 'Queued', 'reading_status' => ReadingStatus::ToRead]);
        $this->paper($user, ['title' => 'In progress', 'reading_status' => ReadingStatus::Reading]);
        $this->paper($user, ['title' => 'Finished', 'reading_status' => ReadingStatus::Read]);

        $this->actingAs($user)->get('/papers?status=reading')
            ->assertOk()
            ->assertSee('In progress')
            ->assertDontSee('Finished');
    }

    public function test_library_can_be_filtered_by_year_and_combined_with_search(): void
    {
        $user = User::factory()->create();

        $this->paper($user, ['title' => 'Alpha study', 'year' => 2020]);
        $this->paper($user, ['title' => 'Alpha review', 'year' => 2024]);
        $this->paper($user, ['title' => 'Beta study', 'year' => 2020]);

        $this->actingAs($user)->get('/papers?q=alpha&year=2020')
            ->assertOk()
            ->assertSee('Alpha study')
            ->assertDontSee('Alpha review')
            ->assertDontSee('Beta study');
    }

    public function test_library_can_be_filtered_by_tag(): void
    {
        $user = User::factory()->create();
        $tag = Tag::create(['name' => 'Method', 'color' => '#123456', 'user_id' => $user->id]);

        $tagged = $this->paper($user, ['title' => 'Has tag']);
        $this->paper($user, ['title' => 'No tag']);
        $tagged->tags()->attach($tag->id);

        $this->actingAs($user)->get('/papers?tag='.$tag->id)
            ->assertOk()
            ->assertSee('Has tag')
            ->assertDontSee('No tag');
    }

    public function test_library_is_paginated(): void
    {
        $user = User::factory()->create();

        for ($i = 1; $i <= 15; $i++) {
            $this->paper($user, ['title' => "Paper number {$i}"]);
        }

        // 12 per page, so page 2 must exist and hold the remainder.
        $this->actingAs($user)->get('/papers')->assertOk()->assertSee('page=2', false);
        $this->actingAs($user)->get('/papers?page=2')->assertOk();
    }

    public function test_reading_status_can_be_changed_from_the_detail_page(): void
    {
        $user = User::factory()->create();
        $paper = $this->paper($user, ['title' => 'Track me']);

        $this->actingAs($user)
            ->patch("/papers/{$paper->id}/status", ['reading_status' => 'read'])
            ->assertRedirect();

        $this->assertSame(ReadingStatus::Read, $paper->fresh()->reading_status);
    }

    public function test_an_invalid_reading_status_is_rejected(): void
    {
        $user = User::factory()->create();
        $paper = $this->paper($user, ['title' => 'Track me']);

        $this->actingAs($user)
            ->patch("/papers/{$paper->id}/status", ['reading_status' => 'finished-ish'])
            ->assertSessionHasErrors('reading_status');

        $this->assertSame(ReadingStatus::ToRead, $paper->fresh()->reading_status);
    }

    public function test_the_same_doi_may_exist_for_two_different_users(): void
    {
        Storage::fake('public');
        // No registry has this DOI; the paper is created without metadata.
        Http::fake(['*' => Http::response('', 404)]);

        $a = User::factory()->create();
        $b = User::factory()->create();

        $this->actingAs($a)->post('/papers', ['doi' => '10.1000/shared'])->assertRedirect();
        // Global uniqueness would have blocked this; it is scoped per user.
        $this->actingAs($b)->post('/papers', ['doi' => '10.1000/shared'])->assertRedirect();

        $this->assertSame(1, Paper::where('user_id', $a->id)->count());
        $this->assertSame(1, Paper::where('user_id', $b->id)->count());
    }

    public function test_the_same_doi_cannot_be_added_twice_by_one_user(): void
    {
        Http::fake(['*' => Http::response('', 404)]);

        $user = User::factory()->create();

        $this->actingAs($user)->post('/papers', ['doi' => '10.1000/dup'])->assertRedirect();

        // Different surface form of the same DOI must still collide.
        $this->actingAs($user)
            ->post('/papers', ['doi' => 'https://doi.org/10.1000/DUP'])
            ->assertSessionHasErrors('identifier');

        $this->assertSame(1, Paper::where('user_id', $user->id)->count());
    }

    public function test_a_paper_requires_either_a_doi_or_a_file(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/papers', [])->assertSessionHasErrors('identifier');
        $this->assertSame(0, Paper::count());
    }

    public function test_a_non_pdf_upload_is_rejected(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)->post('/papers', [
            'file' => UploadedFile::fake()->create('malware.exe', 100, 'application/x-msdownload'),
        ])->assertSessionHasErrors('file');

        $this->assertSame(0, Paper::count());
    }

    public function test_updating_a_paper_does_not_wipe_its_abstract(): void
    {
        $user = User::factory()->create();
        $paper = $this->paper($user, ['title' => 'Keep', 'abstract' => 'Important abstract text.']);

        // The edit form posts the abstract back, so it survives a save.
        $this->actingAs($user)->put("/papers/{$paper->id}", [
            'title' => 'Keep',
            'abstract' => 'Important abstract text.',
            'reading_status' => 'to read',
        ])->assertRedirect();

        $this->assertSame('Important abstract text.', $paper->fresh()->abstract);
    }

    public function test_the_edit_form_renders_the_abstract_so_it_is_not_lost(): void
    {
        $user = User::factory()->create();
        $paper = $this->paper($user, ['title' => 'X', 'abstract' => 'Recoverable abstract.']);

        $this->actingAs($user)->get("/papers/{$paper->id}/edit")
            ->assertOk()
            ->assertSee('Recoverable abstract.');
    }

    public function test_deleting_a_paper_removes_its_stored_file(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)->post('/papers', [
            'file' => UploadedFile::fake()->create('paper.pdf', 200, 'application/pdf'),
        ])->assertRedirect();

        $paper = Paper::where('user_id', $user->id)->firstOrFail();
        Storage::disk('public')->assertExists($paper->file_path);

        $this->actingAs($user)->delete("/papers/{$paper->id}")->assertRedirect();

        Storage::disk('public')->assertMissing($paper->file_path);
        $this->assertDatabaseMissing('papers', ['id' => $paper->id]);
    }
}

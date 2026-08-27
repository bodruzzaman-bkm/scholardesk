<?php

namespace Tests\Feature;

use App\Enums\Locale;
use App\Enums\MemberRole;
use App\Models\Collection;
use App\Models\CollectionMember;
use App\Models\Note;
use App\Models\Paper;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExportAndLocaleTest extends TestCase
{
    use RefreshDatabase;

    private function collectionWithPaper(User $user): Collection
    {
        $collection = Collection::create(['name' => 'Thesis chapter', 'user_id' => $user->id]);
        CollectionMember::create([
            'collection_id' => $collection->id,
            'user_id' => $user->id,
            'role' => MemberRole::Owner,
        ]);

        $paper = Paper::create([
            'title' => 'Attention Is All You Need',
            'authors' => 'Ashish Vaswani',
            'year' => 2017,
            'venue' => 'NeurIPS',
            'doi' => '10.5555/3295222',
            'abstract' => 'A new architecture based on attention.',
            'user_id' => $user->id,
        ]);

        Note::create([
            'paper_id' => $paper->id,
            'user_id' => $user->id,
            'content' => '## My take'."\n".'Self-attention replaces recurrence.',
        ]);

        $collection->papers()->attach($paper->id);

        return $collection;
    }

    /** The markdown document inside the downloaded archive. */
    private function bundleMarkdown(string $body, string $entry): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'zip');
        file_put_contents($tmp, $body);

        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($tmp) === true, 'The bundle is not a readable zip.');
        $markdown = $zip->getFromName($entry);
        $zip->close();
        unlink($tmp);

        $this->assertNotFalse($markdown, "The archive has no {$entry} entry.");

        return $markdown;
    }

    public function test_the_project_bundle_contains_metadata_notes_and_a_bibliography(): void
    {
        $user = User::factory()->create();
        $collection = $this->collectionWithPaper($user);

        $response = $this->actingAs($user)
            ->get(route('collections.bundle', $collection))
            ->assertOk()
            ->assertHeader('content-type', 'application/zip');

        // Requirement 16 wants "its papers, notes, and a formatted
        // bibliography, as a single downloadable file" - so the download is an
        // archive, and the document lives inside it.
        $body = $this->bundleMarkdown($response->streamedContent(), 'thesis-chapter.md');

        $this->assertStringContainsString('# Thesis chapter', $body);
        $this->assertStringContainsString('Attention Is All You Need', $body);
        $this->assertStringContainsString('**Authors:** Ashish Vaswani', $body);
        $this->assertStringContainsString('Self-attention replaces recurrence.', $body);
        $this->assertStringContainsString('## Bibliography', $body);
        $this->assertStringContainsString('@article{vaswani2017attention', $body);
    }

    public function test_the_bundle_is_named_after_the_collection(): void
    {
        $user = User::factory()->create();
        $collection = $this->collectionWithPaper($user);

        $this->actingAs($user)
            ->get(route('collections.bundle', $collection))
            ->assertHeader('content-disposition', 'attachment; filename=thesis-chapter.zip');
    }

    /** Notes are private to their author, even inside a shared collection. */
    public function test_the_bundle_only_includes_the_requesting_users_notes(): void
    {
        $owner = User::factory()->create();
        $collaborator = User::factory()->create();

        $collection = $this->collectionWithPaper($owner);
        CollectionMember::create([
            'collection_id' => $collection->id,
            'user_id' => $collaborator->id,
            'role' => MemberRole::Viewer,
        ]);

        $response = $this->actingAs($collaborator)->get(route('collections.bundle', $collection))->assertOk();
        $body = $this->bundleMarkdown($response->streamedContent(), 'thesis-chapter.md');

        // The owner's private note must not appear in a collaborator's export.
        $this->assertStringNotContainsString('Self-attention replaces recurrence.', $body);
        $this->assertStringContainsString('Attention Is All You Need', $body);
    }

    public function test_a_non_member_cannot_download_the_bundle(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $collection = $this->collectionWithPaper($owner);

        $this->actingAs($stranger)->get(route('collections.bundle', $collection))->assertForbidden();
    }

    // --- Locale -----------------------------------------------------------

    public function test_a_user_can_switch_the_interface_language(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('settings.locale'), ['locale' => 'bn'])
            ->assertRedirect();

        $this->assertSame(Locale::Bengali, $user->fresh()->locale);
    }

    public function test_the_saved_language_is_applied_to_the_interface(): void
    {
        $user = User::factory()->create(['locale' => Locale::Bengali]);

        // Compared against the Bengali lang file rather than an inline literal,
        // so the assertion cannot rot if the wording changes.
        $bengaliDashboard = trans('app.dashboard', [], 'bn');
        $this->assertNotSame('app.dashboard', $bengaliDashboard, 'Bengali translations are missing');

        // Navigation chrome is translated; user content is not.
        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee($bengaliDashboard);
    }

    public function test_english_is_the_default(): void
    {
        $user = User::factory()->create();

        $this->assertSame(Locale::English, $user->locale);

        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('Dashboard');
    }

    public function test_an_unsupported_locale_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('settings.locale'), ['locale' => 'fr'])
            ->assertSessionHasErrors('locale');

        $this->assertSame(Locale::English, $user->fresh()->locale);
    }

    public function test_the_settings_page_reports_ai_availability_honestly(): void
    {
        config(['services.gemini.key' => null]);
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('settings.edit'))
            ->assertOk()
            ->assertSee('Not configured')
            // and is clear about what still works without a key
            ->assertSee('Semantic search across your library');
    }
}

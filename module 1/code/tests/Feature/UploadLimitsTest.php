<?php

namespace Tests\Feature;

use App\Support\UploadLimits;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Upload ceilings.
 *
 * PHP rejects an over-sized request before Laravel's validation runs, so the
 * advertised limits must be derived from php.ini and an over-sized request
 * must still produce a readable message rather than a raw 413.
 */
class UploadLimitsTest extends TestCase
{
    use RefreshDatabase;

    public function test_limits_are_read_from_php_configuration(): void
    {
        $this->assertGreaterThan(0, UploadLimits::perFileKb());
        $this->assertGreaterThan(0, UploadLimits::perRequestKb());
        $this->assertGreaterThan(0, UploadLimits::effectiveMaxFiles());
    }

    /**
     * A single file can never exceed the whole-request ceiling, so the
     * per-file limit must be capped by post_max_size.
     */
    public function test_the_per_file_limit_never_exceeds_the_request_limit(): void
    {
        $this->assertLessThanOrEqual(UploadLimits::perRequestKb(), UploadLimits::perFileKb());
    }

    public function test_sizes_are_rendered_in_human_units(): void
    {
        $this->assertMatchesRegularExpression('/^[\d.]+ (KB|MB)$/', UploadLimits::perFileLabel());
        $this->assertMatchesRegularExpression('/^[\d.]+ (KB|MB)$/', UploadLimits::perRequestLabel());
    }

    /** The form must state the real ceilings, not hard-coded ones. */
    public function test_the_upload_form_states_the_actual_limits(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('papers.create'))
            ->assertOk()
            ->assertSee(UploadLimits::perFileLabel())
            ->assertSee(UploadLimits::perRequestLabel())
            ->assertSee('Up to '.UploadLimits::effectiveMaxFiles().' PDFs');
    }

    /**
     * Validation rejects a file above the per-file ceiling with a readable
     * message that names the limit.
     */
    public function test_a_file_over_the_per_file_limit_is_rejected_with_a_clear_message(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $oversizedKb = UploadLimits::perFileKb() + 1024;

        $this->actingAs($user)->post(route('papers.storeBatch'), [
            'files' => [UploadedFile::fake()->create('huge.pdf', $oversizedKb, 'application/pdf')],
        ])->assertSessionHasErrors('files.0');

        $errors = session('errors')->get('files.0');
        $this->assertStringContainsString(UploadLimits::perFileLabel(), $errors[0]);
    }

    public function test_too_many_files_are_rejected_with_the_real_count(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $max = UploadLimits::effectiveMaxFiles();
        $files = collect(range(1, $max + 1))
            ->map(fn ($i) => UploadedFile::fake()->create("p{$i}.pdf", 10, 'application/pdf'))
            ->all();

        $this->actingAs($user)->post(route('papers.storeBatch'), ['files' => $files])
            ->assertSessionHasErrors('files');
    }

    /**
     * Regression test for the reported 413. PHP discards the body of an
     * over-sized request, so this surfaced as an unhandled
     * PostTooLargeException stack trace instead of a usable message.
     */
    public function test_an_oversized_request_redirects_with_a_readable_error(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->from(route('papers.create'))
            ->withServerVariables(['CONTENT_LENGTH' => (string) (UploadLimits::perRequestKb() * 1024 * 10)])
            ->post(route('papers.storeBatch'), []);

        $response->assertRedirect(route('papers.create'));

        $message = session('error');
        $this->assertNotNull($message, 'No flash error was set for an over-sized upload');
        $this->assertStringContainsString('too large', strtolower($message));
        // The message must name the actual limits so the user can act on it.
        $this->assertStringContainsString(UploadLimits::perRequestLabel(), $message);
    }

    public function test_an_oversized_json_request_returns_413_json(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withServerVariables(['CONTENT_LENGTH' => (string) (UploadLimits::perRequestKb() * 1024 * 10)])
            ->postJson(route('papers.storeBatch'), [])
            ->assertStatus(413)
            ->assertJsonStructure(['error']);
    }
}

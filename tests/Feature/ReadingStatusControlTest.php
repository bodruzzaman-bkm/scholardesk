<?php

namespace Tests\Feature;

use App\Enums\ReadingStatus;
use App\Models\Paper;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Requirement 4: mark each paper "to read", "reading" or "read".
 *
 * The status used to be a read-only badge everywhere except the paper detail
 * page, and the button beside it in the library was labelled "Read" but simply
 * opened the PDF — so clicking it to mark a paper read navigated away instead.
 */
class ReadingStatusControlTest extends TestCase
{
    use RefreshDatabase;

    private function paper(User $user, array $attributes = []): Paper
    {
        return Paper::create(array_merge([
            'title' => 'Trackable paper',
            'user_id' => $user->id,
            'file_path' => 'papers/x.pdf',
            'reading_status' => ReadingStatus::ToRead,
        ], $attributes));
    }

    public function test_the_status_is_settable_from_the_library(): void
    {
        $user = User::factory()->create();
        $paper = $this->paper($user);

        $response = $this->actingAs($user)->get(route('papers.index'))->assertOk();

        // A real control, not a badge.
        $response->assertSee('name="reading_status"', false);
        $response->assertSee(route('papers.status', $paper), false);
    }

    public function test_the_status_is_settable_from_the_reader(): void
    {
        $user = User::factory()->create();
        $paper = $this->paper($user);

        $this->actingAs($user)->get(route('papers.read', $paper))
            ->assertOk()
            ->assertSee('name="reading_status"', false)
            ->assertSee(route('papers.status', $paper), false);
    }

    public function test_the_status_is_settable_from_the_detail_page(): void
    {
        $user = User::factory()->create();
        $paper = $this->paper($user);

        $this->actingAs($user)->get(route('papers.show', $paper))
            ->assertOk()
            ->assertSee('name="reading_status"', false);
    }

    /** The ambiguous "Read" button is gone. */
    public function test_the_open_pdf_button_is_not_labelled_read(): void
    {
        $user = User::factory()->create();
        $this->paper($user);

        $html = $this->actingAs($user)->get(route('papers.index'))
            ->assertOk()
            ->assertSee('Open PDF')
            ->getContent();

        // The status <option>Read</option> legitimately contains ">Read<", so
        // the check is specifically that no *link* is labelled just "Read".
        preg_match_all('#<a\b[^>]*>\s*([^<]+?)\s*</a>#', $html, $links);

        $this->assertNotContains('Read', array_map('trim', $links[1] ?? []));
    }

    public function test_every_status_can_be_selected_in_turn(): void
    {
        $user = User::factory()->create();
        $paper = $this->paper($user);

        foreach ([ReadingStatus::Reading, ReadingStatus::Read, ReadingStatus::ToRead] as $status) {
            $this->actingAs($user)
                ->patch(route('papers.status', $paper), ['reading_status' => $status->value])
                ->assertRedirect();

            $this->assertSame($status, $paper->fresh()->reading_status);
        }
    }

    public function test_the_control_offers_all_three_options(): void
    {
        $user = User::factory()->create();
        $paper = $this->paper($user);

        $response = $this->actingAs($user)->get(route('papers.index'))->assertOk();

        foreach (ReadingStatus::options() as $value => $label) {
            $response->assertSee('value="'.$value.'"', false);
            $response->assertSee($label);
        }
    }

    /** The currently-set status is preselected, not just the first option. */
    public function test_the_current_status_is_preselected(): void
    {
        $user = User::factory()->create();
        $paper = $this->paper($user, ['reading_status' => ReadingStatus::Read]);

        $html = $this->actingAs($user)->get(route('papers.index'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/value="read"\s+selected/', $html);
    }

    public function test_a_status_change_from_the_library_persists(): void
    {
        $user = User::factory()->create();
        $paper = $this->paper($user);

        $this->actingAs($user)
            ->from(route('papers.index'))
            ->patch(route('papers.status', $paper), ['reading_status' => 'read'])
            ->assertRedirect(route('papers.index'));

        $this->assertSame(ReadingStatus::Read, $paper->fresh()->reading_status);

        // And the library's status counts follow it.
        $this->actingAs($user)->get(route('papers.index'))
            ->assertOk()
            ->assertSee('Read');
    }

    public function test_another_user_cannot_change_the_status(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $paper = $this->paper($owner);

        $this->actingAs($intruder)
            ->patch(route('papers.status', $paper), ['reading_status' => 'read'])
            ->assertForbidden();

        $this->assertSame(ReadingStatus::ToRead, $paper->fresh()->reading_status);
    }

    public function test_the_status_filter_cards_reflect_a_change(): void
    {
        $user = User::factory()->create();
        $paper = $this->paper($user);

        $this->actingAs($user)->patch(route('papers.status', $paper), ['reading_status' => 'reading']);

        $counts = $this->actingAs($user)->get(route('papers.index'))->assertOk()->viewData('statusCounts');

        $this->assertSame(0, $counts['to read']);
        $this->assertSame(1, $counts['reading']);
        $this->assertSame(0, $counts['read']);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Paper;
use App\Models\User;
use App\Services\PaperService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Requirement 12 lists author among the library filters, alongside year,
 * venue, tag and reading status.
 */
class AuthorFilterTest extends TestCase
{
    use RefreshDatabase;

    private function paper(User $user, string $title, ?string $authors, array $extra = []): Paper
    {
        return Paper::create(array_merge([
            'title' => $title,
            'authors' => $authors,
            'user_id' => $user->id,
        ], $extra));
    }

    public function test_the_library_can_be_filtered_by_author(): void
    {
        $user = User::factory()->create();

        $this->paper($user, 'Transformer paper', 'Ashish Vaswani, Noam Shazeer');
        $this->paper($user, 'Unrelated paper', 'Ada Lovelace');

        $this->actingAs($user)->get('/papers?author='.urlencode('Ashish Vaswani'))
            ->assertOk()
            ->assertSee('Transformer paper')
            ->assertDontSee('Unrelated paper');
    }

    /**
     * `authors` is one comma-separated string, so a co-author listed second
     * must match just as well as the first.
     */
    public function test_it_matches_a_co_author_not_only_the_first_name(): void
    {
        $user = User::factory()->create();

        $this->paper($user, 'Co-authored', 'Ashish Vaswani, Noam Shazeer, Niki Parmar');
        $this->paper($user, 'Other', 'Ada Lovelace');

        $this->actingAs($user)->get('/papers?author='.urlencode('Niki Parmar'))
            ->assertOk()
            ->assertSee('Co-authored')
            ->assertDontSee('Other');
    }

    public function test_the_author_filter_combines_with_search_and_year(): void
    {
        $user = User::factory()->create();

        $this->paper($user, 'Alpha study', 'Ada Lovelace', ['year' => 2020]);
        $this->paper($user, 'Alpha review', 'Ada Lovelace', ['year' => 2024]);
        $this->paper($user, 'Alpha other', 'Grace Hopper', ['year' => 2020]);

        $this->actingAs($user)->get('/papers?q=alpha&author='.urlencode('Ada Lovelace').'&year=2020')
            ->assertOk()
            ->assertSee('Alpha study')
            ->assertDontSee('Alpha review')
            ->assertDontSee('Alpha other');
    }

    public function test_an_empty_author_filter_shows_everything(): void
    {
        $user = User::factory()->create();

        $this->paper($user, 'First paper', 'Ada Lovelace');
        $this->paper($user, 'Second paper', null);

        $this->actingAs($user)->get('/papers?author=')
            ->assertOk()
            ->assertSee('First paper')
            ->assertSee('Second paper');
    }

    public function test_the_filter_dropdown_lists_individual_author_names(): void
    {
        $user = User::factory()->create();

        $this->paper($user, 'A', 'Ashish Vaswani, Noam Shazeer');
        $this->paper($user, 'B', 'Ada Lovelace');

        $authors = app(PaperService::class)->distinctAuthors($user->id);

        // Split out of the comma-separated column, de-duplicated and sorted.
        $this->assertContains('Ashish Vaswani', $authors);
        $this->assertContains('Noam Shazeer', $authors);
        $this->assertContains('Ada Lovelace', $authors);
    }

    public function test_the_dropdown_drops_the_et_al_tail(): void
    {
        $user = User::factory()->create();
        $this->paper($user, 'A', 'Vaswani et al.');

        $authors = app(PaperService::class)->distinctAuthors($user->id);

        $this->assertContains('Vaswani', $authors);
        $this->assertNotContains('et al.', $authors);
    }

    public function test_a_repeated_author_appears_once(): void
    {
        $user = User::factory()->create();

        $this->paper($user, 'A', 'Ada Lovelace');
        $this->paper($user, 'B', 'Ada Lovelace, Grace Hopper');

        $authors = app(PaperService::class)->distinctAuthors($user->id);

        $this->assertSame(['Ada Lovelace', 'Grace Hopper'], $authors);
    }

    public function test_the_dropdown_only_lists_the_current_users_authors(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->paper($user, 'Mine', 'Ada Lovelace');
        $this->paper($other, 'Theirs', 'Somebody Else');

        $authors = app(PaperService::class)->distinctAuthors($user->id);

        $this->assertSame(['Ada Lovelace'], $authors);
    }

    public function test_the_author_filter_is_rendered_on_the_library_and_search_pages(): void
    {
        $user = User::factory()->create();
        $this->paper($user, 'A', 'Ada Lovelace');

        $this->actingAs($user)->get('/papers')
            ->assertOk()
            ->assertSee('Any author')
            ->assertSee('Ada Lovelace');

        $this->actingAs($user)->get(route('search'))
            ->assertOk()
            ->assertSee('Any author');
    }
}

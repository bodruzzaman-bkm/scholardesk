<?php

namespace Tests\Feature;

use App\Enums\PaperSource;
use App\Models\Paper;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Requirement 2: add a paper by pasting a DOI *or an article URL*.
 * Requirement 3: title, authors, year, venue and abstract are fetched from it.
 */
class ImportByUrlTest extends TestCase
{
    use RefreshDatabase;

    private function crossrefBody(array $overrides = []): array
    {
        return ['message' => array_merge([
            'title' => ['Attention Is All You Need'],
            'author' => [
                ['given' => 'Ashish', 'family' => 'Vaswani'],
                ['given' => 'Noam', 'family' => 'Shazeer'],
            ],
            'container-title' => ['NeurIPS'],
            'published-print' => ['date-parts' => [[2017]]],
            'abstract' => '<jats:p>We propose the Transformer.</jats:p>',
            'DOI' => '10.5555/3295222',
            'URL' => 'https://doi.org/10.5555/3295222',
        ], $overrides)];
    }

    public function test_a_doi_populates_every_metadata_field(): void
    {
        Http::fake(['api.crossref.org/*' => Http::response($this->crossrefBody(), 200)]);

        $user = User::factory()->create();

        $this->actingAs($user)->post('/papers', ['identifier' => '10.5555/3295222'])->assertRedirect();

        $paper = Paper::firstOrFail();

        $this->assertSame('Attention Is All You Need', $paper->title);
        $this->assertSame('Ashish Vaswani, Noam Shazeer', $paper->authors);
        $this->assertSame(2017, $paper->year);
        $this->assertSame('NeurIPS', $paper->venue);
        // JATS markup is stripped from the abstract.
        $this->assertSame('We propose the Transformer.', $paper->abstract);
        $this->assertSame(PaperSource::Doi, $paper->source);
    }

    /** A publisher URL with the DOI in its path resolves through the registry. */
    public function test_a_publisher_url_containing_a_doi_is_resolved(): void
    {
        Http::fake(['api.crossref.org/*' => Http::response($this->crossrefBody(), 200)]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/papers', ['identifier' => 'https://dl.acm.org/doi/10.5555/3295222'])
            ->assertRedirect();

        $paper = Paper::firstOrFail();

        $this->assertSame('Attention Is All You Need', $paper->title);
        $this->assertSame('10.5555/3295222', $paper->doi);
        // The pasted link is kept so the detail page can link back.
        $this->assertSame('https://dl.acm.org/doi/10.5555/3295222', $paper->url);
        $this->assertSame(PaperSource::Url, $paper->source);
    }

    public function test_an_arxiv_link_is_resolved_through_the_arxiv_api(): void
    {
        $atom = <<<'XML'
        <feed><entry>
          <title>Attention Is All You Need</title>
          <author><name>Ashish Vaswani</name></author>
          <author><name>Noam Shazeer</name></author>
          <published>2017-06-12T00:00:00Z</published>
          <summary>We propose a new simple network architecture.</summary>
        </entry></feed>
        XML;

        Http::fake([
            'export.arxiv.org/*' => Http::response($atom, 200),
            // The fallback path would otherwise fetch the arxiv.org page itself.
            'arxiv.org/*' => Http::response('', 404),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/papers', ['identifier' => 'https://arxiv.org/abs/1706.03762'])
            ->assertRedirect();

        $paper = Paper::firstOrFail();

        $this->assertSame('Attention Is All You Need', $paper->title);
        $this->assertSame('Ashish Vaswani, Noam Shazeer', $paper->authors);
        $this->assertSame(2017, $paper->year);
        $this->assertSame('arXiv', $paper->venue);
        // arXiv DOIs live with DataCite, but the id gives us one directly.
        $this->assertSame('10.48550/arxiv.1706.03762', $paper->doi);
    }

    /**
     * A page with no DOI anywhere is resolved from its own Google Scholar
     * citation tags - the only way to import PMID/PII-keyed articles.
     */
    public function test_a_page_without_a_doi_is_read_from_its_citation_meta_tags(): void
    {
        $html = <<<'HTML'
        <html><head>
          <meta name="citation_title" content="Learning Outcomes in Rural Schools">
          <meta name="citation_author" content="Azad Rahman">
          <meta name="citation_author" content="Tanvir Kouser Shuvo">
          <meta name="citation_journal_title" content="The Science Post">
          <meta name="citation_publication_date" content="2025/08/01">
          <meta name="citation_abstract" content="A mixed-methods study.">
        </head><body>No DOI here.</body></html>
        HTML;

        Http::fake([
            'thesciencepostjournal.com/*' => Http::response($html, 200, ['Content-Type' => 'text/html']),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/papers', ['identifier' => 'https://thesciencepostjournal.com/articles/42'])
            ->assertRedirect();

        $paper = Paper::firstOrFail();

        $this->assertSame('Learning Outcomes in Rural Schools', $paper->title);
        $this->assertSame('Azad Rahman, Tanvir Kouser Shuvo', $paper->authors);
        $this->assertSame(2025, $paper->year);
        $this->assertSame('The Science Post', $paper->venue);
        $this->assertSame('A mixed-methods study.', $paper->abstract);
        $this->assertNull($paper->doi);
        $this->assertSame(PaperSource::Url, $paper->source);
    }

    /** Crossref does not register arXiv or Zenodo DOIs; DataCite does. */
    public function test_it_falls_back_from_crossref_to_openalex(): void
    {
        Http::fake([
            'api.crossref.org/*' => Http::response([], 404),
            'api.openalex.org/*' => Http::response([
                'title' => 'A Zenodo Dataset',
                'publication_year' => 2024,
                'authorships' => [['author' => ['display_name' => 'Jane Roe']]],
                'primary_location' => ['source' => ['display_name' => 'Zenodo']],
                'abstract_inverted_index' => ['Recovered' => [0], 'prose' => [1]],
            ], 200),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)->post('/papers', ['identifier' => '10.5281/zenodo.16946409'])->assertRedirect();

        $paper = Paper::firstOrFail();

        $this->assertSame('A Zenodo Dataset', $paper->title);
        $this->assertSame('Jane Roe', $paper->authors);
        // OpenAlex ships abstracts as an inverted index; it must be rebuilt.
        $this->assertSame('Recovered prose', $paper->abstract);
    }

    /**
     * A lookup miss must not block the user: the paper is still created so the
     * details can be typed in by hand.
     */
    public function test_an_unresolvable_link_still_creates_the_paper(): void
    {
        Http::fake(['*' => Http::response('', 404)]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/papers', ['identifier' => 'https://example.com/unknown-article'])
            ->assertRedirect();

        $paper = Paper::firstOrFail();

        $this->assertSame('https://example.com/unknown-article', $paper->url);
        $this->assertNotNull($paper->title);
    }

    public function test_a_malformed_link_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/papers', ['identifier' => 'http://'])
            ->assertSessionHasErrors('identifier');

        $this->assertSame(0, Paper::count());
    }

    /**
     * The URL's DOI is unknown at validation time, so the duplicate has to be
     * caught after resolution rather than crashing on the unique index.
     */
    public function test_a_url_resolving_to_an_existing_doi_is_reported_not_crashed(): void
    {
        Http::fake(['api.crossref.org/*' => Http::response($this->crossrefBody(), 200)]);

        $user = User::factory()->create();

        $this->actingAs($user)->post('/papers', ['identifier' => '10.5555/3295222'])->assertRedirect();

        $this->actingAs($user)
            ->post('/papers', ['identifier' => 'https://dl.acm.org/doi/10.5555/3295222'])
            ->assertSessionHasErrors('identifier');

        $this->assertSame(1, Paper::count());
    }

    public function test_the_add_form_offers_one_field_for_a_doi_or_a_link(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('papers.create'))
            ->assertOk()
            ->assertSee('DOI or article link')
            ->assertSee('name="identifier"', false);
    }

    public function test_a_user_supplied_title_is_not_overwritten_by_the_lookup(): void
    {
        Http::fake(['api.crossref.org/*' => Http::response($this->crossrefBody(), 200)]);

        $user = User::factory()->create();

        $this->actingAs($user)->post('/papers', [
            'identifier' => '10.5555/3295222',
            'title' => 'My own title',
        ])->assertRedirect();

        $this->assertSame('My own title', Paper::firstOrFail()->title);
    }
}

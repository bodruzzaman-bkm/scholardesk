<?php

namespace Tests\Unit;

use App\Services\MetadataService;
use Tests\TestCase;

/**
 * Parsing and identification, exercised without the network.
 */
class MetadataServiceTest extends TestCase
{
    private MetadataService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MetadataService;
    }

    public function test_it_distinguishes_a_url_from_a_doi(): void
    {
        $this->assertTrue($this->service->looksLikeUrl('https://arxiv.org/abs/1706.03762'));
        $this->assertTrue($this->service->looksLikeUrl('http://example.com/article/123'));

        $this->assertFalse($this->service->looksLikeUrl('10.1000/xyz123'));
        $this->assertFalse($this->service->looksLikeUrl('doi:10.1000/xyz123'));
        // A doi.org link is a DOI wearing a URL costume.
        $this->assertFalse($this->service->looksLikeUrl('https://doi.org/10.1000/xyz'));
        $this->assertFalse($this->service->looksLikeUrl('https://dx.doi.org/10.1000/xyz'));
    }

    public function test_it_pulls_a_doi_out_of_a_publisher_url(): void
    {
        $this->assertSame(
            '10.1016/j.compedu.2023.104765',
            $this->service->extractDoi('https://www.sciencedirect.com/science/article/pii/S0360131523000010?doi=10.1016/j.compedu.2023.104765')
        );

        $this->assertSame(
            '10.1145/3442188.3445922',
            $this->service->extractDoi('https://dl.acm.org/doi/10.1145/3442188.3445922')
        );
    }

    public function test_it_returns_null_when_there_is_no_doi(): void
    {
        $this->assertNull($this->service->extractDoi('https://example.com/some/article'));
        $this->assertNull($this->service->extractDoi('no identifier here'));
    }

    /** DOIs get pasted with punctuation, suffixes and version markers. */
    public function test_doi_candidates_strip_noise_and_offer_fallbacks(): void
    {
        $candidates = $this->service->doiCandidates('(10.1000/xyz123v2.pdf)');

        $this->assertContains('10.1000/xyz123v2', $candidates);
        $this->assertContains('10.1000/xyz123', $candidates);
    }

    public function test_doi_candidates_reject_things_that_are_not_dois(): void
    {
        $this->assertSame([], $this->service->doiCandidates('not-a-doi'));
        $this->assertSame([], $this->service->doiCandidates('11.1000/wrong-prefix'));
    }

    public function test_it_recognises_arxiv_identifiers_in_several_forms(): void
    {
        foreach ([
            'https://arxiv.org/abs/1706.03762',
            'https://arxiv.org/pdf/1706.03762',
            'https://arxiv.org/abs/1706.03762v5',
            'arxiv:1706.03762',
            '1706.03762',
            'https://doi.org/10.48550/arXiv.1706.03762',
        ] as $input) {
            $this->assertSame('1706.03762', $this->service->extractArxivId($input), "failed for: {$input}");
        }
    }

    public function test_it_recognises_the_old_style_arxiv_identifier(): void
    {
        $this->assertSame('math.GT/0309136', $this->service->extractArxivId('https://arxiv.org/abs/math.GT/0309136'));
    }

    public function test_a_non_arxiv_link_yields_no_arxiv_id(): void
    {
        $this->assertNull($this->service->extractArxivId('https://dl.acm.org/doi/10.1145/3442188.3445922'));
    }

    public function test_blank_input_resolves_to_nothing(): void
    {
        $this->assertNull($this->service->lookup(null));
        $this->assertNull($this->service->lookup(''));
        $this->assertNull($this->service->lookup('   '));
    }
}

<?php

namespace Tests\Unit;

use App\Models\Paper;
use App\Services\CitationService;
use PHPUnit\Framework\TestCase;

/**
 * CitationService is pure formatting, so these run without the database.
 */
class CitationServiceTest extends TestCase
{
    private CitationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CitationService;
    }

    private function paper(array $attributes = []): Paper
    {
        // Unsaved model: enough for a formatter that only reads attributes.
        return new Paper(array_merge([
            'title' => 'Attention Is All You Need',
            'authors' => 'Ashish Vaswani, Noam Shazeer',
            'year' => 2017,
            'venue' => 'NeurIPS',
            'doi' => '10.5555/3295222',
        ], $attributes));
    }

    public function test_bibtex_contains_the_expected_fields(): void
    {
        $bibtex = $this->service->bibtex($this->paper());

        $this->assertStringContainsString('@article{', $bibtex);
        $this->assertStringContainsString('title    = {Attention Is All You Need}', $bibtex);
        $this->assertStringContainsString('year     = {2017}', $bibtex);
        $this->assertStringContainsString('journal  = {NeurIPS}', $bibtex);
        $this->assertStringContainsString('doi      = {10.5555/3295222}', $bibtex);
        // BibTeX joins authors with " and ", not commas.
        $this->assertStringContainsString('Ashish Vaswani and Noam Shazeer', $bibtex);
        $this->assertStringEndsWith('}', trim($bibtex));
    }

    public function test_bibtex_falls_back_to_misc_without_a_venue(): void
    {
        $bibtex = $this->service->bibtex($this->paper(['venue' => null]));

        $this->assertStringContainsString('@misc{', $bibtex);
        $this->assertStringNotContainsString('journal', $bibtex);
    }

    public function test_bibtex_escapes_braces_that_would_break_the_entry(): void
    {
        $bibtex = $this->service->bibtex($this->paper(['title' => 'A {weird} title']));

        $this->assertStringContainsString('A \{weird\} title', $bibtex);
    }

    public function test_the_citation_key_is_readable_and_stable(): void
    {
        $this->assertSame('vaswani2017attention', $this->service->citationKey($this->paper()));
    }

    public function test_the_citation_key_copes_with_missing_metadata(): void
    {
        $key = $this->service->citationKey($this->paper([
            'authors' => null,
            'year' => null,
            'title' => null,
        ]));

        $this->assertSame('anonnduntitled', $key);
    }

    public function test_apa_formats_authors_as_surname_then_initials(): void
    {
        $apa = $this->service->apa($this->paper());

        $this->assertStringContainsString('Vaswani, A., & Shazeer, N.', $apa);
        $this->assertStringContainsString('(2017).', $apa);
        $this->assertStringContainsString('https://doi.org/10.5555/3295222', $apa);
    }

    public function test_apa_uses_nd_when_the_year_is_unknown(): void
    {
        $this->assertStringContainsString('(n.d.).', $this->service->apa($this->paper(['year' => null])));
    }

    public function test_apa_handles_a_single_author_without_an_ampersand(): void
    {
        $apa = $this->service->apa($this->paper(['authors' => 'Ada Lovelace']));

        $this->assertStringContainsString('Lovelace, A.', $apa);
        $this->assertStringNotContainsString('&', $apa);
    }

    /**
     * "Vaswani et al." is a collapsed author list. Naive surname parsing turns
     * it into the nonsense "al., V. E." and a citation key of "al2017…".
     */
    public function test_et_al_is_treated_as_a_collapsed_list_not_a_surname(): void
    {
        $paper = $this->paper(['authors' => 'Vaswani et al.']);

        $this->assertStringContainsString('Vaswani et al.', $this->service->apa($paper));
        $this->assertStringNotContainsString('al., V.', $this->service->apa($paper));
        $this->assertSame('vaswani2017attention', $this->service->citationKey($paper));
    }

    public function test_plain_text_includes_the_core_metadata(): void
    {
        $text = $this->service->plainText($this->paper());

        $this->assertStringContainsString('Attention Is All You Need', $text);
        $this->assertStringContainsString('NeurIPS', $text);
        $this->assertStringContainsString('doi:10.5555/3295222', $text);
    }

    public function test_an_unknown_format_falls_back_to_plain_text(): void
    {
        $this->assertSame(
            $this->service->plainText($this->paper()),
            $this->service->format($this->paper(), 'nonsense')
        );
    }

    /**
     * A percent sign opens a comment in BibTeX, so an unescaped one swallowed
     * the rest of its line — the closing brace included — and left an entry no
     * parser could read. Percentages are ordinary in paper titles.
     */
    public function test_a_percent_sign_in_a_title_is_escaped_and_does_not_comment_out_the_entry(): void
    {
        $bibtex = $this->service->bibtex($this->paper([
            'title' => 'Improving accuracy by 50% using AI',
        ]));

        $this->assertStringContainsString('50\%', $bibtex);
        $this->assertStringNotContainsString('50% using', $bibtex);

        // The field survives intact: brace, content, closing brace, comma.
        $this->assertStringContainsString('title    = {Improving accuracy by 50\% using AI},', $bibtex);
        // And the entry still terminates.
        $this->assertStringEndsWith('}', trim($bibtex));
    }

    public function test_every_latex_special_character_is_escaped(): void
    {
        $bibtex = $this->service->bibtex($this->paper([
            'title' => 'Cost & benefit: $alpha, x_i, y^2, ~approx, #tag',
        ]));

        foreach (['\&', '\$', '\_', '\textasciicircum{}', '\textasciitilde{}', '\#'] as $escaped) {
            $this->assertStringContainsString($escaped, $bibtex, "missing escape: {$escaped}");
        }
    }

    /**
     * The backslash must be escaped in the same pass as the rest, or the
     * backslash *introduced* by escaping "%" gets escaped a second time.
     */
    public function test_a_backslash_is_escaped_exactly_once(): void
    {
        $bibtex = $this->service->bibtex($this->paper([
            'title' => 'A path C:\Users and 10% more',
        ]));

        $this->assertStringContainsString('\textbackslash{}', $bibtex);
        // The percent escape must not have been re-escaped into \textbackslash{}%.
        $this->assertStringContainsString('10\% more', $bibtex);
        $this->assertStringNotContainsString('\textbackslash{}%', $bibtex);
    }

    public function test_braces_are_still_escaped(): void
    {
        $bibtex = $this->service->bibtex($this->paper(['title' => 'A {braced} title']));

        $this->assertStringContainsString('\{braced\}', $bibtex);
    }
}

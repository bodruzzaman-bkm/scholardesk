<?php

namespace Tests\Unit;

use App\Services\EmbeddingService;
use App\Services\PdfTextService;
use PHPUnit\Framework\TestCase;

/**
 * Some PDFs draw each glyph at an explicit position and never emit a space
 * character, so extraction returns run-on text like
 * "ImpactofArtificialIntelligence". Both the extractor and the embedder have
 * to cope, or most of the document's signal is lost.
 */
class RunOnTextTest extends TestCase
{
    private function clean(string $text): string
    {
        // clean() is private; exercise it through the public seam.
        $method = new \ReflectionMethod(PdfTextService::class, 'repairRunOnText');

        return $method->invoke(new PdfTextService, $text);
    }

    public function test_case_boundaries_in_a_run_on_token_become_spaces(): void
    {
        $input = 'ImpactofArtificialIntelligenceonLearningOutcomesinTheContextOfBangladesh';

        $result = $this->clean($input);

        $this->assertStringContainsString('Impactof Artificial', $result);
        $this->assertStringContainsString('Intelligenceon Learning', $result);
    }

    public function test_letter_digit_boundaries_are_split(): void
    {
        $input = 'Volume1Issue3August2025QuarterlyPublishedJournalReference0000000000';

        $result = $this->clean($input);

        $this->assertStringContainsString('Volume 1', $result);
        $this->assertStringContainsString('2025 Quarterly', $result);
    }

    /** Ordinary prose must be left completely alone. */
    public function test_normal_text_is_not_altered(): void
    {
        $input = 'This study examines the potential of AI in Bangladesh in 2025.';

        $this->assertSame($input, $this->clean($input));
    }

    public function test_short_tokens_are_never_touched(): void
    {
        // Below the run-on threshold, so no repair should be attempted even
        // though it contains a case boundary.
        $input = 'CamelCaseWord';

        $this->assertSame($input, $this->clean($input));
    }

    /**
     * The embedder must still extract signal from lowercase run-on text, which
     * has no case boundaries to split on.
     */
    public function test_the_embedder_still_matches_lowercase_run_on_text(): void
    {
        $service = new EmbeddingService;

        $runOn = $service->embed(
            'technologieshavethepotentialtobridgesocioeconomicdisparitiesbyextendingaccesstoeducation'
        );
        $query = $service->embed('technologies bridge socioeconomic disparities in education');
        $unrelated = $service->embed('medieval crop rotation and soil nutrient cycles');

        $this->assertGreaterThan(0.0, $service->similarity($runOn, $query));
        $this->assertGreaterThan(
            $service->similarity($runOn, $unrelated),
            $service->similarity($runOn, $query),
            'Run-on text should still rank the on-topic query higher'
        );
    }

    public function test_a_very_long_token_is_not_discarded_by_the_embedder(): void
    {
        $service = new EmbeddingService;

        // A single 90-character run-on "word" must produce a non-zero vector.
        $vector = $service->embed(str_repeat('reinforcementlearning', 5));

        $this->assertGreaterThan(0.0, array_sum(array_map('abs', $vector)));
    }
}

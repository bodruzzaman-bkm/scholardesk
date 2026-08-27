<?php

namespace Tests\Unit;

use App\Services\EmbeddingService;
use PHPUnit\Framework\TestCase;

class EmbeddingServiceTest extends TestCase
{
    private EmbeddingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new EmbeddingService;
    }

    public function test_an_embedding_has_the_declared_dimension(): void
    {
        $this->assertCount(EmbeddingService::DIM, $this->service->embed('machine learning for education'));
    }

    public function test_embedding_is_deterministic(): void
    {
        $text = 'transformer attention mechanisms';

        $this->assertSame($this->service->embed($text), $this->service->embed($text));
    }

    public function test_empty_text_produces_an_all_zero_vector(): void
    {
        foreach (['', '   ', '!!! ???'] as $input) {
            $vector = $this->service->embed($input);
            $this->assertCount(EmbeddingService::DIM, $vector);
            $this->assertSame(0.0, array_sum(array_map('abs', $vector)), "not zero for: '{$input}'");
        }
    }

    public function test_a_vector_is_l2_normalised(): void
    {
        $vector = $this->service->embed('neural networks improve classification accuracy');

        $norm = sqrt(array_sum(array_map(fn ($v) => $v * $v, $vector)));

        $this->assertEqualsWithDelta(1.0, $norm, 0.001);
    }

    public function test_identical_text_is_maximally_similar(): void
    {
        $a = $this->service->embed('deep learning in medical imaging');

        $this->assertEqualsWithDelta(1.0, $this->service->similarity($a, $a), 0.001);
    }

    /**
     * The core property the retrieval layer relies on: texts about the same
     * topic must score higher against each other than against unrelated text.
     */
    public function test_related_text_scores_higher_than_unrelated_text(): void
    {
        $query = $this->service->embed('machine learning models for student performance prediction');
        $related = $this->service->embed('predicting student outcomes using machine learning classifiers');
        $unrelated = $this->service->embed('medieval agricultural crop rotation in northern europe');

        $relatedScore = $this->service->similarity($query, $related);
        $unrelatedScore = $this->service->similarity($query, $unrelated);

        $this->assertGreaterThan($unrelatedScore, $relatedScore);
        $this->assertGreaterThan(0.1, $relatedScore);
    }

    public function test_character_trigrams_bridge_morphological_variants(): void
    {
        // "learning" and "learned" share no whole token, only trigrams.
        $a = $this->service->embed('learning');
        $b = $this->service->embed('learned');

        $this->assertGreaterThan(0.0, $this->service->similarity($a, $b));
    }

    public function test_similarity_with_a_zero_vector_is_zero(): void
    {
        $zero = array_fill(0, EmbeddingService::DIM, 0.0);
        $real = $this->service->embed('anything at all');

        $this->assertSame(0.0, $this->service->similarity($zero, $real));
    }

    public function test_centroid_of_one_vector_is_that_vector(): void
    {
        $vector = $this->service->embed('single chunk of text about robotics');

        $this->assertEqualsWithDelta(
            1.0,
            $this->service->similarity($vector, $this->service->centroid([$vector])),
            0.001
        );
    }

    public function test_centroid_sits_between_its_inputs(): void
    {
        $a = $this->service->embed('reinforcement learning for robot control');
        $b = $this->service->embed('robot navigation using reinforcement learning');

        $centroid = $this->service->centroid([$a, $b]);

        $this->assertGreaterThan(0.5, $this->service->similarity($centroid, $a));
        $this->assertGreaterThan(0.5, $this->service->similarity($centroid, $b));
    }

    public function test_centroid_of_nothing_is_a_zero_vector(): void
    {
        $this->assertSame(0.0, array_sum(array_map('abs', $this->service->centroid([]))));
    }
}

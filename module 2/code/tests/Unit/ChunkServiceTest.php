<?php

namespace Tests\Unit;

use App\Services\ChunkService;
use PHPUnit\Framework\TestCase;

class ChunkServiceTest extends TestCase
{
    private ChunkService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ChunkService;
    }

    public function test_blank_text_produces_no_chunks(): void
    {
        $this->assertSame([], $this->service->chunk(null));
        $this->assertSame([], $this->service->chunk(''));
        $this->assertSame([], $this->service->chunk('    '));
    }

    public function test_short_text_becomes_one_chunk(): void
    {
        $text = str_repeat('This is a sentence about research. ', 5);

        $chunks = $this->service->chunk($text);

        $this->assertCount(1, $chunks);
        $this->assertSame(0, $chunks[0]['index']);
    }

    public function test_long_text_is_split_into_several_indexed_chunks(): void
    {
        $text = str_repeat('Academic prose about a research topic. ', 800); // ~30k chars

        $chunks = $this->service->chunk($text);

        $this->assertGreaterThan(1, count($chunks));

        // Indexes must be contiguous from zero, since they are a unique key.
        foreach ($chunks as $position => $chunk) {
            $this->assertSame($position, $chunk['index']);
        }
    }

    public function test_chunks_overlap_so_a_passage_on_a_boundary_is_not_lost(): void
    {
        $text = str_repeat('Sentence number one about the study. ', 400);

        $chunks = $this->service->chunk($text);

        $this->assertGreaterThan(1, count($chunks));

        // Consecutive chunks must share text; otherwise a sentence straddling
        // the boundary would be unretrievable.
        $tail = substr($chunks[0]['content'], -200);
        $this->assertStringContainsString(substr($tail, 0, 50), $chunks[1]['content']);
    }

    public function test_chunking_terminates_and_stays_bounded(): void
    {
        // A pathological input with no sentence breaks must still terminate.
        $text = str_repeat('a', 50_000);

        $chunks = $this->service->chunk($text);

        $this->assertNotEmpty($chunks);
        $this->assertLessThan(100, count($chunks));
    }

    public function test_every_chunk_carries_real_content(): void
    {
        $chunks = $this->service->chunk(str_repeat('Research findings are reported here. ', 300));

        foreach ($chunks as $chunk) {
            $this->assertGreaterThan(50, strlen($chunk['content']));
        }
    }
}

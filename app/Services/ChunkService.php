<?php

namespace App\Services;

/**
 * Splits extracted paper text into overlapping chunks for retrieval.
 *
 * Ported from ScholarDesk's chunk.service.ts, including its character budgets:
 * ~4000 characters per chunk with 400 characters of overlap, preferring to
 * break at a sentence boundary so a retrieved chunk reads as prose rather than
 * starting mid-clause. The overlap means a passage spanning a boundary is
 * still fully present in at least one chunk.
 */
class ChunkService
{
    private const CHUNK_CHARS = 4000;

    private const OVERLAP_CHARS = 400;

    /** Chunks shorter than this are not worth embedding. */
    private const MIN_CHUNK_CHARS = 50;

    /**
     * @return list<array{index: int, content: string}>
     */
    public function chunk(?string $text): array
    {
        $text = trim((string) $text);

        if ($text === '') {
            return [];
        }

        $chunks = [];
        $length = strlen($text);
        $start = 0;
        $index = 0;

        while ($start < $length) {
            $end = $start + self::CHUNK_CHARS;

            if ($end < $length) {
                // Prefer a sentence break, but only if it does not shrink the
                // chunk to less than half its budget.
                $breakAt = strrpos(substr($text, 0, $end), '. ');
                if ($breakAt !== false && $breakAt > $start + (int) (self::CHUNK_CHARS / 2)) {
                    $end = $breakAt + 1;
                }
            }

            $content = trim(substr($text, $start, $end - $start));

            if (strlen($content) > self::MIN_CHUNK_CHARS) {
                $chunks[] = ['index' => $index++, 'content' => $content];
            }

            if ($end >= $length) {
                break;
            }

            $next = $end - self::OVERLAP_CHARS;
            // Always make forward progress, or a short chunk could loop forever.
            $start = $next > $start ? $next : $end;
        }

        return $chunks;
    }
}

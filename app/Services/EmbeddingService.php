<?php

namespace App\Services;

/**
 * Deterministic, dependency-free text embedding.
 *
 * A direct port of ScholarDesk's localEmbedding.service.ts. It hashes word
 * tokens and character trigrams into a fixed-dimension vector (the "hashing
 * trick") weighted by log term frequency, then L2-normalises. Cosine
 * similarity over these vectors captures term and sub-word overlap.
 *
 * This is NOT neural-grade semantics — it will not match a paraphrase that
 * shares no words. What it buys is that semantic search and related-papers
 * work completely offline, with no API key, no model download and no native
 * extension. If a real embedding provider is configured later, only this class
 * needs to change; the storage format (a JSON array of floats) is unchanged.
 */
class EmbeddingService
{
    /**
     * 256 dimensions. Large enough to keep hash collisions rare for a personal
     * library, small enough that comparing every chunk in PHP stays fast.
     */
    public const DIM = 256;

    /** Longer than any real word: treated as run-on text from a PDF. */
    private const MAX_TOKEN_CHARS = 40;

    /** Window size used to break run-on text into embeddable pieces. */
    private const RUN_ON_WINDOW = 12;

    /** @var list<string> */
    private const STOP_WORDS = [
        'the', 'a', 'an', 'and', 'or', 'of', 'to', 'in', 'is', 'are', 'for', 'on', 'with', 'as', 'by',
        'that', 'this', 'it', 'be', 'at', 'from', 'was', 'were', 'which', 'we', 'our', 'their', 'its',
        'has', 'have', 'had', 'can', 'not', 'but', 'also', 'these', 'those', 'such', 'than', 'into',
        'using', 'used', 'use', 'they', 'them', 'he', 'she', 'his', 'her', 'you', 'your', 'i',
    ];

    /** @var array<string, true>|null */
    private static ?array $stopIndex = null;

    /**
     * Embed a piece of text.
     *
     * @return list<float> Always exactly DIM floats; all-zero for empty input.
     */
    public function embed(string $text): array
    {
        $vector = array_fill(0, self::DIM, 0.0);
        $tokens = $this->tokenize($text);

        if ($tokens === []) {
            return $vector;
        }

        $termFrequency = array_count_values($tokens);

        foreach ($termFrequency as $term => $count) {
            $term = (string) $term;
            $weight = 1 + log($count);

            $this->addFeature($vector, 'w:'.$term, $weight);

            // Character trigrams give robustness to morphology, so that
            // "learn", "learning" and "learned" land near each other.
            $length = strlen($term);
            for ($i = 0; $i + 3 <= $length; $i++) {
                $this->addFeature($vector, 't:'.substr($term, $i, 3), 0.3 * $weight);
            }
        }

        return $this->normalize($vector);
    }

    /**
     * Cosine similarity of two unit vectors, in [-1, 1].
     *
     * Both inputs are L2-normalised by embed(), so the dot product is already
     * the cosine; the magnitudes are recomputed anyway to stay correct if a
     * caller passes a raw vector.
     *
     * @param  list<float>  $a
     * @param  list<float>  $b
     */
    public function similarity(array $a, array $b): float
    {
        $count = min(count($a), count($b));

        if ($count === 0) {
            return 0.0;
        }

        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        for ($i = 0; $i < $count; $i++) {
            $x = (float) $a[$i];
            $y = (float) $b[$i];
            $dot += $x * $y;
            $normA += $x * $x;
            $normB += $y * $y;
        }

        if ($normA <= 0.0 || $normB <= 0.0) {
            return 0.0;
        }

        $similarity = $dot / (sqrt($normA) * sqrt($normB));

        return is_finite($similarity) ? $similarity : 0.0;
    }

    /**
     * Component-wise mean of several vectors, re-normalised.
     *
     * Used to give a whole paper one vector (the centroid of its chunks) for
     * the related-papers feature.
     *
     * @param  list<list<float>>  $vectors
     * @return list<float>
     */
    public function centroid(array $vectors): array
    {
        $sum = array_fill(0, self::DIM, 0.0);
        $used = 0;

        foreach ($vectors as $vector) {
            if (! is_array($vector) || $vector === []) {
                continue;
            }

            $used++;
            for ($i = 0; $i < self::DIM; $i++) {
                $sum[$i] += (float) ($vector[$i] ?? 0.0);
            }
        }

        if ($used === 0) {
            return $sum;
        }

        return $this->normalize($sum);
    }

    /** @return list<string> */
    private function tokenize(string $text): array
    {
        $text = mb_strtolower($text);
        $text = preg_replace('/[^a-z0-9\s]/', ' ', $text) ?? '';
        $parts = preg_split('/\s+/', $text) ?: [];

        self::$stopIndex ??= array_fill_keys(self::STOP_WORDS, true);

        $tokens = [];
        foreach ($parts as $token) {
            $length = strlen($token);

            if ($length <= 1) {
                continue;
            }

            /*
             * A token this long is run-on text from a PDF that emitted no
             * space glyphs. Dropping it outright would discard most of the
             * document, so it is broken into overlapping windows instead:
             * the character trigrams below still carry the topical signal even
             * though the word boundaries are lost.
             */
            if ($length >= self::MAX_TOKEN_CHARS) {
                foreach (str_split($token, self::RUN_ON_WINDOW) as $window) {
                    if (strlen($window) > 2) {
                        $tokens[] = $window;
                    }
                }

                continue;
            }

            if (isset(self::$stopIndex[$token])) {
                continue;
            }
            // Bare numbers carry no topical signal.
            if (ctype_digit($token)) {
                continue;
            }
            $tokens[] = $token;
        }

        return $tokens;
    }

    /**
     * FNV-1a 32-bit hash, kept in 32-bit unsigned range.
     *
     * PHP integers are 64-bit, so the multiply is masked back down to 32 bits
     * to reproduce the same distribution as the JavaScript original.
     */
    private function hash(string $string): int
    {
        $hash = 0x811C9DC5;
        $length = strlen($string);

        for ($i = 0; $i < $length; $i++) {
            $hash ^= ord($string[$i]);
            $hash = ($hash * 0x01000193) & 0xFFFFFFFF;
        }

        return $hash;
    }

    /** @param list<float> $vector */
    private function addFeature(array &$vector, string $key, float $weight): void
    {
        $index = $this->hash($key) % self::DIM;
        // A signed hash keeps collisions from all pushing in one direction.
        $sign = ($this->hash($key.'#') & 1) === 1 ? 1 : -1;
        $vector[$index] += $sign * $weight;
    }

    /**
     * @param  list<float>  $vector
     * @return list<float>
     */
    private function normalize(array $vector): array
    {
        $norm = 0.0;
        foreach ($vector as $value) {
            $norm += $value * $value;
        }
        $norm = sqrt($norm);

        // Degenerate input returns all zeros rather than NaN, so a bad chunk
        // can never poison a similarity comparison.
        if (! is_finite($norm) || $norm === 0.0) {
            return array_fill(0, self::DIM, 0.0);
        }

        $result = [];
        foreach ($vector as $value) {
            $scaled = $value / $norm;
            $result[] = is_finite($scaled) ? round($scaled, 6) : 0.0;
        }

        return $result;
    }
}

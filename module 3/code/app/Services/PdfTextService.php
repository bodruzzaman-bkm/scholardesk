<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser;

/**
 * Extracts plain text from an uploaded PDF.
 *
 * Works on text-based PDFs only. Scanned/image-only PDFs yield (almost)
 * nothing — that case is reported explicitly so the UI can say "no extractable
 * text" rather than silently behaving as though the paper were empty. OCR is
 * out of scope, exactly as ScholarDesk documents in its limitations.
 */
class PdfTextService
{
    /** Below this many characters we treat extraction as having failed. */
    private const MIN_USEFUL_CHARS = 200;

    /** Guard against a pathological PDF exhausting memory. */
    private const MAX_CHARS = 600_000;

    /**
     * A whitespace-free run this long is almost certainly missing spaces —
     * the longest real English words are around 30 characters.
     */
    private const RUN_ON_TOKEN_CHARS = 40;

    /**
     * @return array{text: ?string, status: string, pages: ?int}
     *         status: indexed | no_text | error
     */
    public function extract(string $storagePath, string $disk = 'public'): array
    {
        if (! Storage::disk($disk)->exists($storagePath)) {
            return ['text' => null, 'status' => 'error', 'pages' => null];
        }

        $absolute = Storage::disk($disk)->path($storagePath);

        try {
            $parser = new Parser;
            $pdf = $parser->parseFile($absolute);
            $pages = count($pdf->getPages());
            $text = $pdf->getText();
        } catch (\Throwable $e) {
            // A malformed PDF must not take down the request that uploaded it.
            Log::warning('PDF text extraction failed', [
                'path' => $storagePath,
                'error' => $e->getMessage(),
            ]);

            return ['text' => null, 'status' => 'error', 'pages' => null];
        }

        $text = $this->clean($text);

        if (mb_strlen($text) < self::MIN_USEFUL_CHARS) {
            return ['text' => null, 'status' => 'no_text', 'pages' => $pages];
        }

        return [
            'text' => mb_substr($text, 0, self::MAX_CHARS),
            'status' => 'indexed',
            'pages' => $pages,
        ];
    }

    /**
     * Normalise extractor output: collapse whitespace, drop control characters
     * and repair the hyphenated line breaks PDFs are full of.
     */
    private function clean(string $text): string
    {
        // Join words split across a line break: "learn-\ning" -> "learning".
        $text = preg_replace('/(\w)-\s*\r?\n\s*(\w)/u', '$1$2', $text) ?? $text;
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', ' ', $text) ?? $text;
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s*\n\s*/u', "\n", $text) ?? $text;
        $text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? $text;

        $text = $this->repairRunOnText($text);

        return trim($text);
    }

    /**
     * Reinstate spaces in text extracted without them.
     *
     * Some PDFs draw every glyph at an explicit position and never emit a space
     * character, so the extractor returns
     * "ImpactofArtificialIntelligenceonLearning". The font-space threshold
     * cannot help — there is no kerning array to threshold on.
     *
     * Only tokens long enough to be obviously run-on are touched, and only at
     * boundaries that are strong signals (lower→upper case, letter↔digit,
     * after sentence punctuation). Normal prose is left exactly as it is.
     */
    private function repairRunOnText(string $text): string
    {
        return preg_replace_callback(
            '/\S{'.self::RUN_ON_TOKEN_CHARS.',}/u',
            function (array $match): string {
                $token = $match[0];

                // "wordWord" / "wordACRONYM" -> split at the case change.
                $token = preg_replace('/(\p{Ll})(\p{Lu})/u', '$1 $2', $token) ?? $token;
                // "ACRONYMWord" -> keep the acronym whole.
                $token = preg_replace('/(\p{Lu}+)(\p{Lu}\p{Ll})/u', '$1 $2', $token) ?? $token;
                // Letter/digit boundaries: "Volume1" / "2025Quarterly".
                $token = preg_replace('/(\p{L})(\d)/u', '$1 $2', $token) ?? $token;
                $token = preg_replace('/(\d)(\p{L})/u', '$1 $2', $token) ?? $token;
                // A full stop or comma glued to the next word.
                $token = preg_replace('/([.,;:])(\p{L})/u', '$1 $2', $token) ?? $token;

                return $token;
            },
            $text
        ) ?? $text;
    }
}

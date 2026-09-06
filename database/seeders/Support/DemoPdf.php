<?php

namespace Database\Seeders\Support;

/**
 * Builds a small, real PDF from plain text.
 *
 * The demo library needs actual files, not just paper rows. Without one, the
 * reader has nothing to render, so highlighting cannot be shown at all and
 * indexing reports 'no_file' — module 2 disappears from the demo. Shipping
 * real published PDFs in the repository is not an option: their licences vary
 * and most do not permit redistribution. Generating them does not have that
 * problem, and the generated document is plainly a summary sheet rather than a
 * facsimile of someone's paper.
 *
 * The output is deliberately a PDF 1.4 with uncompressed content streams and
 * the base-14 Helvetica, which needs no embedded font programme. pdf.js
 * renders it and builds a text layer from it, so selection and highlighting
 * behave exactly as they do on an uploaded paper, and smalot/pdfparser reads
 * the text back out for indexing.
 */
class DemoPdf
{
    /** US Letter, in PostScript points. */
    private const PAGE_WIDTH = 612;

    private const PAGE_HEIGHT = 792;

    private const MARGIN = 72;

    private const BODY_SIZE = 11;

    private const LEADING = 15;

    /**
     * Helvetica's average glyph is around half its point size, so this is the
     * character count that fits between the margins at BODY_SIZE. It is a
     * deliberate under-estimate: a line of narrow glyphs ending short looks
     * fine, whereas one of wide glyphs running past the margin does not.
     */
    private const WRAP_CHARS = 84;

    /**
     * Helvetica's glyphs average close to half the point size. Used only to
     * estimate how wide a drawn line ended up, for the highlight rectangles.
     */
    private const WIDTH_RATIO = 0.5;

    /**
     * Render the document, and report where every line was drawn.
     *
     * The layout comes back because the seeder needs to place highlights on
     * this text, and a highlight is stored as a rectangle. Only the code that
     * decided where a line went can say where it is, so guessing that
     * separately would drift from the drawing the moment either changed.
     *
     * Rectangles are in the reader's coordinate space, not the PDF's: CSS
     * pixels at zoom 1, measured from the top-left, which is what
     * getClientRects() reports and what renderPdfHighlights() reads back.
     *
     * @param  list<string>  $paragraphs
     * @return array{bytes: string, lines: list<array{page: int, text: string, left: float, top: float, width: float, height: float}>}
     */
    public static function render(string $title, string $byline, array $paragraphs): array
    {
        $pages = self::paginate($title, $byline, $paragraphs);

        $layout = [];

        foreach ($pages as $pageIndex => $lines) {
            foreach ($lines as $lineIndex => $line) {
                if ($line['text'] === '') {
                    continue;
                }

                // The PDF baseline for this line, mirrored into CSS's
                // top-down origin. The glyph box sits above the baseline, so
                // the top edge is one point size higher.
                $baseline = self::PAGE_HEIGHT - self::MARGIN - $lineIndex * self::LEADING;

                $layout[] = [
                    'page' => $pageIndex + 1,
                    'text' => $line['text'],
                    'left' => (float) self::MARGIN,
                    'top' => round(self::PAGE_HEIGHT - $baseline - $line['size'], 2),
                    'width' => round(strlen($line['text']) * $line['size'] * self::WIDTH_RATIO, 2),
                    'height' => round($line['size'] * 1.2, 2),
                ];
            }
        }

        return [
            'bytes' => self::assemble(array_map(self::class.'::contentStream', $pages)),
            'lines' => $layout,
        ];
    }

    /**
     * Wrap the text and break it into pages.
     *
     * @param  list<string>  $paragraphs
     * @return list<list<array{text: string, bold: bool, size: int}>>
     */
    private static function paginate(string $title, string $byline, array $paragraphs): array
    {
        $lines = [];

        foreach (self::wrap($title, 60) as $line) {
            $lines[] = ['text' => $line, 'bold' => true, 'size' => 16];
        }

        $lines[] = ['text' => '', 'bold' => false, 'size' => self::BODY_SIZE];

        foreach (self::wrap($byline, self::WRAP_CHARS) as $line) {
            $lines[] = ['text' => $line, 'bold' => false, 'size' => self::BODY_SIZE];
        }

        $lines[] = ['text' => '', 'bold' => false, 'size' => self::BODY_SIZE];

        foreach ($paragraphs as $paragraph) {
            foreach (self::wrap($paragraph, self::WRAP_CHARS) as $line) {
                $lines[] = ['text' => $line, 'bold' => false, 'size' => self::BODY_SIZE];
            }

            $lines[] = ['text' => '', 'bold' => false, 'size' => self::BODY_SIZE];
        }

        $perPage = (int) floor((self::PAGE_HEIGHT - 2 * self::MARGIN) / self::LEADING);

        // At least one page, even for empty input: a zero-page PDF is invalid.
        return array_chunk($lines, max($perPage, 1)) ?: [[]];
    }

    /**
     * @return list<string>
     */
    private static function wrap(string $text, int $width): array
    {
        // Helvetica's default encoding has no glyphs for the smart quotes and
        // dashes the summaries are written with, and an unmapped byte renders
        // as a blank. Fold them to ASCII before measuring, so the wrap width
        // matches what is actually drawn.
        $text = strtr(trim($text), [
            '—' => '-', '–' => '-', '‘' => "'", '’' => "'",
            '“' => '"', '”' => '"', '…' => '...', '×' => 'x',
            '·' => '-', '≈' => '~', '≥' => '>=', '≤' => '<=',
        ]);

        $text = preg_replace('/[^\x20-\x7E]/', '', $text) ?? '';

        if ($text === '') {
            return [''];
        }

        return explode("\n", wordwrap($text, $width, "\n", true));
    }

    /**
     * One page's drawing instructions.
     *
     * @param  list<array{text: string, bold: bool, size: int}>  $lines
     */
    private static function contentStream(array $lines): string
    {
        $y = self::PAGE_HEIGHT - self::MARGIN;
        $out = [];

        foreach ($lines as $line) {
            if ($line['text'] !== '') {
                $font = $line['bold'] ? '/F2' : '/F1';

                $out[] = sprintf(
                    'BT %s %d Tf 1 0 0 1 %d %d Tm (%s) Tj ET',
                    $font,
                    $line['size'],
                    self::MARGIN,
                    $y,
                    self::escape($line['text']),
                );
            }

            $y -= self::LEADING;
        }

        return implode("\n", $out);
    }

    /** Backslash, and both parens, are what terminate a PDF literal string. */
    private static function escape(string $text): string
    {
        return strtr($text, ['\\' => '\\\\', '(' => '\\(', ')' => '\\)']);
    }

    /**
     * Wrap the content streams in a complete PDF file.
     *
     * The cross-reference table records each object's byte offset from the
     * start of the file, so the objects are written first and measured as they
     * go rather than being assembled and located afterwards.
     *
     * @param  list<string>  $streams
     */
    private static function assemble(array $streams): string
    {
        $pageCount = count($streams);

        // Object numbering: 1 catalog, 2 pages, 3 and 4 the fonts, then the
        // pages themselves, then their content streams.
        $firstPage = 5;
        $firstStream = $firstPage + $pageCount;

        $kids = implode(' ', array_map(
            fn (int $i): string => ($firstPage + $i).' 0 R',
            range(0, $pageCount - 1),
        ));

        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            "<< /Type /Pages /Kids [{$kids}] /Count {$pageCount} >>",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
        ];

        foreach (range(0, $pageCount - 1) as $i) {
            $objects[] = sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %d %d] '
                .'/Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents %d 0 R >>',
                self::PAGE_WIDTH,
                self::PAGE_HEIGHT,
                $firstStream + $i,
            );
        }

        foreach ($streams as $stream) {
            $objects[] = sprintf(
                "<< /Length %d >>\nstream\n%s\nendstream",
                strlen($stream),
                $stream,
            );
        }

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $i => $body) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n".$body."\nendobj\n";
        }

        $startxref = strlen($pdf);
        $size = count($objects) + 1;

        // Every xref entry is exactly 20 bytes, trailing space included.
        $pdf .= "xref\n0 {$size}\n0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        $pdf .= "trailer\n<< /Size {$size} /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$startxref}\n%%EOF\n";

        return $pdf;
    }
}

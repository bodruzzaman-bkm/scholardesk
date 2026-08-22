<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Safe markdown rendering for user-written notes.
 *
 * Mirrors ScholarDesk's utils/markdown.ts: notes are markdown, rendered to
 * HTML, and must never be able to inject script.
 */
class Markdown
{
    /**
     * Render user markdown to sanitised HTML.
     *
     * - `html_input: strip` removes raw HTML tags a user pasted in.
     * - `allow_unsafe_links: false` neutralises `javascript:`, `data:` and
     *   `vbscript:` URLs, which CommonMark otherwise permits and which would
     *   be a stored-XSS vector via `[click me](javascript:...)`.
     */
    public static function render(?string $markdown): string
    {
        if (blank($markdown)) {
            return '';
        }

        return Str::markdown($markdown, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }
}

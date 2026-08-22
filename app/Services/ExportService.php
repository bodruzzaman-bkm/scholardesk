<?php

namespace App\Services;

use App\Models\Collection;
use App\Models\User;

/**
 * Bundles a whole collection into one downloadable Markdown document:
 * paper metadata, the user's own notes, a bibliography, and the most recent
 * saved literature-review draft.
 */
class ExportService
{
    public function __construct(private CitationService $citations) {}

    /**
     * @return array{content: string, filename: string}
     */
    public function collectionBundle(Collection $collection, User $user): array
    {
        $collection->load([
            // Only the requesting user's own notes are exported — notes are
            // private to their author even inside a shared collection.
            'papers' => fn ($q) => $q->with([
                'tags',
                'notes' => fn ($n) => $n->where('user_id', $user->id),
            ]),
            'reviews' => fn ($q) => $q->where('user_id', $user->id)->latest()->limit(1),
        ]);

        $lines = [
            '# '.$collection->name,
        ];

        if (filled($collection->description)) {
            $lines[] = '';
            $lines[] = $collection->description;
        }

        $lines[] = '';
        $lines[] = '_Exported '.now()->format('j F Y').' · '.$collection->papers->count().' papers_';
        $lines[] = '';
        $lines[] = '---';
        $lines[] = '';

        foreach ($collection->papers as $paper) {
            $lines[] = '## '.$paper->title;
            $lines[] = '';

            if (filled($paper->authors)) {
                $lines[] = '**Authors:** '.$paper->authors;
            }
            if ($paper->year) {
                $lines[] = '**Year:** '.$paper->year;
            }
            if (filled($paper->venue)) {
                $lines[] = '**Venue:** '.$paper->venue;
            }
            if (filled($paper->doi)) {
                $lines[] = '**DOI:** https://doi.org/'.$paper->doi;
            }
            if ($paper->tags->isNotEmpty()) {
                $lines[] = '**Tags:** '.$paper->tags->pluck('name')->implode(', ');
            }
            $lines[] = '**Reading status:** '.($paper->reading_status?->label() ?? '—');

            if (filled($paper->abstract)) {
                $lines[] = '';
                $lines[] = '### Abstract';
                $lines[] = '';
                $lines[] = $paper->abstract;
            }

            if ($paper->notes->isNotEmpty()) {
                $lines[] = '';
                $lines[] = '### My notes';
                foreach ($paper->notes as $note) {
                    $lines[] = '';
                    $lines[] = $note->content;
                }
            }

            $lines[] = '';
            $lines[] = '---';
            $lines[] = '';
        }

        $lines[] = '## Bibliography';
        $lines[] = '';
        $lines[] = '```bibtex';
        foreach ($collection->papers as $paper) {
            $lines[] = $this->citations->bibtex($paper);
            $lines[] = '';
        }
        $lines[] = '```';

        $review = $collection->reviews->first();
        if ($review !== null) {
            $lines[] = '';
            $lines[] = '---';
            $lines[] = '';
            $lines[] = '## Literature review draft';
            $lines[] = '';
            $lines[] = $review->content;
        }

        return [
            'content' => implode("\n", $lines),
            'filename' => str($collection->name)->slug()->value().'.md',
        ];
    }
}

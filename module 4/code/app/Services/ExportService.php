<?php

namespace App\Services;

use App\Models\Collection;
use App\Models\Paper;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

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

    /**
     * The whole collection as one downloadable archive (requirement 16).
     *
     * The requirement is "its papers, notes, and a formatted bibliography, as
     * a single downloadable file". A Markdown document carries the notes and
     * the bibliography but not the papers themselves, so the download is a zip:
     *
     *   <collection>.md      the existing bundle — metadata, notes, review
     *   bibliography.bib     BibTeX for every paper, importable as-is
     *   papers/*.pdf         the actual PDFs
     *
     * @return array{path: string, filename: string} path is a temporary file
     *                                               the caller must send and delete
     *
     * @throws \RuntimeException when the archive cannot be created
     */
    public function collectionArchive(Collection $collection, User $user): array
    {
        $bundle = $this->collectionBundle($collection, $user);
        $slug = str($collection->name)->slug()->value() ?: 'collection';

        $dir = storage_path('app/tmp');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        // uniqid keeps two simultaneous downloads of the same collection from
        // writing over each other's archive mid-stream.
        $path = $dir.DIRECTORY_SEPARATOR.$slug.'-'.uniqid().'.zip';

        $zip = new ZipArchive();

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the export archive.');
        }

        $zip->addFromString($bundle['filename'], $bundle['content']);

        $papers = $collection->papers()->get();

        $zip->addFromString(
            'bibliography.bib',
            $this->citations->formatMany($papers, 'bibtex')
        );

        $disk = Storage::disk('public');
        $used = [];

        foreach ($papers as $paper) {
            // A metadata-only paper — imported by DOI with no open-access PDF —
            // is normal, not an error. Skip it rather than failing the export.
            if (blank($paper->file_path) || ! $disk->exists($paper->file_path)) {
                continue;
            }

            $zip->addFile($disk->path($paper->file_path), 'papers/'.$this->pdfName($paper, $used));
        }

        $zip->close();

        return ['path' => $path, 'filename' => $slug.'.zip'];
    }

    /**
     * A readable, unique filename for a paper inside the archive.
     *
     * Two papers can share a title, and a zip with duplicate entry names loses
     * all but one of them, so a counter is appended on collision.
     *
     * @param  array<string, true>  $used  names already taken, updated in place
     */
    private function pdfName(Paper $paper, array &$used): string
    {
        $base = str($paper->title ?: 'paper')->slug()->limit(80, '')->value() ?: 'paper';
        $name = $base.'.pdf';
        $n = 2;

        while (isset($used[$name])) {
            $name = $base.'-'.$n.'.pdf';
            $n++;
        }

        $used[$name] = true;

        return $name;
    }
}

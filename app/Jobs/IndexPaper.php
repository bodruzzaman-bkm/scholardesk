<?php

namespace App\Jobs;

use App\Models\Paper;
use App\Services\IndexingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Extracts and embeds a paper's text after it is added.
 *
 * Queued so that uploading a PDF returns immediately — parsing a large PDF
 * takes seconds and must not sit in the request. With QUEUE_CONNECTION=sync
 * (the default here) it simply runs inline, which is fine for a single user.
 */
class IndexPaper implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct(public int $paperId) {}

    public function handle(IndexingService $indexer): void
    {
        $paper = Paper::find($this->paperId);

        // The paper may have been deleted between queueing and running.
        if ($paper === null) {
            return;
        }

        $indexer->index($paper);
    }
}

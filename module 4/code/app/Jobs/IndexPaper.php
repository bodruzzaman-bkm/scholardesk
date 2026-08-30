<?php

namespace App\Jobs;

use App\Enums\NotificationType;
use App\Models\Paper;
use App\Services\IndexingService;
use App\Services\NotificationService;
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

    public function handle(IndexingService $indexer, NotificationService $notifications): void
    {
        $paper = Paper::find($this->paperId);

        // The paper may have been deleted between queueing and running.
        if ($paper === null) {
            return;
        }

        $indexer->index($paper);

        $this->announce($paper->fresh(), $notifications);
    }

    /**
     * Tell the owner the paper is ready (requirement 20: "when an AI task
     * completes").
     *
     * This is the one AI task worth a notification. Summaries and Q&A are
     * synchronous — the user is watching a spinner and does not need telling
     * — but indexing runs after the response is sent, so by the time it
     * finishes they have moved on. Until it does, the paper is invisible to
     * semantic search, related papers and every Q&A route, and nothing else
     * would say so.
     *
     * A failure is reported too. Silence after an upload reads as success,
     * and the user would only discover otherwise when the assistant claimed
     * to know nothing about a paper they had just added.
     */
    private function announce(?Paper $paper, NotificationService $notifications): void
    {
        if ($paper === null || $paper->user === null) {
            return;
        }

        $indexed = $paper->isIndexed();
        $title = \Illuminate\Support\Str::limit($paper->title, 60);

        $notifications->notify(
            $paper->user,
            NotificationType::AiDone,
            $indexed
                ? sprintf('“%s” is indexed and ready to search.', $title)
                : sprintf('“%s” could not be indexed, so the assistant cannot read it yet.', $title),
            route('papers.show', $paper, absolute: false),
        );
    }
}

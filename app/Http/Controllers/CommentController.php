<?php

namespace App\Http\Controllers;

use App\Enums\ActivityType;
use App\Enums\NotificationType;
use App\Models\Collection;
use App\Models\Comment;
use App\Models\Paper;
use App\Models\User;
use App\Services\ActivityService;
use App\Services\CollectionService;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CommentController extends Controller
{
    public function __construct(
        private ActivityService $activities,
        private CollectionService $collections,
    ) {}

    public function store(Request $request, Collection $collection): RedirectResponse
    {
        $this->authorize('comment', $collection);

        $validated = $request->validate([
            'content' => ['required', 'string', 'max:5000'],
            // A reply's parent must live in this same collection, or a crafted
            // id could graft a reply onto another collection's thread.
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('comments', 'id')->where('collection_id', $collection->id),
            ],
            'paper_id' => [
                'nullable',
                'integer',
                Rule::exists('collection_paper', 'paper_id')->where('collection_id', $collection->id),
            ],
        ]);

        Comment::create([
            'collection_id' => $collection->id,
            'paper_id' => $validated['paper_id'] ?? null,
            'parent_id' => $validated['parent_id'] ?? null,
            'user_id' => $request->user()->id,
            'content' => $validated['content'],
        ]);

        $this->activities->record($collection, $request->user(), ActivityType::CommentAdded);

        $this->collections->notifyMembers(
            $collection,
            $request->user(),
            NotificationType::Comment,
            sprintf('%s commented on “%s”', $request->user()->name, $collection->name),
        );

        return back()->with('success', 'Comment posted.');
    }

    /**
     * Comment on a single paper (requirement 18).
     *
     * Separate from store() because the anchor is different: a paper thread
     * has no collection, so there is no collection membership to validate a
     * reply's parent against — the paper itself is the scope.
     */
    public function storePaper(Request $request, Paper $paper, NotificationService $notifications): RedirectResponse
    {
        $this->authorize('comment', $paper);

        $validated = $request->validate([
            'content' => ['required', 'string', 'max:5000'],
            // A reply's parent must live on this same paper, or a crafted id
            // could graft a reply onto another paper's thread.
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('comments', 'id')->where('paper_id', $paper->id),
            ],
        ]);

        Comment::create([
            'collection_id' => null,
            'paper_id' => $paper->id,
            'parent_id' => $validated['parent_id'] ?? null,
            'user_id' => $request->user()->id,
            'content' => $validated['content'],
        ]);

        /*
         | Requirement 19: the feed records "comments posted".
         |
         | A paper thread has no collection of its own, but the paper may sit
         | in several — and to the collaborators watching one of those feeds,
         | a comment on a paper inside it is exactly the event the feed exists
         | to surface. Recording it against every collection holding the paper
         | mirrors what updateStatus() does for a status change.
         */
        foreach ($paper->collections()->get() as $collection) {
            $this->activities->record($collection, $request->user(), ActivityType::CommentAdded, [
                'paper_id' => $paper->id,
                'title' => $paper->title,
            ]);
        }

        $this->notifyPaperAudience($paper, $request->user(), $notifications);

        return back()->with('success', 'Comment posted.');
    }

    /**
     * Everyone who can see this paper, minus whoever just commented.
     *
     * That is the owner plus the members of every collection holding it —
     * the same set PaperPolicy::comment() admits, so nobody is notified about
     * a discussion they cannot open.
     */
    private function notifyPaperAudience(Paper $paper, User $actor, NotificationService $notifications): void
    {
        $recipients = collect();

        if ($paper->user !== null) {
            $recipients->push($paper->user);
        }

        foreach ($paper->collections()->with('memberUsers')->get() as $collection) {
            $recipients = $recipients->concat($collection->memberUsers);
        }

        $notifications->notifyMany(
            $recipients->unique('id'),
            NotificationType::Comment,
            sprintf('%s commented on “%s”', $actor->name, $paper->title),
            route('papers.show', $paper, absolute: false),
            $actor,
        );
    }

    public function update(Request $request, Comment $comment): RedirectResponse
    {
        $this->authorize('update', $comment);

        $validated = $request->validate([
            'content' => ['required', 'string', 'max:5000'],
        ]);

        $comment->update($validated);

        return back()->with('success', 'Comment updated.');
    }

    public function destroy(Comment $comment): RedirectResponse
    {
        $this->authorize('delete', $comment);

        $comment->delete();

        return back()->with('success', 'Comment deleted.');
    }
}

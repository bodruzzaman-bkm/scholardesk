<?php

namespace App\Http\Controllers;

use App\Enums\ActivityType;
use App\Enums\NotificationType;
use App\Models\Collection;
use App\Models\Comment;
use App\Services\ActivityService;
use App\Services\CollectionService;
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

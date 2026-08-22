<?php

namespace App\Http\Controllers;

use App\Enums\ActivityType;
use App\Models\Note;
use App\Models\Paper;
use App\Services\ActivityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class NoteController extends Controller
{
    public function __construct(private ActivityService $activities) {}

    // Save a new note
    public function store(Request $request, Paper $paper): RedirectResponse
    {
        $this->authorize('annotate', $paper);

        $validated = $request->validate([
            'content' => ['required', 'string', 'max:20000'],
        ]);

        $paper->notes()->create([
            'user_id' => Auth::id(),
            'content' => $validated['content'],
        ]);

        $this->recordActivity($request, $paper);

        return back()->with('success', 'Note added.');
    }

    // Show the edit form
    public function edit(Note $note): View
    {
        $this->authorize('update', $note);

        return view('notes.edit', compact('note'));
    }

    // Update the note
    public function update(Request $request, Note $note): RedirectResponse
    {
        $this->authorize('update', $note);

        $validated = $request->validate([
            'content' => ['required', 'string', 'max:20000'],
        ]);

        $note->update($validated);

        return redirect()
            ->route('papers.show', $note->paper_id)
            ->with('success', 'Note updated.');
    }

    // Delete the note
    public function destroy(Note $note): RedirectResponse
    {
        $this->authorize('delete', $note);

        $note->delete();

        return back()->with('success', 'Note deleted.');
    }

    /**
     * Record the note in the activity feed of every collection the paper
     * belongs to, so collaborators can see that work is happening.
     *
     * The note's *content* is deliberately not included in the metadata:
     * notes are private to their author even inside a shared collection, so
     * the feed reports only that a note was written.
     */
    private function recordActivity(Request $request, Paper $paper): void
    {
        foreach ($paper->collections()->get() as $collection) {
            $this->activities->record(
                $collection,
                $request->user(),
                ActivityType::NoteAdded,
                ['paper_id' => $paper->id, 'title' => $paper->title],
            );
        }
    }
}

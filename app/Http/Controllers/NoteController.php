<?php

namespace App\Http\Controllers;

use App\Models\Note;
use App\Models\Paper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NoteController extends Controller
{
    // Save a new note
    public function store(Request $request, Paper $paper)
    {
        if ($paper->user_id !== Auth::id()) abort(403);

        $request->validate(['content' => 'required|string']);

        $paper->notes()->create([
            'user_id' => Auth::id(),
            'content' => $request->content,
        ]);

        return back()->with('success', 'Note added successfully!');
    }

    // Show the edit form
    public function edit(Note $note)
    {
        if ($note->user_id !== Auth::id()) abort(403);

        return view('notes.edit', compact('note'));
    }

    // Update the note
    public function update(Request $request, Note $note)
    {
        if ($note->user_id !== Auth::id()) abort(403);

        $request->validate(['content' => 'required|string']);

        $note->update(['content' => $request->content]);

        return redirect()
            ->route('papers.show', $note->paper_id)
            ->with('success', 'Note updated!');
    }

    // Delete the note
    public function destroy(Note $note)
    {
        if ($note->user_id !== Auth::id()) abort(403);

        $note->delete();

        return back()->with('success', 'Note deleted!');
    }
}

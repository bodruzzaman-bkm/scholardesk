<?php

namespace App\Http\Controllers;

use App\Models\Highlight;
use App\Models\Paper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * JSON endpoints driving the PDF reader's annotation layer.
 *
 * These are consumed by fetch() from resources/views/papers/read.blade.php,
 * so they return JSON rather than redirects.
 */
class HighlightController extends Controller
{
    /**
     * Every highlight the requesting user has made on this paper.
     *
     * Gated on `read`, not `annotate`. The reader page itself is open to
     * collaborators — PaperPolicy::read admits members of a collection holding
     * the paper — but this endpoint used to demand `annotate`, which is
     * owner-only. The two disagreed, so a collaborator opening a shared PDF
     * triggered a 403 that the reader surfaced as an error banner on a page
     * they were entitled to use.
     *
     * Relaxing it leaks nothing: the query is already scoped to the current
     * user, and `annotate` still keeps anyone but the owner from creating a
     * highlight in the first place, so a collaborator's list is simply empty.
     */
    public function index(Paper $paper): JsonResponse
    {
        $this->authorize('read', $paper);

        $highlights = $paper->highlights()
            ->where('user_id', Auth::id())
            ->latest()
            ->get();

        return response()->json($highlights);
    }

    // Save a new highlight
    public function store(Request $request, Paper $paper): JsonResponse
    {
        $this->authorize('annotate', $paper);

        /*
         | The rect keys are left/top/width/height because that is what
         | getClientRects() gives the reader, and what renderPdfHighlights()
         | reads back when re-drawing. They are stored unscaled (divided by the
         | zoom level at capture time) so a highlight lands correctly at any
         | later zoom. Renaming them here would silently break re-rendering.
         */
        $validated = $request->validate([
            'color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'position' => ['required', 'array'],
            'position.page' => ['required', 'integer', 'min:1'],
            'position.rects' => ['required', 'array', 'min:1', 'max:200'],
            'position.rects.*.left' => ['required', 'numeric'],
            'position.rects.*.top' => ['required', 'numeric'],
            'position.rects.*.width' => ['required', 'numeric', 'min:0'],
            'position.rects.*.height' => ['required', 'numeric', 'min:0'],
            'text' => ['nullable', 'string', 'max:5000'],
            'note' => ['nullable', 'string', 'max:5000'],
        ]);

        $highlight = $paper->highlights()->create([
            'user_id' => Auth::id(),
            'color' => $validated['color'],
            'position' => $validated['position'],
            'text' => $validated['text'] ?? null,
            'note' => $validated['note'] ?? null,
        ]);

        return response()->json($highlight, 201);
    }

    /** Edit the margin note attached to an existing highlight. */
    public function update(Request $request, Highlight $highlight): JsonResponse
    {
        $this->authorize('update', $highlight);

        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:5000'],
            'color' => ['sometimes', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $highlight->update($validated);

        return response()->json($highlight);
    }

    // Delete a highlight
    public function destroy(Highlight $highlight): JsonResponse
    {
        $this->authorize('delete', $highlight);

        $highlight->delete();

        return response()->json(['message' => 'Highlight deleted']);
    }
}

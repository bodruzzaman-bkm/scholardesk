<?php

namespace App\Http\Controllers;

use App\Models\Paper;
use App\Models\Highlight;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HighlightController extends Controller
{
    // Fetch all highlights for a specific paper
    public function index(Paper $paper)
    {
        if ($paper->user_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $highlights = $paper->highlights()->where('user_id', Auth::id())->get();
        return response()->json($highlights);
    }

    // Save a new highlight
    public function store(Request $request, Paper $paper)
    {
        if ($paper->user_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'color' => 'required|string|max:7',
            'position' => 'required|array',
            'text' => 'nullable|string',
            'note' => 'nullable|string',
        ]);

        $highlight = $paper->highlights()->create([
            'user_id' => Auth::id(),
            'color' => $request->color,
            'position' => $request->position,
            'text' => $request->text,
            'note' => $request->note,
        ]);

        return response()->json($highlight, 201);
    }

    // Delete a highlight
    public function destroy(Highlight $highlight)
    {
        if ($highlight->user_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $highlight->delete();
        return response()->json(['message' => 'Highlight deleted']);
    }
}
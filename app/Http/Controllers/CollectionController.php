<?php

namespace App\Http\Controllers;

use App\Models\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CollectionController extends Controller
{
    public function index()
    {
        $collections = Collection::where('user_id', Auth::id())->latest()->get();
        return view('collections.index', compact('collections'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        Collection::create([
            'name' => $request->name,
            'description' => $request->description,
            'user_id' => Auth::id(),
        ]);

        return redirect()->route('collections.index')->with('success', 'Collection created successfully!');
    }
    public function show(Collection $collection)
    {
        // Security Check: Ensure the user owns the collection
        if ($collection->user_id !== Auth::id()) {
            abort(403, 'Unauthorized action.');
        }

        // Load the associated papers for this collection
        $collection->load('papers');

        return view('collections.show', compact('collection'));
    }

    // Method to remove a paper from a specific collection
    public function removePaper(Collection $collection, Paper $paper)
    {
        // Security Check: Ensure the user owns the collection
        if ($collection->user_id !== Auth::id()) {
            abort(403, 'Unauthorized action.');
        }

        // Detach (remove) the paper from this collection's pivot table
        $collection->papers()->detach($paper->id);

        return back()->with('success', 'Paper removed from the collection successfully.');
    }
}
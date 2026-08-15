<?php

namespace App\Http\Controllers;

use App\Models\Collection;
use App\Models\Paper;
use App\Models\Tag;
use App\Services\CrossRefService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class PaperController extends Controller
{   
    // Method to display a single paper's details
    public function show(Paper $paper)
    {
        // Security Check: Ensure the user owns the paper
        if ($paper->user_id !== Auth::id()) {
            abort(403, 'Unauthorized action.');
        }

        return view('papers.show', compact('paper'));
    }

    // Method to display a list of all papers uploaded by the authenticated user
    public function index(Request $request)
    {
        // Fetch papers belonging to the logged-in user, ordered by newest first
        $query = Paper::with('tags')->where('user_id', Auth::id())->latest();

        // Apply Tag Filter if requested
        if ($request->filled('tag')) {
            $query->whereHas('tags', function ($q) use ($request) {
                $q->where('tags.id', $request->tag);
            });
        }

        $papers = $query->get();
        $tags = Tag::where('user_id', Auth::id())->get(); // Fetch tags for the dropdown

        // Pass the fetched papers and tags to the view
        return view('papers.index', compact('papers', 'tags'));
    }



    // Method to show the paper upload form
    public function create()
    {
        return view('papers.create');
    }

    // Injecting CrossRefService into the store method
    public function store(Request $request, CrossRefService $crossRefService)
    {
        // 1. Validation
        $request->validate([
            'doi' => 'required_without:file|nullable|string|unique:papers,doi',
            'file' => 'required_without:doi|nullable|mimes:pdf|max:10240',
            'title' => 'nullable|string|max:255', 
        ]);

        // 2. File Upload Handling
        $filePath = null;
        if ($request->hasFile('file')) {
            $filePath = $request->file('file')->store('papers', 'public');
        }

        // 3. Fetch Metadata Using Service
        $metadata = [];
        if ($request->filled('doi')) {
            // Controller only delegates the task to the service, no direct API calls here
            $metadata = $crossRefService->fetchMetadata($request->doi) ?? [];
        }

        // 4. Save to database
        Paper::create([
            'title' => $request->title ?? ($metadata['title'] ?? 'Untitled Paper'),
            'authors' => $metadata['authors'] ?? null,
            'year' => $metadata['year'] ?? null,
            'venue' => $metadata['venue'] ?? null,
            'abstract' => $request->abstract ?? ($metadata['abstract'] ?? null),
            'doi' => $request->doi,
            'file_path' => $filePath,
            'user_id' => Auth::id(),
        ]);

        return redirect()->route('dashboard')->with('success', 'Paper added successfully with metadata!');
    }
    // Method to delete a paper
    public function destroy(Paper $paper)
    {
        // 1. Security Check: Ensure the user owns the paper
        if ($paper->user_id !== Auth::id()) {
            abort(403, 'Unauthorized action.');
        }

        // 2. Delete the physical PDF file from storage
        if ($paper->file_path) {
            Storage::disk('public')->delete($paper->file_path);
        }

        // 3. Delete the record from the database
        $paper->delete();

        // 4. Redirect back with success message
        return redirect()->route('papers.index')->with('success', 'Paper deleted successfully!');
    }

    // Method to show the edit form for a specific paper
    public function edit(Paper $paper)
    
    {
        if ($paper->user_id !== Auth::id()) {
            abort(403, 'Unauthorized action.');
        }

        // Fetch user's collections to display as checkboxes
        $collections = Collection::where('user_id', Auth::id())->get();

        // Fetch user's tags to display as checkboxes
        $tags = Tag::where('user_id', Auth::id())->get();

        return view('papers.edit', compact('paper', 'collections', 'tags'));
    }

    // Method to update the paper's details and reading status
    public function update(Request $request, Paper $paper)
    {
        // Security Check: Ensure the user owns the paper
        if ($paper->user_id !== Auth::id()) {
            abort(403, 'Unauthorized action.');
        }

        // Validate the incoming data
        $request->validate([
            'title' => 'required|string|max:255',
            'authors' => 'nullable|string',
            'year' => 'nullable|string',
            'venue' => 'nullable|string',
            'reading_status' => 'required|in:to read,reading,read',
            'tags' => 'nullable|array',
            'tags.*' => 'integer',
        ]);

        // ... (existing update logic) ...
        $paper->update([
            'title' => $request->title,
            'authors' => $request->authors,
            'year' => $request->year,
            'venue' => $request->venue,
            'reading_status' => $request->reading_status,
        ]);

        // Sync collections: attach selected, detach unselected
        if ($request->has('collections')) {
            $paper->collections()->sync($request->collections);
        } else {
            // If no checkboxes are selected, remove from all collections
            $paper->collections()->sync([]);
        }

        // Sync tags, keeping only the tags that actually belong to this user
        $tagIds = Tag::where('user_id', Auth::id())
            ->whereIn('id', $request->input('tags', []))
            ->pluck('id');

        $paper->tags()->sync($tagIds);

        return redirect()->route('papers.index')->with('success', 'Paper updated successfully!');
    }
    // Method to open the PDF reader interface
    public function read(Paper $paper)
    {
        // Security Check
        if ($paper->user_id !== Auth::id()) {
            abort(403, 'Unauthorized action.');
        }

        // Ensure PDF exists
        if (!$paper->file_path) {
            return redirect()->back()->with('error', 'No PDF file uploaded for this paper.');
        }

        return view('papers.read', compact('paper'));
    }
}
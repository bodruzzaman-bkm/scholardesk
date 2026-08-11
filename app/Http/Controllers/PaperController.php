<?php

namespace App\Http\Controllers;

use App\Models\Paper;
use App\Services\CrossRefService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PaperController extends Controller
{
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
}
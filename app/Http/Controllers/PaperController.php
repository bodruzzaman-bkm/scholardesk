<?php

namespace App\Http\Controllers;

use App\Models\Paper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PaperController extends Controller
{
    // Method to show the paper upload form
    public function create()
    {
        return view('papers.create');
    }

    // Method to process and save the uploaded paper
    // Method to process and save the uploaded paper
    public function store(Request $request)
    {
        // 1. Validate: Require either 'file' OR 'doi'
        $request->validate([
            'title' => 'required|string|max:255',
            'abstract' => 'nullable|string',
            'doi' => 'required_without:file|nullable|string|unique:papers,doi',
            'file' => 'required_without:doi|nullable|mimes:pdf|max:10240',
        ]);

        // 2. Check if a file was uploaded, then save it
        $filePath = null;
        if ($request->hasFile('file')) {
            $filePath = $request->file('file')->store('papers', 'public');
        }

        // 3. Save the paper details in the database
        Paper::create([
            'title' => $request->title,
            'abstract' => $request->abstract,
            'doi' => $request->doi,
            'file_path' => $filePath,
            'user_id' => Auth::id(), 
        ]);

        // 4. Redirect back to the dashboard with a success message
        return redirect()->route('dashboard')->with('success', 'Paper added successfully!');
    }
}
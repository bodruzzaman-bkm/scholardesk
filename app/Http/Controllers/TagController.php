<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TagController extends Controller
{
    // Method to create a new colored tag for the authenticated user
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'color' => 'required|string|max:7', // Hex color code
        ]);

        Tag::create([
            'name' => $request->name,
            'color' => $request->color,
            'user_id' => Auth::id(),
        ]);

        return back()->with('success', 'Tag created successfully!');
    }
}

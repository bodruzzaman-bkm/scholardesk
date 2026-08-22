<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTagRequest;
use App\Http\Requests\UpdateTagRequest;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class TagController extends Controller
{
    /** Tag management page: list, rename, recolour, delete. */
    public function index(): View
    {
        $tags = Tag::query()
            ->ownedBy(Auth::id())
            ->withCount('papers')
            ->orderBy('name')
            ->get();

        return view('tags.index', compact('tags'));
    }

    // Method to create a new colored tag for the authenticated user
    public function store(StoreTagRequest $request): RedirectResponse
    {
        Tag::create([
            ...$request->validated(),
            'user_id' => Auth::id(),
        ]);

        return back()->with('success', 'Tag created.');
    }

    public function update(UpdateTagRequest $request, Tag $tag): RedirectResponse
    {
        $this->authorize('update', $tag);

        $tag->update($request->validated());

        return back()->with('success', 'Tag updated.');
    }

    /**
     * Deleting a tag detaches it from every paper but leaves the papers alone.
     * The pivot rows go via the FK cascade defined in the paper_tag migration;
     * detaching explicitly keeps the behaviour obvious and database-agnostic.
     */
    public function destroy(Tag $tag): RedirectResponse
    {
        $this->authorize('delete', $tag);

        $tag->papers()->detach();
        $tag->delete();

        return back()->with('success', 'Tag deleted.');
    }
}

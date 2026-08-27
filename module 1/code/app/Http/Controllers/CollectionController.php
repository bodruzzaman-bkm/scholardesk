<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCollectionRequest;
use App\Http\Requests\StorePapersBatchRequest;
use App\Http\Requests\UpdateCollectionRequest;
use App\Models\Collection;
use App\Models\Paper;          // was missing entirely, which broke removePaper()
use App\Services\ActivityService;
use App\Services\AiService;
use App\Services\CitationService;
use App\Services\CollectionService;
use App\Services\ExportService;
use App\Services\PaperService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CollectionController extends Controller
{
    public function __construct(
        private CollectionService $collections,
        private ActivityService $activities,
    ) {}

    public function index(): View
    {
        // Shared collections appear alongside owned ones.
        $collections = Collection::query()
            ->accessibleBy(Auth::id())
            // withCount avoids loading every paper just to show "N papers".
            ->withCount(['papers', 'members'])
            ->with('members')
            ->latest()
            ->paginate(12);

        return view('collections.index', compact('collections'));
    }

    public function store(StoreCollectionRequest $request): RedirectResponse
    {
        $collection = $this->collections->create($request->user(), $request->validated());

        return redirect()
            ->route('collections.show', $collection)
            ->with('success', 'Collection created.');
    }

    public function show(Request $request, Collection $collection, AiService $ai): View
    {
        $this->authorize('view', $collection);

        $collection->load([
            'papers' => fn ($q) => $q->with('tags')->latest(),
            'members.user:id,name,email',
            'reviews' => fn ($q) => $q->where('user_id', Auth::id()),
        ]);

        // Papers the user can access that are not yet in this collection.
        $available = Paper::query()
            ->accessibleBy(Auth::id())
            ->whereDoesntHave('collections', fn ($q) => $q->where('collections.id', $collection->id))
            ->orderBy('title')
            ->get(['id', 'title', 'year']);

        $comments = $collection->comments()
            ->roots()
            ->with(['user:id,name', 'replies.user:id,name'])
            ->oldest()
            ->get();

        return view('collections.show', [
            'collection' => $collection,
            'available' => $available,
            'comments' => $comments,
            'activities' => $this->activities->forCollection($collection),
            'myRole' => $collection->roleFor($request->user()),
            'aiConfigured' => $ai->isConfigured(),
        ]);
    }

    public function update(UpdateCollectionRequest $request, Collection $collection): RedirectResponse
    {
        $this->authorize('update', $collection);

        $collection->update($request->validated());

        return redirect()
            ->route('collections.show', $collection)
            ->with('success', 'Collection updated.');
    }

    /**
     * Delete the collection itself. The papers inside it are left alone — only
     * the pivot rows go, which is what ScholarDesk specifies.
     */
    public function destroy(Collection $collection): RedirectResponse
    {
        $this->authorize('delete', $collection);

        $collection->papers()->detach();
        $collection->delete();

        return redirect()
            ->route('collections.index')
            ->with('success', 'Collection deleted. The papers themselves are still in your library.');
    }

    /**
     * Add one or more existing papers to this collection.
     *
     * Accepts either `paper_id` (single select) or `paper_ids[]` (multi
     * select), so one endpoint serves both controls.
     */
    public function addPaper(Request $request, Collection $collection): RedirectResponse
    {
        $this->authorize('managePapers', $collection);

        $validated = $request->validate([
            'paper_id' => ['nullable', 'integer'],
            'paper_ids' => ['nullable', 'array', 'max:100'],
            'paper_ids.*' => ['integer'],
        ]);

        $ids = $validated['paper_ids'] ?? [];
        if (! empty($validated['paper_id'])) {
            $ids[] = $validated['paper_id'];
        }

        if ($ids === []) {
            return back()->with('error', 'Select at least one paper to add.');
        }

        // Access is re-checked per paper inside the service.
        $result = $this->collections->addPapers($collection, $request->user(), $ids);

        $message = "Added {$result['added']} ".str('paper')->plural($result['added']).'.';
        if ($result['skipped'] > 0) {
            $message .= " {$result['skipped']} were already in the collection or not accessible.";
        }

        return back()->with('success', $message);
    }

    /** Upload new PDFs straight into this collection. */
    public function uploadPapers(StorePapersBatchRequest $request, Collection $collection, PaperService $papers): RedirectResponse
    {
        $this->authorize('managePapers', $collection);

        $result = $papers->createManyFromUploads(Auth::id(), $request->file('files', []));
        $created = collect($result['created']);

        if ($created->isEmpty()) {
            return back()->with('error', 'None of those files could be added.');
        }

        $this->collections->addPapers($collection, $request->user(), $created->pluck('id')->all());

        $message = 'Uploaded '.$created->count().' '.str('paper')->plural($created->count()).' into this collection.';
        if ($result['failed'] !== []) {
            $message .= ' '.count($result['failed']).' file(s) failed.';
        }

        return back()->with('success', $message);
    }

    // Method to remove a paper from a specific collection
    public function removePaper(Request $request, Collection $collection, Paper $paper): RedirectResponse
    {
        $this->authorize('managePapers', $collection);

        $this->collections->removePaper($collection, $request->user(), $paper);

        return back()->with('success', 'Paper removed from the collection.');
    }

    /** Download the whole collection as one Markdown document. */
    public function bundle(Collection $collection, ExportService $exports): BinaryFileResponse
    {
        $this->authorize('view', $collection);

        // Requirement 16 asks for "its papers, notes, and a formatted
        // bibliography, as a single downloadable file" — so the PDFs travel
        // with it, which means an archive rather than a Markdown document.
        $archive = $exports->collectionArchive($collection, request()->user());

        return response()
            ->download($archive['path'], $archive['filename'], [
                'Content-Type' => 'application/zip',
            ])
            // The zip is a temporary file; without this, storage/app/tmp grows
            // by a full copy of the collection on every download.
            ->deleteFileAfterSend(true);
    }

    /** Export every citation in this collection as one file. */
    public function export(Request $request, Collection $collection, CitationService $citations): Response
    {
        $this->authorize('view', $collection);

        $format = $request->query('format', 'bibtex');

        if (! in_array($format, CitationService::FORMATS, true)) {
            $format = 'bibtex';
        }

        $body = $citations->formatMany($collection->papers()->get(), $format);
        $extension = $format === 'bibtex' ? 'bib' : 'txt';
        $filename = str($collection->name)->slug()->value().'.'.$extension;

        return response($body, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}

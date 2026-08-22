<?php

namespace App\Http\Controllers;

use App\Enums\ActivityType;
use App\Enums\ReadingStatus;
use App\Http\Requests\StorePaperRequest;
use App\Http\Requests\StorePapersBatchRequest;
use App\Http\Requests\UpdatePaperRequest;
use App\Models\ChatSession;
use App\Models\Collection;
use App\Models\Paper;
use App\Models\Tag;
use App\Services\ActivityService;
use App\Services\AiService;
use App\Services\CitationService;
use App\Services\PaperService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Enum;
use Illuminate\View\View;

class PaperController extends Controller
{
    public function __construct(private PaperService $papers) {}

    /** The library: keyword search, filters, sorting and pagination. */
    public function index(Request $request): View
    {
        $userId = Auth::id();

        $filters = $request->only(['q', 'tag', 'status', 'year', 'author', 'venue', 'collection', 'sort']);

        $papers = $this->papers->paginateLibrary($userId, $filters);
        $options = $this->papers->filterOptions($userId);

        return view('papers.index', [
            'papers' => $papers,
            'filters' => $filters,
            'tags' => Tag::query()->ownedBy($userId)->orderBy('name')->get(),
            'collections' => Collection::query()->ownedBy($userId)->orderBy('name')->get(),
            'years' => $options['years'],
            'venues' => $options['venues'],
            'authors' => $options['authors'],
            'statuses' => ReadingStatus::options(),
            'statusCounts' => $this->papers->statusCounts($userId),
        ]);
    }

    public function show(Request $request, Paper $paper, AiService $ai): View
    {
        $this->authorize('view', $paper);

        // Eager-load everything the detail page renders, in one pass.
        $paper->load(['tags', 'collections', 'notes.user']);

        return view('papers.show', [
            'paper' => $paper,
            'aiConfigured' => $ai->isConfigured(),
            // Previously persisted turns. They were already being written but
            // never read back, so reloading silently discarded the discussion.
            'chatHistory' => $this->chatHistoryFor($request->user()->id, $paper),
        ]);
    }

    /**
     * Bulk upload: several PDFs become several papers in one submission.
     */
    public function storeBatch(StorePapersBatchRequest $request): RedirectResponse
    {
        $result = $this->papers->createManyFromUploads(Auth::id(), $request->file('files', []));

        $added = count($result['created']);
        $failed = $result['failed'];

        if ($added === 0) {
            return back()->with('error', 'None of those files could be added.')->withErrors([
                'files' => collect($failed)->map(fn ($f) => "{$f['name']}: {$f['error']}")->implode(' '),
            ]);
        }

        $message = "Added {$added} ".str('paper')->plural($added).' to your library.';

        if ($failed !== []) {
            $message .= ' '.count($failed).' file(s) could not be added: '
                .collect($failed)->pluck('name')->implode(', ');
        }

        return redirect()->route('papers.index')->with('success', $message);
    }

    /** Re-run text extraction and embedding for a paper. */
    public function reindex(Paper $paper): RedirectResponse
    {
        $this->authorize('update', $paper);

        if (! $paper->hasPdf()) {
            return back()->with('error', 'This paper has no PDF to index.');
        }

        $this->papers->queueIndexing($paper);

        return back()->with('success', 'Re-indexing started. Search and AI will pick it up shortly.');
    }

    public function create(): View
    {
        return view('papers.create');
    }

    public function store(StorePaperRequest $request): RedirectResponse
    {
        $paper = $this->papers->createForUser(
            Auth::id(),
            $request->validated(),
            $request->file('file')
        );

        // Land on the paper just created rather than the dashboard, so the
        // user can immediately correct metadata or start reading.
        return redirect()
            ->route('papers.show', $paper)
            ->with('success', 'Paper added to your library.');
    }

    public function edit(Paper $paper): View
    {
        $this->authorize('update', $paper);

        $userId = Auth::id();
        $paper->load(['tags', 'collections']);

        return view('papers.edit', [
            'paper' => $paper,
            'collections' => Collection::query()->ownedBy($userId)->orderBy('name')->get(),
            'tags' => Tag::query()->ownedBy($userId)->orderBy('name')->get(),
            'statuses' => ReadingStatus::options(),
        ]);
    }

    public function update(UpdatePaperRequest $request, Paper $paper): RedirectResponse
    {
        $this->authorize('update', $paper);

        $validated = $request->validated();

        $this->papers->update(
            $paper,
            [
                'title' => $validated['title'],
                'authors' => $validated['authors'] ?? null,
                'year' => $validated['year'] ?? null,
                'venue' => $validated['venue'] ?? null,
                'abstract' => $validated['abstract'] ?? null,
                'reading_status' => $validated['reading_status'],
            ],
            // The edit form always renders both control groups, so an absent
            // key genuinely means "everything was unchecked".
            $validated['collections'] ?? [],
            $validated['tags'] ?? [],
        );

        return redirect()
            ->route('papers.show', $paper)
            ->with('success', 'Paper updated.');
    }

    public function destroy(Paper $paper): RedirectResponse
    {
        $this->authorize('delete', $paper);

        $this->papers->delete($paper);

        return redirect()
            ->route('papers.index')
            ->with('success', 'Paper deleted.');
    }

    /** Quick reading-status change from the library or detail page. */
    public function updateStatus(Request $request, Paper $paper, ActivityService $activities): RedirectResponse
    {
        $this->authorize('update', $paper);

        $validated = $request->validate([
            'reading_status' => ['required', new Enum(ReadingStatus::class)],
        ]);

        $status = ReadingStatus::from($validated['reading_status']);

        // Nothing to record if the status did not actually change.
        if ($paper->reading_status === $status) {
            return back();
        }

        $this->papers->setStatus($paper, $status);

        // Requirement 19 lists status changes among the feed's events.
        foreach ($paper->collections()->get() as $collection) {
            $activities->record($collection, $request->user(), ActivityType::StatusChanged, [
                'paper_id' => $paper->id,
                'title' => $paper->title,
                'status' => $status->label(),
            ]);
        }

        return back()->with('success', 'Reading status updated.');
    }

    /** The in-browser PDF reader, with the AI assistant alongside it. */
    public function read(Request $request, Paper $paper, AiService $ai): View|RedirectResponse
    {
        $this->authorize('read', $paper);

        if (! $paper->hasPdf()) {
            return redirect()
                ->route('papers.show', $paper)
                ->with('error', 'This paper has no PDF attached. Upload one to read it here.');
        }

        return view('papers.read', [
            'paper' => $paper,
            'aiConfigured' => $ai->isConfigured(),
            // The same conversation as the detail page, so a question asked
            // while reading is still there afterwards and vice versa.
            'chatHistory' => $this->chatHistoryFor($request->user()->id, $paper),
        ]);
    }

    /**
     * Previously persisted turns for this user's conversation about a paper.
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\ChatMessage>
     */
    private function chatHistoryFor(int $userId, Paper $paper)
    {
        $session = ChatSession::query()
            ->where('user_id', $userId)
            ->where('scope', 'paper')
            ->where('paper_id', $paper->id)
            ->first();

        return $session
            ? $session->messages()->get(['role', 'content', 'citations'])
            : collect();
    }

    /** Download this paper's citation as BibTeX, APA or plain text. */
    public function export(Request $request, Paper $paper, CitationService $citations): Response
    {
        $this->authorize('view', $paper);

        $format = $request->query('format', 'bibtex');

        if (! in_array($format, CitationService::FORMATS, true)) {
            $format = 'bibtex';
        }

        $body = $citations->format($paper, $format);
        $extension = $format === 'bibtex' ? 'bib' : 'txt';
        $filename = $citations->citationKey($paper).'.'.$extension;

        return response($body, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}

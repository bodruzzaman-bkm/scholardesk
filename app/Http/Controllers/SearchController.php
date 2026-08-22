<?php

namespace App\Http\Controllers;

use App\Enums\ReadingStatus;
use App\Models\Collection;
use App\Models\Tag;
use App\Services\PaperService;
use App\Services\VectorSearchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Dedicated search page offering both modes side by side:
 * keyword (SQL LIKE over metadata) and semantic (vector similarity over the
 * extracted full text).
 */
class SearchController extends Controller
{
    public function __construct(
        private PaperService $papers,
        private VectorSearchService $vectors,
    ) {}

    public function index(Request $request): View
    {
        $userId = Auth::id();
        $query = trim((string) $request->query('q', ''));
        $mode = $request->query('mode') === 'semantic' ? 'semantic' : 'keyword';

        $keywordResults = null;
        $semanticResults = null;

        if ($query !== '') {
            if ($mode === 'semantic') {
                $semanticResults = $this->vectors->searchPapers($query, $userId, 15);
            } else {
                $keywordResults = $this->papers->paginateLibrary(
                    $userId,
                    $request->only(['q', 'tag', 'status', 'year', 'author', 'venue', 'collection', 'sort']),
                    15
                );
            }
        }

        $options = $this->papers->filterOptions($userId);

        return view('search.index', [
            'query' => $query,
            'mode' => $mode,
            'keywordResults' => $keywordResults,
            'semanticResults' => $semanticResults,
            'filters' => $request->only(['q', 'tag', 'status', 'year', 'author', 'venue', 'collection', 'sort']),
            'tags' => Tag::query()->ownedBy($userId)->orderBy('name')->get(),
            'collections' => Collection::query()->accessibleBy($userId)->orderBy('name')->get(),
            'years' => $options['years'],
            'venues' => $options['venues'],
            'authors' => $options['authors'],
            'statuses' => ReadingStatus::options(),
        ]);
    }
}

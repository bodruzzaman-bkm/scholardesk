# Module 3 — Advanced AI Research & Citation Export

**Functional requirements 11–15.** Source: `scholardesk/docs/ScholarDesk.pdf`.

For each requirement: the code path from URL to answer, the actual code that
implements it, and the file it lives in.

**Paths are relative to the project root.** Every file listed is also copied
into this folder under `code/` at the same path — so
`app/Services/RagService.php` is here as `code/app/Services/RagService.php`.
Line numbers were re-verified against the live source on 2026-08-31.

**Status: five of five implemented.** 76 tests, 181 assertions, all passing.

---

# Req 11 — Collection-wide Q&A with citations

> The Users can ask questions across an entire collection and receive a
> synthesized answer with citations linking back to the source papers.

### Code path

```
POST /ai/collections/{collection}/ask     routes/web.php:137
  -> AiController::askCollection()        app/Http/Controllers/AiController.php:73
     -> RagService::askCollection()       app/Services/RagService.php:139
        -> VectorSearchService::search()  app/Services/VectorSearchService.php:70
        -> RagService::answer()           app/Services/RagService.php:225
           -> AiService::generate()       app/Services/AiService.php
           -> RagService::answerCites()   app/Services/RagService.php:310
  -> rendered by                          resources/views/components/ai/chat.blade.php
```

### 1. The route — `routes/web.php:133-139`

Throttled, because every call spends the provider's token quota.

```php
Route::middleware('throttle:20,1')->group(function () {
    Route::post('/ai/ask', [AiController::class, 'askLibrary'])->name('ai.ask');
    Route::post('/ai/papers/{paper}/summary', [AiController::class, 'summarize'])->name('ai.paper.summary');
    Route::post('/ai/papers/{paper}/ask', [AiController::class, 'askPaper'])->name('ai.paper.ask');
    Route::post('/ai/collections/{collection}/ask', [AiController::class, 'askCollection'])->name('ai.collection.ask');
    Route::post('/ai/collections/{collection}/review', [AiController::class, 'review'])->name('ai.collection.review');
});
```

### 2. The controller — `app/Http/Controllers/AiController.php:73-89`

```php
public function askCollection(Request $request, Collection $collection): JsonResponse
{
    $this->authorize('useAi', $collection);

    $validated = $request->validate([
        'question' => ['required', 'string', 'min:3', 'max:1000'],
    ]);

    return $this->guard(function () use ($collection, $request, $validated) {
        $result = $this->rag->askCollection($collection, $request->user(), $validated['question']);

        return [
            'answer' => $result['answer'],
            'citations' => $result['citations'],
        ];
    });
}
```

`guard()` at `AiController.php:150` turns any `AiUnavailableException` into a
503 with a readable message, so a missing API key degrades one panel instead of
breaking the page.

### 3. Retrieval, scoped to the collection — `app/Services/RagService.php:139-152`

```php
public function askCollection(Collection $collection, User $user, string $question): array
{
    $hits = $this->vectors->search($question, $user->id, 'collection', $collection->id, self::TOP_K);

    if ($hits->isEmpty()) {
        throw new AiUnavailableException(
            'Nothing in this collection matched that question. Papers must have an indexed PDF to be searchable.'
        );
    }

    $session = $this->sessionFor($user, 'collection', null, $collection->id, $question);

    return $this->answer($session, $question, $hits);
}
```

**The security-critical part is `app/Services/VectorSearchService.php:201-220`.**
Access is resolved to a concrete paper-id list *before* anything is scored:

```php
private function accessiblePaperIds(string $scope, ?int $scopeId, int $userId): array
{
    if ($scope === 'collection' && $scopeId !== null) {
        $collection = Collection::query()->accessibleBy($userId)->whereKey($scopeId)->first();

        if ($collection === null) {
            return [];
        }

        return $collection->papers()->pluck('papers.id')->all();
    }

    return Paper::query()->accessibleBy($userId)->pluck('id')->all();
}
```

Retrieval is the last step before text reaches a language model, so an
unscoped query here would put one user's papers into another user's answer.

### 4. Citations — `app/Services/RagService.php:231-251`

Each chunk is labelled with its paper id as it goes into the prompt:

```php
foreach ($hits as $hit) {
    $paper = $hit['paper'];
    $label = "[P{$paper->id}]";

    $block = sprintf("%s %s\n%s\n\n", $label, $paper->title, $hit['chunk']->content);

    if (mb_strlen($context) + mb_strlen($block) > $budget) {
        break;                      // stop before the provider rejects the prompt
    }

    $context .= $block;

    $citations[$paper->id] ??= [
        'paper_id' => $paper->id,
        'label' => $label,
        'title' => $paper->title,
        'snippet' => mb_strimwidth(preg_replace('/\s+/u', ' ', $hit['chunk']->content) ?? '', 0, 200, '…'),
        'chunk_index' => $hit['chunk']->chunk_index,
    ];
}
```

The prompt then requires those labels — `RagService.php:254-265`:

```php
$prompt = <<<PROMPT
Answer the question using only the excerpts below.

Cite the source of each claim inline using its bracket label, e.g. [P12].
If the excerpts do not contain the answer, say so plainly instead of
guessing.

EXCERPTS:
{$context}

QUESTION: {$question}
PROMPT;
```

After generation, only the papers the answer actually referenced are returned
— `RagService.php:280-284`:

```php
// Only report citations the model actually referenced.
$used = array_values(array_filter(
    $citations,
    fn (array $c) => $this->answerCites($answer, $c['paper_id'])
));
```

`answerCites()` at `RagService.php:310-313` is deliberately loose about
punctuation, because models drift from the requested `[P12]` into fullwidth
brackets or parentheses:

```php
private function answerCites(string $answer, int $paperId): bool
{
    return preg_match('/\bP'.$paperId.'\b/i', $answer) === 1;
}
```

The `\b` word boundary is what stops `P1` matching inside `P12`.

### Files for this requirement

| File | What it contributes |
|---|---|
| `routes/web.php:133-139` | Throttled AI routes |
| `app/Http/Controllers/AiController.php` | `askCollection()` :73, `guard()` :150 |
| `app/Services/RagService.php` | `askCollection()` :139, `answer()` :225, `answerCites()` :310 |
| `app/Services/VectorSearchService.php` | `search()` :70, `accessiblePaperIds()` :201 |
| `app/Services/AiService.php` | Provider calls (Groq / Gemini) |
| `app/Models/ChatSession.php`, `ChatMessage.php` | Conversation history and stored citations |
| `app/Exceptions/AiUnavailableException.php` | No key / no matches / provider down |
| `resources/views/components/ai/chat.blade.php` | The ask-and-answer panel |
| `resources/views/components/ai/runtime.blade.php` | Shared fetch + markdown helpers |
| `tests/Feature/AiLayerTest.php`, `CitationMatchingTest.php` | Tests |

---

# Req 12 — Semantic and keyword search with filters

> Users can search the library semantically, by meaning, in addition to
> keyword search with filters for year, author, venue, tag, and reading
> status.

### Code path

```
GET /search                                 routes/web.php:54
  -> SearchController::index()              app/Http/Controllers/SearchController.php:26
     |- semantic: VectorSearchService::searchPapers()  app/Services/VectorSearchService.php:131
     |            -> EmbeddingService::embed()          app/Services/EmbeddingService.php:49
     |            -> EmbeddingService::similarity()     app/Services/EmbeddingService.php:87
     '- keyword:  PaperService::paginateLibrary()       app/Services/PaperService.php:37
                  -> filter scopes on                   app/Models/Paper.php:141-206
  -> rendered by                            resources/views/search/index.blade.php
```

### 1. One route, two modes — `app/Http/Controllers/SearchController.php:26-45`

```php
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
```

### 2. All five filters — `app/Services/PaperService.php:37-52`

The proposal names year, author, venue, tag and reading status. Every one is a
scope in the chain:

```php
public function paginateLibrary(int $userId, array $filters, int $perPage = 12): LengthAwarePaginator
{
    return Paper::query()
        ->ownedBy($userId)
        ->with('tags')
        ->search($filters['q'] ?? null)
        ->withTag($filters['tag'] ?? null)              // <- tag
        ->withStatus($filters['status'] ?? null)        // <- reading status
        ->withYear($filters['year'] ?? null)            // <- year
        ->withAuthor($filters['author'] ?? null)        // <- author
        ->withVenue($filters['venue'] ?? null)          // <- venue
        ->inCollection($filters['collection'] ?? null)
        ->sorted($filters['sort'] ?? null)
        ->paginate($perPage)
        ->withQueryString();
}
```

| Filter | Scope | Line |
|---|---|---|
| keyword | `scopeSearch` | `app/Models/Paper.php:141` |
| tag | `scopeWithTag` | `app/Models/Paper.php:156` |
| reading status | `scopeWithStatus` | `app/Models/Paper.php:165` |
| year | `scopeWithYear` | `app/Models/Paper.php:174` |
| author | `scopeWithAuthor` | `app/Models/Paper.php:190` |
| venue | `scopeWithVenue` | `app/Models/Paper.php:199` |

Keyword search delegates to `App\Support\Search` — `app/Models/Paper.php:141-151`:

```php
public function scopeSearch(Builder $query, ?string $term): Builder
{
    $term = trim((string) $term);

    if ($term === '') {
        return $query;
    }

    // Support\Search chooses LIKE or ILIKE for the driver, so this matches
    // case-insensitively on Postgres as well as SQLite, and escapes the
    // wildcards so a search for "50%" does not match every row. Column
    // names are hard-coded there; only the value is bound.
    return Search::anyColumn($query, ['title', 'authors', 'abstract', 'venue', 'doi'], $term);
}
```

**That helper exists because the drivers disagree silently.** SQLite's `LIKE`
is case-insensitive for ASCII; Postgres's is case-*sensitive*, and `ILIKE` is
the insensitive form. A library that searched correctly on SQLite would have
quietly stopped matching "Attention" for a query of "attention" once deployed
to Postgres — no error, just fewer results.
`App\Support\Search::operator()` picks the right one per connection.

Escaping matters for a second reason: without it, a search for `50%` matches
every row, because `%` is the wildcard.

Author is a substring match, because `authors` is a comma-separated string
rather than a relation — `app/Models/Paper.php:190-197`:

```php
public function scopeWithAuthor(Builder $query, mixed $author): Builder
{
    if (blank($author)) {
        return $query;
    }

    return $query->whereRaw(Search::clause('authors'), [Search::pattern((string) $author)]);
}
```

So selecting "Vaswani" finds papers where they are any of the listed authors,
not only the first.

### 3. Semantic search — `app/Services/VectorSearchService.php:131-150`

```php
public function searchPapers(string $query, int $userId, int $limit = 10): SupportCollection
{
    return $this->search($query, $userId, 'library', null, $limit * 6)
        ->groupBy(fn (array $hit) => $hit['paper']->id)
        ->map(function (SupportCollection $group) {
            $best = $group->sortByDesc('score')->first();

            return [
                'paper' => $best['paper'],
                'score' => $best['score'],
                'snippet' => $this->snippet($best['chunk']->content),
            ];
        })
        ->filter(fn (array $hit) => $hit['score'] >= self::MIN_DISPLAY_SCORE)
        ->sortByDesc('score')
        ->take($limit)
        ->values();
}
```

Chunks are scored in PHP — `VectorSearchService.php:178-193`:

```php
return PaperChunk::query()
    ->whereIn('paper_id', $paperIds)
    ->whereNotNull('embedding')
    ->get()
    ->map(function (PaperChunk $chunk) use ($queryVector, $papers) {
        return [
            'chunk' => $chunk,
            'paper' => $papers[$chunk->paper_id] ?? null,
            'score' => $this->embeddings->similarity($queryVector, $chunk->embedding ?? []),
        ];
    })
    ->filter(fn (array $hit) => $hit['paper'] !== null && $hit['score'] > self::MIN_RETRIEVAL_SCORE)
    ->sortByDesc('score')
    ->take($k)
    ->values();
```

SQLite has no vector type, so this is O(n) rather than index-assisted — but
exact rather than approximate, and comfortably under a second for a personal
library. Moving to PostgreSQL with pgvector would mean rewriting only this
class.

### 4. The three thresholds — `app/Services/VectorSearchService.php:31-61`

```php
private const MIN_RETRIEVAL_SCORE = 0.01;   // hand to the model; the prompt is the safety net
private const MIN_DISPLAY_SCORE   = 0.15;   // below this is noise, drop it
public  const WEAK_MATCH_SCORE    = 0.32;   // below this, show it but label it

public static function isWeak(float $score): bool
{
    return $score < self::WEAK_MATCH_SCORE;
}
```

A **0.32 display floor was tried and rejected.** It suppressed off-topic
queries cleanly, but it also dropped short on-topic ones — "tumour detection in
scans" scores about 0.28 against a paper plainly about exactly that, because a
four-word query shares little surface with a long passage. Losing a paper the
user knows is there is worse than showing one they can dismiss at a glance, so
the floor only removes genuine noise and weak matches are **labelled** instead.

### Files for this requirement

| File | What it contributes |
|---|---|
| `routes/web.php:54` | `GET /search` |
| `app/Http/Controllers/SearchController.php` | Mode switch :30, dispatch :36-44 |
| `app/Services/PaperService.php` | `paginateLibrary()` :37, `filterOptions()` :259 |
| `app/Models/Paper.php` | Six filter scopes, :141-206 |
| `app/Services/VectorSearchService.php` | `searchPapers()` :131, `rank()` :158, thresholds :31-61 |
| `app/Services/EmbeddingService.php` | `embed()` :49, `similarity()` :87 |
| `resources/views/search/index.blade.php` | Search UI, mode toggle, filter controls |
| `tests/Feature/SemanticSearchTest.php`, `SearchRelevanceTest.php` | Tests |

---

# Req 13 — Related papers

> The system recommends related papers from the user's own library based on
> content similarity.

### Code path

```
GET /ai/papers/{paper}/related                routes/web.php:140  (NOT throttled)
  -> AiController::related()                  app/Http/Controllers/AiController.php:124
     -> VectorSearchService::relatedPapers()   app/Services/VectorSearchService.php:96
        -> EmbeddingService::centroid()        app/Services/EmbeddingService.php:125
  -> rendered by                              resources/views/components/ai/related.blade.php
```

### 1. The route sits outside the throttle group — `routes/web.php:140`

```php
Route::get('/ai/papers/{paper}/related', [AiController::class, 'related'])->name('ai.paper.related');
```

Every other AI route is inside `throttle:20,1`. This one is not, deliberately:
it is pure local vector arithmetic over stored embeddings and never calls an
external provider, so there is no quota to protect.

### 2. The controller — `app/Http/Controllers/AiController.php:124-143`

```php
public function related(Request $request, Paper $paper): JsonResponse
{
    $this->authorize('view', $paper);

    $related = $this->vectors->relatedPapers($paper, $request->user()->id)
        ->map(fn (array $hit) => [
            'id' => $hit['paper']->id,
            'title' => $hit['paper']->title,
            'authors' => $hit['paper']->authors,
            'year' => $hit['paper']->year,
            'score' => round($hit['score'], 3),
            'url' => route('papers.show', $hit['paper']),
        ])
        ->values();

    return response()->json([
        'related' => $related,
        'indexed' => $paper->isIndexed(),
    ]);
}
```

`indexed` is returned so the panel can say "this paper has no indexed text
yet" instead of showing an empty list that looks like a bug.

### 3. Centroid comparison — `app/Services/VectorSearchService.php:96-124`

```php
public function relatedPapers(Paper $paper, int $userId, int $limit = 5): SupportCollection
{
    $vectors = $paper->chunks()->whereNotNull('embedding')->pluck('embedding')->all();

    if ($vectors === []) {
        return collect();
    }

    $centroid = $this->embeddings->centroid($vectors);

    if ($this->isZero($centroid)) {
        return collect();
    }

    // Pull more chunks than needed, because several may belong to one paper.
    $hits = $this->rank($centroid, $userId, 'library', null, $limit * 6, $paper->id);

    return $hits
        ->groupBy(fn (array $hit) => $hit['paper']->id)
        ->map(fn (SupportCollection $group) => [
            'paper' => $group->first()['paper'],
            // A paper's score is its single best-matching chunk.
            'score' => $group->max('score'),
        ])
        ->filter(fn (array $hit) => $hit['score'] >= self::MIN_DISPLAY_SCORE)
        ->sortByDesc('score')
        ->take($limit)
        ->values();
}
```

Two decisions worth pointing at:

- **The source paper is excluded up front** (`$paper->id` passed as
  `$excludePaperId`), not filtered afterwards. A paper's own chunks are always
  its nearest neighbours, so post-filtering would need unbounded headroom on
  `k`.
- **A paper scores as its single best-matching chunk**, not its average. One
  strongly relevant section is a better signal than a diluted whole-document
  mean.

### Files for this requirement

| File | What it contributes |
|---|---|
| `routes/web.php:140` | Un-throttled JSON endpoint |
| `app/Http/Controllers/AiController.php` | `related()` :124 |
| `app/Services/VectorSearchService.php` | `relatedPapers()` :96 |
| `app/Services/EmbeddingService.php` | `centroid()` :125 |
| `resources/views/components/ai/related.blade.php` | The related-papers card |

---

# Req 14 — Literature-review draft

> The Users can generate a structured literature-review draft from selected
> papers or a whole collection, and save it.

### Code path

```
POST /ai/collections/{collection}/review    routes/web.php:138
  -> AiController::review()                 app/Http/Controllers/AiController.php:95
     -> RagService::draftReview()           app/Services/RagService.php:157
        -> AiService::generate()            app/Services/AiService.php
        -> LiteratureReview::create()       app/Services/RagService.php:206
        -> ActivityService::record()        app/Services/RagService.php:214
  -> rendered by                            resources/views/components/ai/review.blade.php
```

### 1. Selection is validated against the collection — `app/Http/Controllers/AiController.php:95-121`

```php
public function review(Request $request, Collection $collection): JsonResponse
{
    $this->authorize('useAi', $collection);

    $validated = $request->validate([
        'paper_ids' => ['nullable', 'array'],
        // Restricted to papers actually in this collection, so a crafted
        // id cannot pull an unrelated paper into the draft.
        'paper_ids.*' => [
            'integer',
            Rule::exists('collection_paper', 'paper_id')->where('collection_id', $collection->id),
        ],
    ]);

    $paperIds = $validated['paper_ids'] ?? null;

    return $this->guard(function () use ($collection, $request, $paperIds) {
        $review = $this->rag->draftReview($collection, $request->user(), $paperIds ?: null);

        return [
            'review_id' => $review->id,
            'title' => $review->title,
            'content' => $review->content,
            'paper_count' => count($review->paper_ids ?? []),
        ];
    });
}
```

`$paperIds ?: null` is what makes **"selected papers *or* a whole collection"**
work: an empty selection becomes `null`, and `null` means everything.

### 2. Selected papers, or all of them — `app/Services/RagService.php:157-166`

```php
public function draftReview(Collection $collection, User $user, ?array $paperIds = null): LiteratureReview
{
    $papers = $collection->papers()
        ->when($paperIds, fn ($q) => $q->whereIn('papers.id', $paperIds))
        ->get();

    if ($papers->isEmpty()) {
        throw new AiUnavailableException('Add papers to this collection before generating a review.');
    }
```

`when($paperIds, ...)` applies the `whereIn` only when a selection was sent.

### 3. The structure — `app/Services/RagService.php:184-203`

```php
$prompt = <<<PROMPT
Draft a structured literature review over the following papers.

Structure it as markdown with:
## Introduction
## Themes across the literature
## Methodological approaches
## Gaps and open questions
## Conclusion

Refer to papers by their title. Ground every claim in the supplied
material; if the material is thin on a point, say so rather than
inventing findings.

COLLECTION: {$collection->name}

PAPERS:
{$context}
PROMPT;
```

### 4. Saving it — `app/Services/RagService.php:206-214`

```php
$review = LiteratureReview::create([
    'collection_id' => $collection->id,
    'user_id' => $user->id,
    'title' => 'Draft review — '.now()->format('j M Y'),
    'content' => $content,
    'paper_ids' => $papers->pluck('id')->all(),
]);

$this->activities->record($collection, $user, ActivityType::ReviewGenerated);
```

`paper_ids` records the exact papers the draft was built from, so a saved
review stays interpretable after the collection changes. Generating one also
lands in the collection's activity feed.

Saved drafts are listed on the collection page —
`resources/views/collections/show.blade.php:182-197`.

### 5. The selection UI — `resources/views/components/ai/review.blade.php`

Line 54, the per-paper checkbox:

```blade
<input type="checkbox" value="{{ $paper->id }}" x-model.number="selected"
```

Line 122, what gets posted:

```js
const data = await window.scholardeskAi.post(this.url, { paper_ids: this.selected });
```

Plus Select-all / None controls at lines 46-47.

### Files for this requirement

| File | What it contributes |
|---|---|
| `routes/web.php:138` | Throttled review endpoint |
| `app/Http/Controllers/AiController.php` | `review()` :95, selection validation :99-107 |
| `app/Services/RagService.php` | `draftReview()` :157, prompt :184, persistence :206 |
| `app/Models/LiteratureReview.php` | The saved draft, `paper_ids` cast |
| `resources/views/components/ai/review.blade.php` | Checkboxes, Select all / None |
| `resources/views/collections/show.blade.php:182-197` | Saved-draft list |
| `tests/Feature/ReviewSelectionTest.php` | Tests |

---

# Req 15 — Citation export

> The Users can export citations in BibTeX, APA, and plain-text formats,
> individually or for an entire collection.

### Code path

```
GET /papers/{paper}/export?format=…            routes/web.php:75
  -> PaperController::export()                 app/Http/Controllers/PaperController.php:250
     -> CitationService::format()              app/Services/CitationService.php:20

GET /collections/{collection}/export?format=…  routes/web.php:84
  -> CollectionController::export()            app/Http/Controllers/CollectionController.php:194
     -> CitationService::formatMany()          app/Services/CitationService.php:30
```

### 1. Exactly the three formats the proposal names — `app/Services/CitationService.php:18-27`

```php
/** @var list<string> */
public const FORMATS = ['bibtex', 'apa', 'text'];

public function format(Paper $paper, string $format): string
{
    return match ($format) {
        'bibtex' => $this->bibtex($paper),
        'apa' => $this->apa($paper),
        default => $this->plainText($paper),
    };
}
```

### 2. One paper — `app/Http/Controllers/PaperController.php:250-268`

```php
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
```

An unrecognised `format` falls back to BibTeX rather than erroring.

### 3. A whole collection — `app/Http/Controllers/CollectionController.php:194-212`

Same shape, but `formatMany()` over every paper:

```php
$body = $citations->formatMany($collection->papers()->get(), $format);
$extension = $format === 'bibtex' ? 'bib' : 'txt';
$filename = str($collection->name)->slug()->value().'.'.$extension;
```

`formatMany()` at `CitationService.php:30-37` joins BibTeX entries with a blank
line and the other formats with a single newline.

### 4. The formatters

**BibTeX** — `CitationService.php:39-59`:

```php
public function bibtex(Paper $paper): string
{
    $key = $this->citationKey($paper);
    $type = $paper->venue ? 'article' : 'misc';

    $fields = array_filter([
        'title' => $paper->title,
        'author' => $this->bibtexAuthors($paper->authors),
        'year' => $paper->year,
        'journal' => $paper->venue,
        'doi' => $paper->doi,
    ], fn ($value) => filled($value));
    ...
```

**APA** — `CitationService.php:61-83`. **Plain text** — `CitationService.php:85-96`:

```php
public function plainText(Paper $paper): string
{
    $segments = array_filter([
        $paper->authors,
        $paper->title,
        $paper->venue,
        $paper->year ? (string) $paper->year : null,
        $paper->doi ? 'doi:'.$paper->doi : null,
    ], fn ($v) => filled($v));

    return implode('. ', $segments).'.';
}
```

**The citation key** — `CitationService.php:99-130` — produces
`firstauthorYEARfirstword`. One line in it exists because of a real bug:

```php
// Cast to string: Str::of() returns a Stringable, which would never
// compare equal to '' and so would defeat the fallbacks below.
$surname = (string) Str::of((string) $firstAuthor)->ascii()->replaceMatches('/[^A-Za-z]/', '')->lower();
```

Without that cast every key came out as `nduntitled`.

Author handling treats `"Vaswani et al."` as a collapsed list rather than a
person — `CitationService.php:151-158` — so APA renders `Vaswani et al.`
instead of parsing `al.` as a surname.

### Files for this requirement

| File | What it contributes |
|---|---|
| `routes/web.php:75, :84` | Per-paper and per-collection export |
| `app/Services/CitationService.php` | `FORMATS` :18, `format()` :20, `formatMany()` :30, `bibtex()` :39, `apa()` :61, `plainText()` :85, `citationKey()` :99 |
| `app/Http/Controllers/PaperController.php` | `export()` :250 |
| `app/Http/Controllers/CollectionController.php` | `export()` :194 |
| `resources/views/papers/show.blade.php:141-146` | BibTeX / APA / Text links |
| `resources/views/collections/show.blade.php:253-255` | The same three for a collection |
| `tests/Unit/CitationServiceTest.php` | Tests |

---

# Supporting: the indexing pipeline

Requirements 11, 12 and 13 only work because a paper's text is extracted,
chunked and embedded first. No indexed text means no retrieval.

```
Paper uploaded
  -> IndexPaper (queued job)         app/Jobs/IndexPaper.php
     -> PdfTextService               app/Services/PdfTextService.php   extract + repair run-on text
     -> ChunkService                 app/Services/ChunkService.php     overlapping chunks
     -> EmbeddingService::embed()    app/Services/EmbeddingService.php:49
     -> PaperChunk rows              app/Models/PaperChunk.php
```

Schema: `database/migrations/2026_08_21_150000_create_ai_tables.php`.

The embedder is local — `app/Services/EmbeddingService.php:25`:

```php
public const DIM = 256;
```

Feature hashing over word tokens and character trigrams, L2-normalised. It
needs no API key, which is why requirements 12 and 13 work with no provider
configured at all.

`POST /papers/{paper}/reindex` (`routes/web.php:91`) retries a failed
extraction.

---

# Summary

| # | Feature | Primary implementation | Status |
|---|---|---|---|
| 11 | Collection Q&A with citations | `RagService::askCollection()` :139, `answer()` :225 | Built |
| 12 | Semantic + keyword search, 5 filters | `SearchController::index()` :26, `Paper` scopes :141-206 | Built |
| 13 | Related papers | `VectorSearchService::relatedPapers()` :96 | Built |
| 14 | Literature-review draft | `RagService::draftReview()` :157 | Built |
| 15 | Citation export | `CitationService` :18-130 | Built |

Run the module's tests from the project root:

```bash
php artisan test --filter="AiLayer|SemanticSearch|SearchRelevance|ReviewSelection|CitationMatching|CitationService|EmbeddingService|ChunkService|RunOnText"
```

→ 76 tests, 181 assertions, passing.

---

# Operating limits

Real constraints on the features above, not gaps in them.

1. **A paper needs an indexed PDF to be reachable by requirements 11–13.**
   A paper added by DOI with no PDF, or a scanned PDF with no text layer, has
   nothing to index — there is no OCR.

2. **The embeddings are lexical, not neural.** They match wording and
   morphology, not true paraphrase: a measured example scored 0.203 for a
   question phrased in entirely different words, against 0.773 for one sharing
   vocabulary. Expect it to find papers that *say* similar things, not papers
   that *mean* similar things in different language.

3. **Generation needs an API key; retrieval does not.** Requirements 11 and 14
   call Groq (or Gemini) and report "not configured" without `GROQ_API_KEY`.
   Requirements 12, 13 and 15 need no key at all.

4. **Groq's free tier allows 8,000 tokens per minute.** Several long questions
   in quick succession are briefly rate-limited. `AiService` distinguishes a
   413 (context too long — retry with fewer chunks) from a 429 (slow down).

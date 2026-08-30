# Module 2 — Reading, Annotation & Single-Paper AI

**Functional requirements 7–10.** Source: `scholardesk/docs/ScholarDesk.pdf`.

For each requirement: the code path from URL to result, the actual code that
implements it, and the file it lives in.

**Paths are relative to the project root.** Every file listed is also copied
into this folder under `code/` at the same path — so
`app/Services/RagService.php` is here as `code/app/Services/RagService.php`.
Line numbers were re-verified against the live source on 2026-08-31.

**Status: four of four implemented.** 72 tests, 211 assertions, all passing.

---

# Req 7 — In-browser PDF reader with coloured highlights

> The Users can read uploaded PDFs inside the browser and create colored
> highlights with margin notes that persist across sessions.

### Code path

```
GET /papers/{paper}/read              routes/web.php:73
  -> PaperController::read()          app/Http/Controllers/PaperController.php:212
  -> resources/views/papers/read.blade.php   (pdf.js renders the document)

GET    /papers/{paper}/highlights     routes/web.php:117  -> HighlightController::index()
POST   /papers/{paper}/highlights     routes/web.php:118  -> HighlightController::store()
PATCH  /highlights/{highlight}        routes/web.php:119  -> HighlightController::update()
DELETE /highlights/{highlight}        routes/web.php:120  -> HighlightController::destroy()
```

### 1. Opening the reader — `app/Http/Controllers/PaperController.php:212-229`

```php
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
```

A paper with no file redirects rather than rendering an empty viewer.

`authorize('read', ...)` rather than `'update'`: `PaperPolicy::read()` grants
access to the owner **and** to collaborators on a collection containing the
paper, so a shared paper is readable without being editable.

### 2. Rendering — `resources/views/papers/read.blade.php:19-22`

```blade
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf_viewer.min.css" />
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.min.js"></script>
<script>
    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.worker.min.js';
```

pdf.js is the only external JavaScript library in the project. Zoom runs
50%–300% (`read.blade.php:219-235`), and a text layer is rendered over each
page (`:269`) so text can be selected — without it there is nothing to
highlight.

### 3. Storing a highlight — `app/Http/Controllers/HighlightController.php:33-68`

The validation rules are the part that matters, and the comment above them
explains why:

```php
/*
 | The rect keys are left/top/width/height because that is what
 | getClientRects() gives the reader, and what renderPdfHighlights()
 | reads back when re-drawing. They are stored unscaled (divided by the
 | zoom level at capture time) so a highlight lands correctly at any
 | later zoom. Renaming them here would silently break re-rendering.
 */
$validated = $request->validate([
    'color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
    'position' => ['required', 'array'],
    'position.page' => ['required', 'integer', 'min:1'],
    'position.rects' => ['required', 'array', 'min:1', 'max:200'],
    'position.rects.*.left' => ['required', 'numeric'],
    'position.rects.*.top' => ['required', 'numeric'],
    'position.rects.*.width' => ['required', 'numeric', 'min:0'],
    'position.rects.*.height' => ['required', 'numeric', 'min:0'],
    'text' => ['nullable', 'string', 'max:5000'],
    'note' => ['nullable', 'string', 'max:5000'],
]);
```

> **A bug this comment exists to prevent.** These rules were briefly written
> as `{x, y, w, h}` while the reader sent `{left, top, width, height}`. Every
> save returned 422, and because the reader ignored the response body it
> failed **silently** — highlights simply never appeared again after a reload.
> The test that should have caught it had enshrined the same wrong assumption.
> `tests/Feature/HighlightTest.php` now uses payloads copied verbatim from the
> reader's own fetch call.

**Coordinates are stored unscaled.** The reader divides by the zoom level at
capture time and multiplies by the current zoom when re-drawing, so a
highlight made at 150% lands correctly at 300%.

**Margin notes** are the `note` column on the same row — a highlight and its
note are one record, which is why editing the note is a `PATCH` to the
highlight (`update()` :69) rather than a separate resource.

### 4. Persistence across sessions

Highlights are database rows
(`database/migrations/2026_08_15_183612_create_highlights_table.php`), not
browser state. On load the reader fetches `highlights.index` and
`renderPdfHighlights()` (`read.blade.php:455`) re-draws them.

`position` is cast to an array on the model (`app/Models/Highlight.php:12`),
so the rect list round-trips as JSON without manual encoding.

### 5. Annotation is owner-only — `app/Policies/PaperPolicy.php`

```php
/**
 * Highlights and notes are private to their author, so only the paper's
 * owner may create them. A collaborator reading a shared PDF does not get
 * to write on someone else's copy.
 */
public function annotate(User $user, Paper $paper): bool
{
    return $this->owns($user, $paper);
}
```

### Files

| File | What it contributes |
|---|---|
| `routes/web.php:73, :117-120` | Reader and highlight endpoints |
| `app/Http/Controllers/PaperController.php` | `read()` :212, `chatHistoryFor()` :236 |
| `app/Http/Controllers/HighlightController.php` | `index()` :20, `store()` :33, `update()` :69, `destroy()` :84 |
| `app/Models/Highlight.php` | `position` cast :12 |
| `app/Policies/HighlightPolicy.php`, `PaperPolicy.php` | Ownership |
| `resources/views/papers/read.blade.php` | pdf.js viewer, zoom, text layer, highlight rendering |
| `tests/Feature/HighlightTest.php` | Tests, using the reader's real payloads |

---

# Req 8 — Markdown notes

> The Users can write, edit, and delete Markdown notes attached to each paper.

### Code path

```
POST   /papers/{paper}/notes   routes/web.php:123   -> NoteController::store()   :19
GET    /notes/{note}/edit      routes/web.php:124  -> NoteController::edit()    :38
PUT    /notes/{note}           routes/web.php:125  -> NoteController::update()  :46
DELETE /notes/{note}           routes/web.php:126  -> NoteController::destroy() :62
```

### Writing a note — `app/Http/Controllers/NoteController.php:19-35`

```php
public function store(Request $request, Paper $paper): RedirectResponse
{
    $this->authorize('annotate', $paper);

    $validated = $request->validate([
        'content' => ['required', 'string', 'max:20000'],
    ]);

    $paper->notes()->create([
        'user_id' => Auth::id(),
        'content' => $validated['content'],
    ]);

    $this->recordActivity($request, $paper);

    return back()->with('success', 'Note added.');
}
```

### Rendering markdown safely — `app/Support/Markdown.php`

This is the security-critical file in Module 2:

```php
/**
 * Render user markdown to sanitised HTML.
 *
 * - `html_input: strip` removes raw HTML tags a user pasted in.
 * - `allow_unsafe_links: false` neutralises `javascript:`, `data:` and
 *   `vbscript:` URLs, which CommonMark otherwise permits and which would
 *   be a stored-XSS vector via `[click me](javascript:...)`.
 */
public static function render(?string $markdown): string
{
    if (blank($markdown)) {
        return '';
    }

    return Str::markdown($markdown, [
        'html_input' => 'strip',
        'allow_unsafe_links' => false,
    ]);
}
```

> **Why this wrapper exists.** Laravel's plain `Str::markdown()` permits
> `javascript:` links. Note content is rendered with `{!! !!}` (it has to be —
> that is what "markdown notes" means), so a note containing
> `[click me](javascript:alert(1))` would have been stored XSS against anyone
> viewing that paper. Every render goes through this class instead.
> `tests/Feature/NoteSecurityTest.php` asserts the payload is neutralised.

### Ordering — `app/Models/Note.php` / `NoteController`

Notes list newest-first with `orderByDesc('id')` as a tiebreaker: two notes
saved in the same second have identical timestamps, and without the secondary
key their order flipped between page loads.

### Files

| File | What it contributes |
|---|---|
| `routes/web.php:123-126` | Note CRUD |
| `app/Http/Controllers/NoteController.php` | `store()` :19, `edit()` :38, `update()` :46, `destroy()` :62 |
| `app/Models/Note.php` | The note |
| `app/Policies/NotePolicy.php` | Author-only edit and delete |
| `app/Support/Markdown.php` | Sanitised rendering |
| `resources/views/notes/edit.blade.php` | The edit form |
| `tests/Feature/NoteTest.php`, `NoteSecurityTest.php` | Tests |

---

# Req 9 — AI summary of a single paper

> The system generates an AI summary of a single paper covering its main idea,
> key contributions, method, and limitations.

### Code path

```
POST /ai/papers/{paper}/summary        routes/web.php:135  (throttle:20,1)
  -> AiController::summarize()         app/Http/Controllers/AiController.php:29
     -> RagService::summarizePaper()   app/Services/RagService.php:54
        -> AiService::generate()       app/Services/AiService.php
  -> rendered by                       resources/views/components/ai/summary.blade.php
```

### The controller — `app/Http/Controllers/AiController.php:29-36`

```php
public function summarize(Paper $paper): JsonResponse
{
    $this->authorize('useAi', $paper);

    return $this->guard(fn () => [
        'summary' => $this->rag->summarizePaper($paper),
    ]);
}
```

`guard()` (:150) converts an `AiUnavailableException` into a 503 with a
readable message, so a missing API key degrades one card instead of breaking
the page.

### The four sections the requirement names — `app/Services/RagService.php:54-83`

```php
public function summarizePaper(Paper $paper): string
{
    $text = $this->paperText($paper);

    if ($text === '') {
        throw new AiUnavailableException(
            'This paper has no extractable text, so it cannot be summarised. Scanned PDFs need OCR, which is out of scope.'
        );
    }

    $prompt = <<<PROMPT
    Summarise the following academic paper.

    Use exactly these four sections, as markdown headings:
    ## TL;DR
    ## Key contributions
    ## Method
    ## Limitations

    Base every statement on the text provided. If the text does not cover a
    section, write "Not stated in the provided text." under that heading.

    PAPER TITLE: {$paper->title}

    PAPER TEXT:
    {$text}
    PROMPT;

    return $this->ai->generate($prompt, $this->systemInstruction());
}
```

The four headings map one-to-one onto the requirement's "main idea, key
contributions, method, and limitations".

**The "Not stated" instruction is the anti-hallucination control.** Without it
a model asked for four sections will invent a plausible Limitations paragraph
for a paper that never discusses any.

Unlike Q&A, summarisation feeds the paper's text directly rather than
retrieving chunks — there is no question to retrieve against.

### Files

| File | What it contributes |
|---|---|
| `routes/web.php:135` | Throttled summary endpoint |
| `app/Http/Controllers/AiController.php` | `summarize()` :29, `guard()` :150 |
| `app/Services/RagService.php` | `summarizePaper()` :54 |
| `app/Services/AiService.php` | Groq / Gemini calls, budgets, 413-vs-429 |
| `app/Exceptions/AiUnavailableException.php` | No key / no text / provider down |
| `resources/views/components/ai/summary.blade.php` | The summary card |
| `tests/Feature/PaperAiSectionsTest.php`, `ReaderAiTest.php` | Tests |

---

# Req 10 — Ask questions about a single paper

> The Users can ask questions about a single paper and receive answers drawn
> from that paper's content.

### Code path

```
POST /ai/papers/{paper}/ask            routes/web.php:136  (throttle:20,1)
  -> AiController::askPaper()          app/Http/Controllers/AiController.php:38
     -> RagService::askPaper()         app/Services/RagService.php:90
        -> VectorSearchService::search()  app/Services/VectorSearchService.php:70
        -> RagService::answer()           app/Services/RagService.php:225
  -> rendered by                       resources/views/components/ai/chat.blade.php
```

### Scoped to one paper — `app/Services/RagService.php:90-110`

```php
public function askPaper(Paper $paper, User $user, string $question): array
{
    $hits = $this->vectors->search($question, $user->id, 'paper', $paper->id, self::TOP_K);

    if ($hits->isEmpty()) {
        throw new AiUnavailableException(
            $paper->isIndexed()
                ? 'Nothing in this paper matched that question.'
                : 'This paper has not been indexed yet, so it cannot be searched.'
        );
    }

    $session = $this->sessionFor($user, 'paper', $paper->id, null, $question);

    return $this->answer($session, $question, $hits);
}
```

The `'paper'` scope with `$paper->id` is what makes answers **drawn from that
paper's content** rather than the whole library. It resolves through
`VectorSearchService::accessiblePaperIds()`
(`app/Services/VectorSearchService.php:203-207`):

```php
if ($scope === 'paper' && $scopeId !== null) {
    $paper = Paper::query()->accessibleBy($userId)->whereKey($scopeId)->first(['id']);

    return $paper ? [$paper->id] : [];
}
```

Note that it re-checks `accessibleBy($userId)` — the scope narrows retrieval,
but access is still verified, so a paper id the user cannot reach returns an
empty list rather than its contents.

**The two different empty-result messages matter.** "Nothing matched" and "not
indexed yet" send the user to completely different actions — rephrase, versus
press Re-index. Collapsing them into one message makes the feature look broken
when it is merely waiting.

### The answer is grounded — `app/Services/RagService.php:254-265`

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

"Using only the excerpts" plus "say so plainly instead of guessing" is what
keeps the answer tied to the paper.

### Conversation continuity — `app/Http/Controllers/PaperController.php:236-249`

```php
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
```

The reader and the paper detail page load the **same** session, so a question
asked while reading is still there afterwards, and vice versa.

### Files

| File | What it contributes |
|---|---|
| `routes/web.php:136` | Throttled Q&A endpoint |
| `app/Http/Controllers/AiController.php` | `askPaper()` :38 |
| `app/Services/RagService.php` | `askPaper()` :90, `answer()` :225, `sessionFor()` :315 |
| `app/Services/VectorSearchService.php` | `search()` :70, paper scope :203 |
| `app/Services/EmbeddingService.php` | `embed()` :49, `similarity()` :87 |
| `app/Models/ChatSession.php`, `ChatMessage.php` | Persisted conversation |
| `resources/views/components/ai/chat.blade.php` | The ask-and-answer panel |
| `resources/views/components/ai/runtime.blade.php` | Shared fetch + markdown helpers |
| `tests/Feature/PaperAiSectionsTest.php`, `ReaderAiTest.php` | Tests |

---

# Supporting: the indexing pipeline

Requirement 10 only works because a paper's text is extracted, chunked and
embedded first. No indexed text means no retrieval.

```
Paper uploaded
  -> IndexPaper (queued job)         app/Jobs/IndexPaper.php
     -> PdfTextService               app/Services/PdfTextService.php   extract + repair run-on text
     -> ChunkService                 app/Services/ChunkService.php     overlapping chunks
     -> EmbeddingService::embed()    app/Services/EmbeddingService.php:49
     -> PaperChunk rows              app/Models/PaperChunk.php
```

### Run-on text repair — `app/Services/PdfTextService.php`

Some PDFs emit no space glyphs at all. In one test document **84.6% of tokens
ran over 25 characters**, mean length 64.7 — the extractor was returning whole
sentences as single "words", which no tokeniser can match against.

`repairRunOnText()` splits on case, digit and punctuation boundaries, and the
embedder windows anything still too long rather than discarding it. Measured
result on that document:

| | Before | After |
|---|---|---|
| Tokens | 364 | 1,212 |
| Mean token length | 64.7 | 19.4 |
| Tokens over 25 chars | 84.6% | 28.5% |

`tests/Unit/RunOnTextTest.php` covers it.

`POST /papers/{paper}/reindex` (`routes/web.php:76`) retries a failed
extraction.

---

# Summary

| # | Feature | Primary implementation | Status |
|---|---|---|---|
| 7 | Reader + coloured highlights | `HighlightController` :33, `read.blade.php` | Built |
| 8 | Markdown notes | `NoteController` :19, `Support\Markdown` | Built |
| 9 | Single-paper AI summary | `RagService::summarizePaper()` :54 | Built |
| 10 | Single-paper Q&A | `RagService::askPaper()` :90 | Built |

Run this module's tests from the project root:

```bash
php artisan test --filter="Highlight|Note|NoteSecurity|PaperAiSections|ReaderAi|ChunkService|RunOnText|EmbeddingService"
```

---

# Operating limits

1. **Requirements 9 and 10 need an API key.** Both call Groq (or Gemini) and
   report "not configured" without `GROQ_API_KEY`. Requirements 7 and 8 need
   no key at all.

2. **Scanned PDFs cannot be summarised or questioned.** With no text layer
   there is nothing to extract, and there is no OCR. Requirement 9 says so
   explicitly in its error message rather than returning an empty summary.

3. **Highlighting needs the text layer.** pdf.js renders it over each page; on
   a PDF where text cannot be selected, there is nothing for
   `getClientRects()` to measure.

4. **Groq's free tier allows 8,000 tokens per minute.** A long paper summary
   followed immediately by a question can hit it. `AiService` distinguishes a
   413 (context too long — retry with less text) from a 429 (slow down), and
   the routes are throttled to 20 requests per minute.

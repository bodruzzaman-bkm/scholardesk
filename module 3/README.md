# Module 3 — Advanced AI Research & Citation Export

**ScholarDesk · CSE470 · functional requirements 11–15**

This folder is the Module 3 submission: the real source files that implement
the module, plus the documentation for it.

| File | What it is |
|---|---|
| [`features.md`](features.md) | The five requirements, what implements each, and the operating limits |
| [`code/`](code/) | The 34 source files, at their real project paths |
| [`sync.ps1`](sync.ps1) | Re-copies `code/` from the live app so it cannot drift |

---

## Read this first

`code/` holds **copies of files from the working application**, kept at their
real project-relative paths (`app/Services/RagService.php`, and so on). They
are the exact files that run — not a rewrite or a summary.

**They are not a standalone program.** Module 3 builds on Modules 1 and 2: it
needs authentication and the paper library (Module 1) and the uploaded PDFs
and extracted text (Module 2) to have anything to search, cite, or answer
questions about. A Laravel application is one program, so the module boundary
is a boundary in the *feature set*, not a separate app that boots on its own.

To run or demonstrate any of this, run the full application from the project
root. Everything in `code/` is already part of it.

`code/routes/module-3-routes.php` is the one file here that is an **excerpt**
rather than a copy: the app's routes live in a single shared `routes/web.php`
covering all four modules, so the Module 3 routes are reproduced there with
comments. The app does not load that file.

---

## Requirement → file map

### Req 11 — Ask questions across a collection, with citations

> The Users can ask questions across an entire collection and receive a
> synthesized answer with citations linking back to the source papers.

| File | Role |
|---|---|
| `app/Http/Controllers/AiController.php` | `askCollection()`, `askLibrary()`, `askPaper()` |
| `app/Services/RagService.php` | Retrieval, prompt assembly, citation extraction |
| `app/Services/AiService.php` | Provider abstraction (Groq / Gemini) |
| `app/Models/ChatSession.php`, `ChatMessage.php` | Conversation history |
| `app/Exceptions/AiUnavailableException.php` | No key, no matches, provider down |
| `resources/views/components/ai/chat.blade.php` | The ask-and-answer panel |
| `resources/views/components/ai/runtime.blade.php` | Shared fetch + markdown helpers |

The citation logic is in `RagService::answer()` and `answerCites()`: chunks are
labelled `[P<paper_id>]` in the prompt, and after generation only the papers
the answer actually referenced are returned as citations.

### Req 12 — Semantic and keyword search with filters

> Users can search the library semantically, by meaning, in addition to
> keyword search with filters for year, author, venue, tag, and reading
> status.

| File | Role |
|---|---|
| `app/Http/Controllers/SearchController.php` | Both modes, one route |
| `app/Services/VectorSearchService.php` | Cosine similarity, access scoping |
| `app/Services/EmbeddingService.php` | The local 256-dimension embedder |
| `app/Services/PaperService.php` | Keyword search and all five filters |
| `resources/views/search/index.blade.php` | Search UI and filter controls |

### Req 13 — Related papers

> The system recommends related papers from the user's own library based on
> content similarity.

| File | Role |
|---|---|
| `app/Services/VectorSearchService.php` | `relatedPapers()` — centroid comparison |
| `app/Http/Controllers/AiController.php` | `related()` — JSON endpoint |
| `resources/views/components/ai/related.blade.php` | The related-papers card |

### Req 14 — Literature-review draft

> The Users can generate a structured literature-review draft from selected
> papers or a whole collection, and save it.

| File | Role |
|---|---|
| `app/Services/RagService.php` | `draftReview()` — structure, prompt, persistence |
| `app/Models/LiteratureReview.php` | The saved draft |
| `resources/views/components/ai/review.blade.php` | Per-paper checkboxes, Select all / None |

### Req 15 — Citation export

> The Users can export citations in BibTeX, APA, and plain-text formats,
> individually or for an entire collection.

| File | Role |
|---|---|
| `app/Services/CitationService.php` | All three formats, plus `citationKey()` |

`FORMATS` is `['bibtex', 'apa', 'text']` — exactly the three the proposal
names.

### Supporting: the indexing pipeline

Requirements 11–13 are only possible because a paper's text is extracted,
chunked and embedded first. That pipeline is included so the module is
readable end to end:

| File | Role |
|---|---|
| `app/Services/PdfTextService.php` | Text extraction, plus run-on-text repair |
| `app/Services/ChunkService.php` | Overlapping chunks |
| `app/Services/IndexingService.php` | Orchestration, index status |
| `app/Jobs/IndexPaper.php` | Runs it off the request |
| `app/Models/PaperChunk.php` | A chunk and its embedding |
| `database/migrations/..._create_ai_tables.php` | Schema for all of the above |

---

## Running the tests

From the **project root**, not this folder:

```bash
php artisan test --filter="AiLayer|SemanticSearch|SearchRelevance|ReviewSelection|CitationMatching|CitationService|EmbeddingService|ChunkService|RunOnText"
```

That is **76 tests, 181 assertions**, all passing. Those nine test files are
the Module 3 suite and are copied into `code/tests/`. The whole application
suite is 375 tests.

---

## Demonstrating each feature

Start the app from the project root (`php artisan serve`), sign in, and make
sure at least two papers have an uploaded PDF that finished indexing — the
paper page shows the index status, and **Re-index** retries a failed one.

| Requirement | Where to see it |
|---|---|
| 11 | A collection page → the **Ask this collection** card. Answers carry citation links back to the papers used. |
| 12 | `/search` → type a query, switch the toggle to **Semantic**. Filters for year, author, venue, tag and status sit alongside. |
| 13 | Any paper page → the **Related papers** card. |
| 14 | A collection page → the **Literature review** card. Tick some papers, or leave them all ticked for the whole collection. Saved drafts list below it. |
| 15 | A paper page → **BibTeX / APA / Text** links. A collection page → the same three under Export. |

**Requirements 12 and 13 need no API key** — they run entirely on the local
embedder. **Requirements 11 and 14 do**: set `GROQ_API_KEY` in `.env`, or they
report "not configured". Free keys come from
[console.groq.com](https://console.groq.com/keys).

See [`features.md`](features.md) for the operating limits that apply during a
demo — indexing requirements, what the embeddings can and cannot match, and
the provider's rate limit.

---

## Keeping this folder current

If any of these files change in the app, re-run:

```powershell
.\sync.ps1
```

It wipes and rebuilds `code/` from the live source, and exits non-zero if a
listed file has been renamed or deleted.

# ScholarDesk — architecture

> ### 📐 The diagrams live in **[`visual-architecture.html`](visual-architecture.html)**
> Open it in a browser — real drawings, not ASCII. This file is the prose
> companion: the same decisions written out, with the reasoning and the file
> paths. Figure numbers below refer to that page.

*Route-level detail and SQL: [`wiring.md`](wiring.md).*

---

## 1. At a glance

| | |
|---|---|
| **Stack** | PHP 8.3+, Laravel 13, Blade + Alpine + Tailwind, SQLite locally / Postgres deployed |
| **Shape** | Server-rendered monolith with a thin JSON surface for four fetch-driven widgets |
| **Code** | 16 feature controllers + 9 auth controllers, 16 services, 15 models, 6 policies, 8 enums, 58 Blade views |
| **Schema** | 25 tables, 22 migrations |
| **Routes** | 87 registered — 84 application, plus `/up` and two framework storage fallbacks |
| **Async** | 1 queued job (`IndexPaper`), `QUEUE_CONNECTION=sync` by default |
| **External I/O** | Crossref / OpenAlex / DataCite / arXiv (metadata), Groq or Gemini (generation) |
| **Deployment** | Render (`render.yaml`) or Fly (`fly.toml`), Docker either way |

### The three entry points

Everything begins in one of three files:

- [bootstrap/app.php](../bootstrap/app.php) — the application object: routing,
  middleware stack, exception handlers.
- [routes/web.php](../routes/web.php) — every feature route, 156 lines.
- [routes/auth.php](../routes/auth.php) — the Breeze authentication scaffold,
  `require`d at the bottom of `web.php`.

There is no `api.php`. The JSON endpoints live in `web.php` alongside the HTML
ones and are separated by convention (`/ai/*`, `/papers/{paper}/highlights`,
`/notifications/unread-count`) rather than by a route file — which is why
`shouldRenderJsonWhen` in `bootstrap/app.php` has to name `ai/*` explicitly.

→ **Figure 1** shows the whole system: browser, the five subsystems, the
database it owns, and the two outside services it treats as optional.

---

## 2. The layer cake

Nine layers, each with one rule that keeps it honest. The rules are what make
the codebase navigable; without them the layers would exist on paper only.

| # | Layer | The rule that keeps it honest |
|---|---|---|
| 1 | **Route** — `routes/web.php` | Names a URL and an action. No logic, ever. |
| 2 | **Middleware** — `auth`, `SetLocale`, `EnsureUserIsNotSuspended`, `admin`, `throttle` | Answers *may this **request** proceed at all?* |
| 3 | **FormRequest** — `StorePaperRequest`, … | The controller never runs on bad input. |
| 4 | **Controller** — 16 of them | Authorise, delegate, return. **No queries.** |
| 5 | **Policy** — 6 of them | Answers *may this **user** do this to this **row**?* |
| 6 | **Service** — 16 of them | The only layer that knows *how*. |
| 7 | **Model** — 15 of them | A scope is a named, reusable `WHERE` clause. |
| 8 | **Database** | 25 tables. |
| 9 | **View** — 58 Blade templates | Renders what it is handed. No queries. |

**Why a service layer at all.** So that "where does a paper get created?" has
exactly one answer.
[`PaperController::store`](../app/Http/Controllers/PaperController.php#L132-L145)
is thirteen lines and contains no query;
[`PaperService::createForUser`](../app/Services/PaperService.php#L62-L124)
holds all of it — the metadata lookup, the duplicate-DOI check, the open-access
PDF fetch, the row, and the indexing dispatch. Two controllers call it, and they
get identical behaviour for free.

**The one deliberate exception.** Three controllers build queries inline:
`CollectionController::index/show`, `AdminController` throughout, and
`TagController::index`. Those are list-and-count reads with no branching logic
to extract — putting `Collection::accessibleBy(...)->withCount(...)` behind a
service method would add a layer without adding a decision.

---

## 3. Boot sequence

[bootstrap/app.php](../bootstrap/app.php) does four things, and each exists
because of a specific failure it prevents.

| Configuration | What it prevents |
|---|---|
| `web(append: [SetLocale, EnsureUserIsNotSuspended])` | A suspension that only takes hold when the session expires — with a remembered login, potentially weeks after an administrator acted. |
| `alias(['admin' => EnsureUserIsAdmin])` | — (gates the admin group) |
| `trustProxies(at: '*')` | An HTTPS-terminating tunnel forwards plain HTTP, so `asset()` and `route()` emit `http://` onto an `https://` page and the browser blocks the CSS as mixed content. |
| `render(PostTooLargeException)` | A raw 413 stack trace. PHP discards the request before validation runs — `$request->all()` and even the CSRF token are gone — so the referring page is the only safe destination. |
| `shouldRenderJsonWhen(… \|\| is('ai/*') \|\| …)` | A validation error on a fetch-driven endpoint coming back as an HTML redirect the front end cannot parse. |

**Suspension runs globally, not at the login gate.** That is the one worth
remembering: `EnsureUserIsNotSuspended` sits in the `web` group, so it fires on
every authenticated request.

---

## 4. Request lifecycle

→ **Figure 2** draws all nine stages, the four exits, and the `afterResponse()`
tail.

The thing the figure makes visible: **three different gates, asked in order**,
and conflating them is the usual way an authorization bug gets in.

| Gate | Question | A wrong answer costs |
|---|---|---|
| Middleware | May this **request** proceed? | Anonymous access to the whole feature |
| FormRequest | Is this **input** well-formed? | A 500 where a 422 belonged |
| Policy | May this **user** touch this **row**? | One user reading another's library |

The response side matters too. `IndexPaper::dispatch($id)->afterResponse()`
([PaperService.php:197](../app/Services/PaperService.php#L197)) runs *after* the
bytes have gone. With `QUEUE_CONNECTION=sync` it executes inline in the same PHP
process — but a 40-page PDF never adds seconds to the upload the user is
watching.

---

## 5. Subsystems

→ **Figure 1** places all five over the shared spine.

| Subsystem | Owns |
|---|---|
| 📚 **Library** | `PaperService`, `MetadataService`, `CitationService` |
| 📖 **Reader & annotation** | `PdfTextService`, the `Highlight` and `Note` JSON endpoints |
| 🤖 **AI / RAG** | `RagService`, `VectorSearchService`, `EmbeddingService`, `ChunkService`, `IndexingService`, `AiService` |
| 👥 **Collaboration** | `CollectionService`, `ActivityService`, `NotificationService`, `ExportService` |
| 🛡️ **Admin & analytics** | `AnalyticsService`, `AdminController`, `ReportController` |

Two dependency facts worth knowing:

- **`EmbeddingService` has two callers on opposite sides of the system** —
  `IndexingService` at write time, `VectorSearchService` at read time. Both must
  hash text identically or retrieval silently returns nothing. That shared
  dependency is why the embedder is a service and not a method on either one.
- **`ActivityService` and `NotificationService` are used by four subsystems and
  depend on nothing.** Deliberate: they are called from inside other people's
  transactions, so they must never fail in a way that rolls one back.

---

## 6. The domain model

25 tables. The hub is `papers`; `collections` is the sharing mechanism; `users`
owns almost everything.

**Many-to-many:** `collection_paper`, `paper_tag`.
**Self-referencing:** `comments.parent_id` (one level only), `users.suspended_by`.
**Polymorphic:** `reports.reportable` → `Comment` or `Paper`.
**Laravel's own:** `sessions`, `password_reset_tokens`, `cache`, `cache_locks`,
`jobs`, `job_batches`, `failed_jobs`.

### The three access boundaries

→ **Figure 3** draws the first two over the same eight papers, so the
difference is visible rather than described.

Almost every security question in this codebase reduces to picking the right one.
They are not interchangeable.

| Boundary | SQL | Means |
|---|---|---|
| **`Paper::ownedBy`** [:118](../app/Models/Paper.php#L118) | `WHERE user_id = ?` | "My library" — the listing, filters, every analytics figure. A shared paper does **not** appear, which is correct: someone else's paper is not in your library. |
| **`Paper::accessibleBy`** [:129-138](../app/Models/Paper.php#L129-L138) | `user_id = ?` **OR** `EXISTS (collections → collection_members WHERE user_id = ?)` | The **retrieval boundary**. What search and the AI layer may see. |
| **Policies** — `app/Policies/*.php` | per-action verbs on a row already in hand | `view` / `read` / `useAi` / `comment` → owner **or** collaborator. `update` / `delete` / `annotate` → owner **only**. |

**Why the middle one is load-bearing.** `VectorSearchService` resolves it to a
concrete paper-id list *before* any scoring happens. Retrieval is the last step
before text reaches a language model, so an unscoped query there would leak one
user's papers into another user's answer.

**Why the third one splits where it does.** A collaborator can open a shared PDF
and discuss it, but cannot highlight it — highlights and notes are one person's
private working material. A comment is the opposite: it is *addressed* to the
other people who can see the paper.

### Two independent role systems

| Global — `users.role` | Collection-local — `collection_members.role` |
|---|---|
| `researcher` (default) | `viewer` — read papers, notes, comments, feed |
| `administrator` (promoted by an admin) | `editor` — + add/remove papers, comment, run AI |
| Gate: `EnsureUserIsAdmin` | `owner` — + manage members, rename, delete |
| | Gate: `Collection::userCanAtLeast()` via `CollectionPolicy` |

**An administrator is not automatically a collection owner.**
`CollectionPolicy` never consults `isAdmin()` — an admin cannot silently read
your private collection. The single place the two systems meet is
`CommentPolicy`: `delete` admits an admin as moderation, and `moderate` is
admin-only.

---

## 7. The AI pipeline

Two flows that never run at the same time: one writes vectors, the other reads
them.

### 7a. Ingest — PDF to retrievable chunks

→ **Figure 4**, including every failure branch.

| Stage | Class | Detail |
|---|---|---|
| Store + create | `PaperService::createForUser` | file → `public` disk; row created |
| Dispatch | `PaperService::queueIndexing` [:190](../app/Services/PaperService.php#L190) | `afterResponse()`; a dispatch failure is logged, not thrown |
| Extract | `PdfTextService::extract` | scanned → `no_text`; malformed → `error` |
| Chunk | `ChunkService` | 4000 chars, 400 overlap, breaking at a sentence boundary |
| Embed | `EmbeddingService` | word tokens + character trigrams → 256 dims, log-TF weighted, L2-normalised |
| Store | `IndexingService::storeText` [:54](../app/Services/IndexingService.php#L54) | one transaction: delete old chunks, insert in batches of 50, set `index_status` |

**Every failure is recorded on the paper rather than thrown**, so one bad PDF
degrades that one paper's AI features and nothing else. The 400-character
overlap means a passage spanning a chunk boundary is still fully present in at
least one chunk.

### 7b. Query — question to cited answer

→ **Figure 5**, including the three score thresholds on a single scale.

| Step | What happens |
|---|---|
| Embed | Same hashing as ingest. An all-zero vector returns early — no DB work at all. |
| **Resolve the boundary** | `accessiblePaperIds()` → a concrete id list, **before** scoring |
| Rank | Cosine over every accessible chunk, in PHP. Exact, not approximate. O(n), under a second for a personal library. |
| Budget | `max(2000, promptCharBudget() × 0.75)` — derived from the provider's per-minute token allowance, so a small free tier *shrinks the context* instead of producing a rejected request |
| Generate | `AiService::generate` → Groq or Gemini. Failure → `AiUnavailableException` → 503 |
| Cite | `answerCites()` keeps only papers the model actually referenced |
| Persist | Both turns → `chat_messages`; the question is written **first**, so a provider failure leaves a consistent session |

**The three thresholds, three audiences:**

| Constant | Value | Applies to |
|---|---|---|
| `MIN_RETRIEVAL_SCORE` | 0.01 | chunks fed to the **model** |
| `MIN_DISPLAY_SCORE` | 0.15 | results shown to a **human** |
| `WEAK_MATCH_SCORE` | 0.32 | below this the UI **labels** it weak |

A higher floor was tried and rejected. It did suppress off-topic queries, but it
also dropped short on-topic ones — "tumour detection in scans" scores 0.28
against a paper plainly about exactly that, because four words share little
surface with a long passage. Losing a paper the user knows is there is worse than
showing one they can dismiss at a glance.

**Why the citation filter is a regex.** The prompt asks for `[P12]`. Models
drift to 【P12】, (P12), **P12**. Matching the literal string missed real
citations and silently fell back to listing every retrieved source.
`/\bP12\b/i` fixes it, and the word boundary stops `P1` matching `P12`.

**Why the conversation is persisted.** `PaperController::show` and
`PaperController::read` both call `chatHistoryFor()`, so a question asked in the
reader is still there on the detail page and vice versa — one `ChatSession` per
(user, scope, paper|collection).

---

## 8. Cross-cutting concerns

### Authorization matrix

| Policy | Verbs | Granted to |
|---|---|---|
| `PaperPolicy` | `view`, `read`, `useAi`, `comment` | owner **or** collaborator (`view` also admin) |
| | `update`, `delete`, `annotate` | owner only |
| `CollectionPolicy` | `view` | viewer+ |
| | `managePapers`, `comment`, `useAi` | editor+ |
| | `update`, `delete`, `manageMembers` | owner only |
| `CommentPolicy` | `update` | author only — *admins do not edit others' words* |
| | `delete` | author **or** admin |
| | `moderate` (hide/unhide) | admin only |
| `NotePolicy` / `HighlightPolicy` / `TagPolicy` | `update`, `delete` | author/owner only |

Two routes skip policies deliberately:

- **`POST /reports`** — anyone who can *see* content may report it. Requiring
  ownership would mean only a comment's author could report their own comment,
  which is not a moderation system. Visibility is still checked, because
  refusing to confirm the existence of another user's private paper matters.
- **`POST /ai/ask`** — no row to authorize against. The `accessibleBy` boundary
  inside `VectorSearchService` is the whole control.

### Fan-out: activity and notification

Every collaborative mutation fires both, and both are non-fatal by design:

- `ActivityService::record()` → an `activities` row. **Never throws** — a feed
  row failing to write must not roll back the real action it describes.
- `NotificationService::notify()` → a `notifications_inapp` row (the source of
  truth) **plus** an email (best-effort, swallowed and logged). A down SMTP
  server must never break the request that triggered the notification.

Two mutations fan out to *every collection holding the paper*, not one:
`PaperController::updateStatus` and `CommentController::storePaper`. A paper can
sit in several collections, and to the collaborators watching any of those
feeds, a status change on a paper inside it is exactly the event the feed exists
to surface.

### Rate limiting

`throttle:20,1` wraps five AI routes and nothing else.
`GET /ai/papers/{p}/related` sits deliberately *outside* the group — it is pure
local vector arithmetic with no provider call, so limiting it would restrict a
free operation.

### Localisation

`SetLocale` resolves user preference → session → `config('app.locale')`, with
`lang/en` and `lang/bn` supplying the strings.
`SettingsController::updateLocale` writes both the column and the session, so
the change applies to the response that performs it rather than the one after.

### The failure policy, stated once

The same decision recurs in six places, and it is the most defensible thing
about the design: **an enhancement failing must never break the thing it
enhances.**

| Component | On failure | What survives |
|---|---|---|
| `AiService` (no key / provider down) | `isConfigured()` false, or 503 | library, reader, notes, search, export, collaboration |
| `IndexPaper` / `IndexingService` | status recorded on the paper | the paper itself, minus retrieval |
| `MetadataService` | returns `null` | the paper is created; details typed by hand |
| `ActivityService::record` | swallowed | the mutation it describes |
| `NotificationService` email | swallowed + logged | the in-app notification row |
| `PaperService::queueIndexing` | logged warning | the uploaded paper |

---

## 9. Storage and deployment

| Concern | Where |
|---|---|
| PDFs | `Storage::disk('public')` → `storage/app/public/papers/`. On Render the symlink is re-created at boot, because the container filesystem is rebuilt on every deploy. |
| Exports | `storage/app/tmp/*.zip` with `deleteFileAfterSend(true)` — without it, storage grows by a full copy of the collection on every download. |
| Embeddings | `paper_chunks.embedding`, a JSON array of 256 floats |
| Sessions / cache | database driver |
| Queue | `sync` by default; the one job runs `afterResponse()` |

Local runs on SQLite; deployed runs on Postgres. Two places that difference is
visible in application code:

- [`App\Support\Search`](../app/Support/Search.php) picks `LIKE` or `ILIKE` per
  driver, so keyword search is case-insensitive on both, and escapes wildcards
  so a search for `50%` does not match every row.
- `IndexingService` inserts chunks in batches of 50 to stay under SQLite's
  bound-variable limit.

---

## 10. The seams

Where to cut if a requirement changes. Each is one class.

| Change | Touch only |
|---|---|
| Real neural embeddings | `EmbeddingService` — storage format (JSON floats) unchanged |
| pgvector instead of PHP scoring | `VectorSearchService` — the class docblock says so explicitly |
| A different LLM provider | `AiService::generate` — every provider call funnels through it, which is also what makes the AI layer fakeable in tests |
| Another metadata registry | `MetadataService`, add a resolver to the chain |
| A new citation format | `CitationService::FORMATS` + one method; no DB or HTTP access, so it is directly unit-testable |
| Real email verification | `User implements MustVerifyEmail` **and** a real mailer, then re-add `verified` to the route group — the comment at [routes/web.php:25-39](../routes/web.php#L25-L39) explains why half the change is worse than none |

---

*Diagrams: [`visual-architecture.html`](visual-architecture.html) ·
Route detail and SQL: [`wiring.md`](wiring.md)*

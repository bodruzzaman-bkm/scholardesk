# ScholarDesk — route and query wiring

> ### 📐 For the **drawings**, open **[`visual-wiring.html`](visual-wiring.html)**
>
> | Figure | Shows |
> |---|---|
> | 1 | **Four rings** — every URL, placed by what gates it |
> | 2 | **The filter funnel** — a query string narrowing 240 papers to 4, and the SQL it emits |
> | 3 | **Two engines** — keyword vs semantic, and the one real difference |
> | 4 | **Who writes what** — writers → tables, with the four single-writer ones |
> | 5 | **Comment anchoring** — three valid states, and the one-level rule |
> | 6 | **Upload trace** — swimlanes, with the response boundary as a clock |
> | 7 | **Question trace** — swimlanes, with the access check in red |
>
> This file is the text version: greppable, with every route, the SQL each scope
> produces, and the traces written out step by step.

*System shape: [`architecture.md`](architecture.md) ·
[`visual-architecture.html`](visual-architecture.html)*

---

## Contents

1. [Route index](#1-route-index) — all 84, five columns
2. [Query wiring](#2-query-wiring) — scope → SQL → callers
3. [Reverse index](#3-reverse-index) — table → who writes it
4. [View ↔ route wiring](#4-view--route-wiring)
5. [Three traces](#5-three-traces)

**Reading the tables.** Every route also carries the global stack — session,
CSRF, `SetLocale`, `EnsureUserIsNotSuspended` — so only *extra* middleware is
listed. `—` means nothing, on purpose; the note under each table says why.

---

## 1. Route index

→ **[visual-wiring.html](visual-wiring.html) Figure 1** places every URL in one
of four gating rings, which is the fastest way to answer "what protects this?"

### Library — 12

| Route | Gate | Validation | Service | Writes |
|---|---|---|---|---|
| `GET /papers` | auth | — | `paginateLibrary` | — |
| `GET /papers/create` | auth | — | — | — |
| `POST /papers` | auth | `StorePaperRequest` | `createForUser` → `MetadataService` | `papers` + queues indexing |
| `POST /papers/batch` | auth | `StorePapersBatchRequest` | `createManyFromUploads` | `papers` ×N |
| `GET /papers/{p}` | `view` | — | — | — |
| `GET /papers/{p}/edit` | `update` | — | — | — |
| `PUT /papers/{p}` | `update` | `UpdatePaperRequest` | `PaperService::update` *(txn)* | `papers`, `collection_paper`, `paper_tag` |
| `DELETE /papers/{p}` | `delete` | — | `PaperService::delete` | `papers` + the PDF on disk |
| `PATCH /papers/{p}/status` | `update` | inline `Enum` | `setStatus` + `ActivityService` ×N | `papers`, `activities` |
| `GET /papers/{p}/read` | `read` | — | — | — |
| `GET /papers/{p}/export` | `view` | `?format=` allowlist | `CitationService::format` | — |
| `POST /papers/{p}/reindex` | `update` | — | `queueIndexing` | rebuilds `paper_chunks` |

> `papers.store` has **no policy** — there is no existing row to authorize
> against. The owner is `Auth::id()`, set server-side, never taken from the
> request.

### Search — 1

| Route | Gate | Validation | Service | Writes |
|---|---|---|---|---|
| `GET /search` | auth | `?mode=` → `keyword`\|`semantic` | `paginateLibrary` **or** `VectorSearchService::searchPapers` | — |

→ **[visual-wiring.html](visual-wiring.html) Figure 3** draws the fork.

One route, two engines. The boundary differs on purpose:

| | Keyword | Semantic |
|---|---|---|
| Engine | `Paper::scopeSearch` → `LIKE`/`ILIKE` | `EmbeddingService` + cosine over `paper_chunks` |
| Fields | title, authors, abstract, venue, doi | the extracted full text |
| Boundary | `ownedBy` — **your library only** | `accessibleBy` — **+ shared collections** |
| Result | paginated | top 15, filtered ≥ 0.15, weak matches labelled |

Semantic search reaches into shared collections because that is where the AI
layer's material lives.

### Collections — 10

| Route | Gate | Validation | Service | Writes |
|---|---|---|---|---|
| `GET /collections` | auth | — | inline `accessibleBy` + `withCount` | — |
| `POST /collections` | auth | `StoreCollectionRequest` | `CollectionService::create` *(txn)* | `collections`, `collection_members` *(owner row)* |
| `GET /collections/{c}` | `view` viewer+ | — | `ActivityService::forCollection` | — |
| `PUT /collections/{c}` | `update` owner | `UpdateCollectionRequest` | — | `collections` |
| `DELETE /collections/{c}` | `delete` owner | — | detach, then delete | `collection_paper`, `collections` — **papers survive** |
| `POST …/papers` | `managePapers` editor+ | inline | `addPapers` — **re-checks each paper** | `collection_paper`, `activities`, `notifications_inapp` |
| `POST …/papers/upload` | `managePapers` | `StorePapersBatchRequest` | `createManyFromUploads` + `addPapers` | `papers`, `collection_paper`, `activities` |
| `DELETE …/papers/{p}` | `managePapers` | — | `removePaper` | `collection_paper` only — **paper survives** |
| `GET …/export` | `view` | `?format=` allowlist | `formatMany` | — |
| `GET …/bundle` | `view` | — | `collectionArchive` | — *(zip: PDFs + notes + bibliography)* |

### Collaborators — 3

| Route | Gate | Validation | Service | Writes |
|---|---|---|---|---|
| `POST …/members` | `manageMembers` owner | inline `email`, `Enum` | `addMember` | `collection_members`, `activities`, `notifications_inapp` |
| `PATCH …/members/{m}` | `manageMembers` + `assertBelongs` | inline `Enum` | `changeMemberRole` | `collection_members` |
| `DELETE …/members/{m}` | `manageMembers` + `assertBelongs` | — | `removeMember` | `collection_members`, `activities` |

Three structural guards, each closing a specific hole:

| Guard | Closes |
|---|---|
| `role === Owner → Editor` | ownership handed out through the invite form |
| `assertBelongs()` → 404 | a member id from *another* collection — route-model binding resolves `{member}` independently of `{collection}` |
| the owner's row is immutable | an owner demoting or removing themselves and orphaning the collection |

### Reader and annotation — 4 · JSON

| Route | Gate | Validation | Writes |
|---|---|---|---|
| `GET /papers/{p}/highlights` | **`read`** | — | — |
| `POST /papers/{p}/highlights` | **`annotate`** | hex colour, page ≥ 1, 1–200 rects | `highlights` |
| `PATCH /highlights/{h}` | `update` author | inline | `highlights` |
| `DELETE /highlights/{h}` | `delete` author | — | `highlights` |

> **The `read`/`annotate` asymmetry is the fix, not a bug.** The reader page
> admits collaborators, but this endpoint used to demand owner-only `annotate`,
> so a collaborator opening a shared PDF got a 403 banner on a page they were
> entitled to use. Relaxing it leaks nothing: the query is already
> `WHERE user_id = Auth::id()`, so their list is simply empty, and `annotate`
> still stops them creating one.

**Rect keys are a contract.** `position.rects[].left/top/width/height` are what
`getClientRects()` produces and what `renderPdfHighlights()` reads back. They
are stored *unscaled* — divided by the capture-time zoom — so a highlight lands
correctly at any later zoom. Renaming them silently breaks re-rendering.

### Notes — 4

| Route | Gate | Validation | Service | Writes |
|---|---|---|---|---|
| `POST /papers/{p}/notes` | `annotate` owner | max 20 000 | `ActivityService` ×N collections | `notes`, `activities` |
| `GET /notes/{n}/edit` | `update` author | — | — | — |
| `PUT /notes/{n}` | `update` author | inline | — | `notes` |
| `DELETE /notes/{n}` | `delete` author | — | — | `notes` |

> The activity row records *that* a note was written, never its content — notes
> stay private to their author even inside a shared collection.

### AI — 6 · JSON

| Route | Gate | Throttle | Service chain | Writes |
|---|---|---|---|---|
| `POST /ai/ask` | **—** | 20/min | `askLibrary` → `search('library')` | `chat_sessions`, `chat_messages` |
| `POST /ai/papers/{p}/summary` | `useAi` | 20/min | `summarizePaper` → `AiService` | — |
| `POST /ai/papers/{p}/ask` | `useAi` | 20/min | `askPaper` → `search('paper')` | `chat_messages` |
| `POST /ai/collections/{c}/ask` | `useAi` editor+ | 20/min | `askCollection` | `chat_messages` |
| `POST /ai/collections/{c}/review` | `useAi` editor+ | 20/min | `draftReview` → `Activity` + `Notification` | `literature_reviews`, `activities`, `notifications_inapp` |
| `GET /ai/papers/{p}/related` | `view` | **none** | `relatedPapers` — local maths | — |

Three deliberate asymmetries:

1. **`ai.ask` has no policy.** There is no row to authorize. The
   `Paper::accessibleBy` boundary inside `VectorSearchService` *is* the control.
2. **`related` sits outside the throttle group.** No provider call, no cost —
   rate-limiting it would restrict a free operation.
3. **`review` validates `paper_ids.*` with
   `Rule::exists('collection_paper', 'paper_id')->where('collection_id', $c->id)`**,
   not `exists:papers,id`. Without the `where`, a crafted id could pull an
   unrelated paper into the draft.

Every handler wraps its work in `AiController::guard()`, turning
`AiUnavailableException` into a **503 with a readable message** — the rule that
AI never becomes a hard dependency.

### Comments — 4

| Route | Gate | Validation | Writes |
|---|---|---|---|
| `POST /collections/{c}/comments` | `comment` editor+ | + 2 × `Rule::exists` | `comments`, `activities`, `notifications_inapp` |
| `POST /papers/{p}/comments` | `comment` owner-or-collaborator | + 1 × `Rule::exists` | `comments`, `activities`, `notifications_inapp` |
| `PUT /comments/{c}` | `update` **author only** | max 5000 | `comments` |
| `DELETE /comments/{c}` | `delete` author **or** admin | — | `comments` |

**The one-level thread rule, enforced in validation.** Both store methods
constrain `parent_id` with `->whereNull('parent_id')`:

```php
// collection thread — parent must be a root IN THIS COLLECTION
Rule::exists('comments', 'id')
    ->where('collection_id', $collection->id)
    ->whereNull('parent_id')

// paper thread — parent must be a root ON THIS PAPER
Rule::exists('comments', 'id')
    ->where('paper_id', $paper->id)
    ->whereNull('parent_id')
```

The Blade component recurses and only one level of replies is eager-loaded, so
every extra level cost another query and another indent. The view already only
rendered a reply form on roots — but a hand-made POST could reply to a reply
until this landed.

**Comment anchoring** — → **[visual-wiring.html](visual-wiring.html) Figure 5**
draws these three states alongside the depth rule:

| `collection_id` | `paper_id` | Means |
|---|---|---|
| set | null | a collection thread |
| null | set | a paper thread |
| set | set | a paper thread inside a collection |

### Reports — 1

| Route | Gate | Validation | Writes |
|---|---|---|---|
| `POST /reports` | **visibility only** — `view` on the target's paper or collection | `type` ∈ allowlist, `id`, `reason` ≤ 500 | `reports` *(updateOrCreate)* |

- **`REPORTABLE` is a class allowlist** (`comment`, `paper`), not a
  `reportable_type` from the request — otherwise a caller could point a report
  at any model in the application.
- **`updateOrCreate`, not `create`.** The unique index
  `(user_id, reportable_type, reportable_id)` means a second report from the
  same person would be a 500 rather than a no-op. Re-reporting refreshes the
  reason and reopens a dismissed report.

### Notifications — 4 · Tags — 4

| Route | Gate | Writes |
|---|---|---|
| `GET /notifications` | rooted at `$request->user()` | — |
| `GET /notifications/unread-count` | rooted at `$request->user()` | — *(JSON)* |
| `POST /notifications/{n}/read` | `abort_unless(user_id === me, 403)` | `notifications_inapp` |
| `POST /notifications/read-all` | rooted at `$request->user()` | `notifications_inapp` |
| `GET /tags` | auth | — |
| `POST /tags` | auth · `StoreTagRequest` | `tags` |
| `PUT /tags/{tag}` | `update` owner | `tags` |
| `DELETE /tags/{tag}` | `delete` owner | `paper_tag` *(detach)*, `tags` |

> No `NotificationPolicy` — every query is already rooted at
> `$request->user()->inAppNotifications()`, and the one route taking an id
> guards it inline. A policy class would restate the relationship without
> adding a rule.

### Admin — 8 · all behind `admin`

| Route | Validation | Writes |
|---|---|---|
| `GET /admin` | — | — *(11 counts + a disk scan)* |
| `GET /admin/users` | `?q=` | — |
| `PATCH /admin/users/{u}/role` | `Enum(UserRole)` + **self-guard** | `users` |
| `PATCH /admin/users/{u}/suspension` | `reason` ≤ 500 + **self-guard** | `users` |
| `GET /admin/comments` | — | — |
| `PATCH /admin/comments/{c}/visibility` | `moderate` *(belt-and-braces)* | `comments` |
| `GET /admin/reports` | `?status=` | — |
| `PATCH /admin/reports/{r}` | `Rule::in(resolved, dismissed)` | `reports` |

Three moderation decisions the wiring encodes:

- **Suspension, not deletion.** Deleting a user cascades through their papers,
  collections, notes, highlights and comments — destroying a library to silence
  an account, irreversibly. A suspended user keeps everything and simply cannot
  sign in; `EnsureUserIsNotSuspended` makes it take hold on the next request.
- **The suspension columns are absent from `$fillable`** and assigned
  explicitly. They are a privileged decision, not user-editable data.
- **Resolving a report does not hide the content.** That stays a separate,
  deliberate action — an administrator can judge a report valid and still leave
  the content up.

`toggleCommentVisibility` calls `authorize('moderate')` even though `admin`
already gates it. `CommentPolicy` declared moderation as admin-only and nothing
consulted it, which made the policy a comment rather than a rule.

### Dashboard, analytics, profile, settings — 8 · Auth — 15

| Route | Gate | Writes |
|---|---|---|
| `GET /` | — | — |
| `GET /dashboard` | auth | — *(`AnalyticsService` ×6)* |
| `GET /analytics` | auth | — *(`AnalyticsService` ×6)* |
| `GET /profile` · `PATCH /profile` | auth · `ProfileUpdateRequest` | `users` |
| `DELETE /profile` | auth · `current_password` | `users` + full cascade |
| `GET /settings` | auth | — |
| `PATCH /settings/locale` | auth · `Enum(Locale)` | `users` + session |
| 15 auth routes | `guest` or `auth` | `users`, `sessions`, `password_reset_tokens` |

> `/dashboard` and `/analytics` overlap on purpose. `/dashboard` is a *working
> surface* — continue reading, recently added, jump back in. `/analytics`
> answers "what does my library look like?" and carries the venue breakdown the
> dashboard omits.

> **The verification routes are wired but inert.** `App\Models\User` does not
> implement `MustVerifyEmail`, so nothing sends a user there. The `verified`
> middleware was removed from the feature routes for exactly that reason —
> [web.php:25-39](../routes/web.php#L25-L39) documents the two-part change
> needed to switch it on. A route advertising a protection it does not apply is
> worse than one claiming less than it does.

---

## 2. Query wiring

### `Paper` scopes — [Paper.php:118-229](../app/Models/Paper.php#L118-L229)

| Scope | SQL | Reached from |
|---|---|---|
| `ownedBy($id)` | `WHERE user_id = ?` | `papers.index`, keyword `search`, `papers.edit`, `dashboard`, `analytics`, all 8 analytics methods |
| `accessibleBy($id)` | `user_id = ?` **OR** `EXISTS (collections → collection_members WHERE user_id = ?)` | `collections.show`, `addPapers`, **every `VectorSearchService` path** — so all 6 AI routes and semantic search |
| `search($term)` | `(title LIKE ? OR authors LIKE ? OR abstract LIKE ? OR venue LIKE ? OR doi LIKE ?)` — `ILIKE` on Postgres, wildcards escaped | `papers.index`, `search` |
| `withTag($id)` | `WHERE EXISTS (paper_tag → tags WHERE tags.id = ?)` | `papers.index`, `search` |
| `withStatus($s)` | `WHERE reading_status = ?` — value checked against the enum first | `papers.index`, `search` |
| `withYear($y)` | `WHERE year = ?` | `papers.index`, `search` |
| `withAuthor($a)` | `WHERE authors LIKE %?%` — substring, because `authors` is one comma-separated string, not a relation | `papers.index`, `search` |
| `withVenue($v)` | `WHERE venue = ?` | `papers.index`, `search` |
| `inCollection($id)` | `WHERE EXISTS (collection_paper WHERE collections.id = ?)` | `papers.index`, `search` |
| `sorted($s)` | `match`: `title` → `ORDER BY title`; `year` → `ORDER BY year IS NULL, year DESC`; `oldest` → `ORDER BY created_at`; default → `DESC` | `papers.index`, `search` |

**`sorted()` is a whitelist, not a passthrough.** A `match` over four known
values means a query string can never order by an arbitrary column. Same
principle in `withStatus`, which checks enum membership before building a clause.

**`ORDER BY year IS NULL, year DESC`** puts undated papers last instead of
first — `NULL` sorts high on some drivers and low on others, and a library
imported from DOIs has plenty of them.

### Other scopes

| Scope | SQL | Reached from |
|---|---|---|
| `Collection::ownedBy` | `WHERE user_id = ?` | `papers.index`, `papers.edit`, `headlineStats` |
| `Collection::accessibleBy` | ownership `OR EXISTS (collection_members)` | `collections.index`, `search` filters, `accessiblePaperIds` |
| `Comment::roots` | `WHERE parent_id IS NULL` | `papers.show`, `collections.show` — without it every reply also renders as its own thread |
| `Report::open` / `withStatus` | `WHERE status = ?` | `admin.index`, `admin.reports` |
| `Tag::ownedBy` | `WHERE user_id = ?` | `tags.index`, `papers.index`, `papers.edit`, `search`, `topTags` |
| `InAppNotification::unread` | `WHERE is_read = false` | `notifications.count` |

### `AnalyticsService` — 8 aggregations, every one `ownedBy`-scoped

| Method | Query shape | Used by |
|---|---|---|
| `headlineStats` | 5 × `COUNT(*)` | dashboard, analytics |
| `readingStatusBreakdown` | `GROUP BY reading_status` — one grouped query, not three `COUNT`s, then zero-filled from the enum | dashboard, analytics |
| `papersByYear` | `WHERE year IS NOT NULL GROUP BY year ORDER BY year DESC LIMIT 10` | dashboard, analytics |
| `papersByVenue` | `WHERE venue != '' GROUP BY venue ORDER BY COUNT DESC LIMIT 10` | analytics |
| `topTags` | `withCount('papers')->whereHas('papers')->orderByDesc('papers_count')` | dashboard, analytics |
| `papersAddedByMonth` | `WHERE created_at >= start`, grouped **in PHP** by `Y-m`, then pre-filled so quiet months render as zero rather than as gaps | dashboard (6), analytics (12) |
| `continueReading` | `WHERE reading_status = 'reading' ORDER BY updated_at DESC LIMIT 1` | dashboard |
| `recentPapers` | `with('tags') ORDER BY created_at DESC LIMIT 5` | dashboard |

`papersByVenue` excludes blank venues rather than bucketing them as "Unknown":
a paper whose DOI resolved no venue says nothing about where the user publishes,
and a large Unknown bar would dominate the chart without meaning anything.

`topTags` uses `whereHas('papers')` rather than `having('papers_count', '>', 0)`
— `withCount` builds a correlated subquery, and SQLite rejects `HAVING` on a
query with no `GROUP BY`.

### The retrieval query — `VectorSearchService::rank`

→ **[visual-architecture.html](visual-architecture.html) Figure 5** draws this,
including the three thresholds on one scale.

1. **Resolve the boundary first.** `accessiblePaperIds(scope, scopeId, userId)`:
   - `'paper'` → `Paper::accessibleBy()->whereKey($id)->first(['id'])`
   - `'collection'` → `Collection::accessibleBy()->whereKey($id)->papers()->pluck('papers.id')`
   - `'library'` → `Paper::accessibleBy()->pluck('id')`
2. Subtract `$excludePaperId` (for `relatedPapers`). Empty list → return, **no
   DB work at all**.
3. `SELECT * FROM papers WHERE id IN (…)` → `keyBy('id')`
4. `SELECT * FROM paper_chunks WHERE paper_id IN (…) AND embedding IS NOT NULL`
5. Score each chunk in PHP, filter `> 0.01`, `sortByDesc`, `take(k)`

| Constant | Value | Applies to | Why |
|---|---|---|---|
| `MIN_RETRIEVAL_SCORE` | 0.01 | chunks fed to the **model** | anything above shares *some* signal, and the model is instructed to say when the excerpts do not answer |
| `MIN_DISPLAY_SCORE` | 0.15 | results shown to a **human** | feature-hashed embeddings have a baseline from trigrams shared by any two pieces of English prose; off-topic queries land around 0.26–0.29 |
| `WEAK_MATCH_SCORE` | 0.32 | UI **label** | a higher floor was tried and rejected — it also dropped short on-topic queries. Losing a paper the user knows is there is worse than showing one they can dismiss |

---

## 3. Reverse index

### Table → routes that write it

| Table | Written by |
|---|---|
| `papers` | `papers.store/storeBatch/update/destroy/status`, `collections.papers.upload`, + `IndexPaper` (`full_text`, `indexed_at`, `index_status`) |
| `collection_paper` | `papers.update` *(sync)*, `collections.papers.add/upload/remove`, `collections.destroy` *(detach)* |
| `paper_tag` | `papers.update` *(sync)*, `tags.destroy` *(detach)* |
| `collections` | `collections.store/update/destroy` |
| `collection_members` | `collections.store` *(owner row)*, `collections.members.add/update/remove` |
| `tags` | `tags.store/update/destroy` |
| `notes` | `notes.store/update/destroy` |
| `highlights` | `highlights.store/update/destroy` |
| `comments` | `comments.store`, `papers.comments.store`, `comments.update/destroy`, `admin.comments.visibility` |
| `reports` | `reports.store`, `admin.reports.resolve` |
| `users` | `register`, `profile.update/destroy`, `password.update/store`, `settings.locale`, `admin.users.role/suspension`, `verification.verify` |
| **`paper_chunks`** | **only `IndexingService::storeText`** |
| **`activities`** | **only `ActivityService::record`** — 8 call sites |
| **`notifications_inapp`** | **only `NotificationService`** — 6 call sites |
| **`chat_sessions` / `chat_messages`** | **only `RagService`** |
| `literature_reviews` | only `RagService::draftReview` |

→ **[visual-wiring.html](visual-wiring.html) Figure 4** draws writers → tables,
with the four single-writer edges picked out. They are the load-bearing rows: `paper_chunks` having exactly one writer
is why re-indexing can safely delete and rebuild wholesale, and `activities` /
`notifications_inapp` having one each is what lets those writers be
non-throwing without any caller needing to know.

### Who can touch `collection_members`?

The question the reverse index exists for.

| Path | Guard |
|---|---|
| `CollectionService::create()` | owner row, inside the create transaction |
| `CollectionService::addMember()` | `updateOrCreate`, owner-gated |
| `CollectionService::changeMemberRole()` | refuses the owner's own row |
| `CollectionService::removeMember()` | refuses the owner's own row |
| migration `2026_08_21_170000` | backfill, `insertOrIgnore` so re-running is safe |

Every runtime path is `CollectionPolicy::manageMembers` → **owner only**.
Read paths: `Collection::roleFor` / `userCanAtLeast` / `memberUsers`,
`Paper::scopeAccessibleBy`, `Collection::scopeAccessibleBy`.

### Service → its callers

| Service | Called from |
|---|---|
| `PaperService` | `PaperController` (7 methods), `CollectionController::uploadPapers` |
| `CollectionService` | `CollectionController`, `CollectionMemberController`, `CommentController` |
| `RagService` | `AiController` only |
| `VectorSearchService` | `AiController`, `SearchController`, `RagService` |
| **`EmbeddingService`** | `VectorSearchService` (read) **and** `IndexingService` (write) — **must agree** |
| `AiService` | `RagService`, plus `isConfigured()` from four controllers |
| `ActivityService` | `PaperController`, `NoteController`, `CommentController`, `CollectionController`, `CollectionService`, `RagService` |
| `NotificationService` | `CollectionService`, `CommentController`, `RagService`, `IndexPaper`, `NotificationController` |
| `AnalyticsService` | `DashboardController`, `AnalyticsController` |
| `CitationService` | `PaperController::export`, `CollectionController::export`, `ExportService` |
| `MetadataService` | `PaperService` only |
| `IndexingService` | `IndexPaper` job, demo seeder |

---

## 4. View ↔ route wiring

### The four `fetch()` call sites — the implicit API

The only places the app behaves like an SPA, and why `shouldRenderJsonWhen` has
to include `ai/*`.

| View | Line | Calls | Purpose |
|---|---|---|---|
| [components/ai/related.blade.php](../resources/views/components/ai/related.blade.php#L57) | 57 | `GET ai.paper.related` | related-papers panel, loaded lazily |
| [components/ai/runtime.blade.php](../resources/views/components/ai/runtime.blade.php#L21) | 21 | all 5 POST `ai.*` routes | the shared AI client |
| [layouts/navigation.blade.php](../resources/views/layouts/navigation.blade.php#L179) | 179 | `GET notifications.count` | unread badge on the bell |
| [papers/read.blade.php](../resources/views/papers/read.blade.php#L534) | 534 | `highlights.*` | the reader's annotation layer |

`runtime.blade.php` is the single AI client — `summary`, `chat` and `review` all
delegate to it, so the 503-handling that keeps AI from becoming a hard
dependency is written once.

### Most-linked routes

| Route | Links | Where |
|---|---|---|
| `papers.index` | 17 | nav, breadcrumbs, every redirect target |
| `papers.show` | 14 | library cards, search results, related panel, notifications, activity feed |
| `tags.index`, `search`, `papers.create`, `logout`, `collections.index`, `admin.index` | 5 each | primary nav |

---

## 5. Three traces

### 5.1 Uploading a PDF

→ **[visual-wiring.html](visual-wiring.html) Figure 6** draws this as swimlanes,
with the response boundary marked.

1. **`POST /papers`** — multipart, `file=attention.pdf`
2. **Global stack** — session → CSRF → `SetLocale` → `EnsureUserIsNotSuspended`;
   `auth` redirects a guest to login
3. **`StorePaperRequest::rules()`** [:40](../app/Http/Requests/StorePaperRequest.php#L40)
   - `file` — `required_without:identifier`, `mimetypes:application/pdf`, `max:10240`
   - `identifier` — `required_without:file`, plus
     `Rule::unique('papers','doi')->where('user_id', Auth::id())` **only for a
     DOI**; a URL's DOI is unknown until resolved
4. **`PaperController::store`** — no `authorize()`, because there is no row yet
5. **`PaperService::createForUser`** [:62](../app/Services/PaperService.php#L62)
   - `$file->store('papers', 'public')`
   - `MetadataService::lookup()` — DOI → Crossref → OpenAlex → DataCite;
     URL → arXiv id → DOI in path → `citation_*` meta tags. **A miss returns
     `null` and the paper is still created.**
   - resolved DOI already owned? → `ValidationException` on `identifier`, not a
     unique-constraint 500
   - open-access `pdf_url` and no upload? → `storeFetchedPdf()`. An OA PDF is the
     difference between importing a *citation* and importing a *readable paper*.
   - `Paper::create(...)` — **user input wins over registry metadata**
   - `queueIndexing()` → `IndexPaper::dispatch()->afterResponse()`
6. **302 to `papers.show`** — the user is already looking at the paper
7. **After the response:** `IndexingService` extracts → chunks → embeds →
   transaction. Failures are recorded on the paper, never thrown.

**Writes:** `papers`, `paper_chunks`, `notifications_inapp`.

### 5.2 Asking the library a question

→ **[visual-wiring.html](visual-wiring.html) Figure 7** draws this as swimlanes,
with the access check picked out in red.

1. **`POST /ai/ask`** — `auth` + `throttle:20,1`
2. **`AiController::askLibrary`** — validate 3–1000 chars. **No `authorize()`**:
   there is no row; the boundary below is the control. `guard()` turns
   `AiUnavailableException` into a 503 the UI can render.
3. **`VectorSearchService::search(q, userId, 'library', null, 6)`**
   - `EmbeddingService::embed(question)` — all-zero → return, no DB work
   - `accessiblePaperIds('library')` → **the access boundary, before scoring**
   - `rank()` — cosine over every chunk, `> 0.01`, top 6
4. **No hits** → `AiUnavailableException` *"Nothing in your library matched…"* —
   a real answer, not a 500
5. **`sessionFor()`** → `firstOrCreate` on (user, scope, paper, collection)
6. **`RagService::answer()`** [:244](../app/Services/RagService.php#L244)
   - build context under `contextBudget()`, stopping when spent rather than
     sending an over-long prompt the provider will reject
   - **record the question first**, so a provider failure leaves a consistent
     session
   - `AiService::generate()` → Groq or Gemini
   - `answerCites()` — `/\bP12\b/i`, because models drift to 【P12】, (P12),
     **P12**
   - record the assistant turn with the citations actually used

**Writes:** `chat_sessions`, `chat_messages`.

### 5.3 Adding a collaborator

→ **[visual-architecture.html](visual-architecture.html) Figure 7** draws this,
including the checks that change afterwards.

1. **`POST /collections/7/members`** — `auth`; `{collection}` binds or 404s
2. **`authorize('manageMembers')`** → `Collection::userCanAtLeast(Owner)` →
   `roleFor()`, which treats the creator as owner even if the membership row is
   missing
3. **Validate** `email`, `Enum(MemberRole)`; **`Owner` is downgraded to
   `Editor`** — ownership is structural, not handed out through a form
4. **`CollectionService::addMember`** [:118](../app/Services/CollectionService.php#L118)
   - no such user → `ValidationException`; target is the owner →
     `ValidationException`
   - `CollectionMember::updateOrCreate([collection_id, user_id], [role])` — so
     re-inviting changes the role rather than hitting the unique index
   - `ActivityService::record(MemberAdded)` — never throws
   - `NotificationService::notify(Share, link)` — in-app row is the source of
     truth, email is best-effort

**What changes immediately, with no further wiring:**

| Check | Now returns |
|---|---|
| `Collection::accessibleBy` | the collection appears in their list |
| `Paper::accessibleBy` | its papers become retrievable |
| `PaperPolicy::read` | they can open the PDFs |
| `PaperPolicy::useAi` | they can ask questions of them |
| `PaperPolicy::comment` | they can join the discussion |
| **`PaperPolicy::annotate`** | **still false** — highlights stay the owner's |

That is the point of the whole access design: adding one row to
`collection_members` changes what six separate checks return, because all six
read membership from the same place.

---

*Diagrams: [`visual-architecture.html`](visual-architecture.html) ·
System shape: [`architecture.md`](architecture.md)*

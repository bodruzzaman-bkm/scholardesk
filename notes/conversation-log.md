# Conversation log

A running record of what was asked and what was answered, newest entry at the
bottom. One numbered entry per conversation, in a fixed shape:

```
## NNN — YYYY-MM-DD · <topic>

**Question**      the question, verbatim
**Answer**        what was actually answered
**Visuals**       the diagrams produced, and where they live
**Docs touched**  files created or changed
**Open threads**  anything left hanging for next time
```

Entries are never rewritten. If a later conversation corrects an earlier one,
the correction gets its own entry and links back — the log is a history, not a
summary.

---

## 001 — 2026-09-07 · Expanded architecture and the wire map

**Question**

> okay! the project is done. show me the expanded architecture. And a
> wirelinking type architecture between the routes/features/quesries. we will
> discuss a lot on this project so create a doc file where you will show my
> question and your answers, visuals against it after every conversation

**Answer**

Three things were needed, and none of them existed yet.

The `module 1..4/features.md` documents are organised **by requirement number**
— they prove coverage but cannot answer "how does the system work?".
[`docs/viva-notes.md`](../docs/viva-notes.md) has a 14-line request-lifecycle
sketch and prose around it. Neither contains a wire map.

So: [`architecture.md`](architecture.md) for the system's shape, and
[`wiring.md`](wiring.md) for the traceability — all 84 application routes, each
one traced from URL through middleware, validation, policy, service and Eloquent
scope down to the tables it touches.

**What the survey found**

| | |
|---|---|
| Routes | 87 registered — 84 application + `/up` + 2 storage fallbacks |
| Controllers | 16 feature + 9 auth |
| Services | 16 |
| Models | 15 · Policies 6 · Enums 8 · Views 58 |
| Schema | 25 tables, 22 migrations |
| Async | 1 queued job (`IndexPaper`), `afterResponse()` |

**The five things worth knowing about this codebase**

1. **Three access boundaries, not one.** `Paper::scopeOwnedBy` is "my library".
   `Paper::scopeAccessibleBy` is ownership *or* membership of a collection
   holding the paper — the retrieval boundary, which every AI route and semantic
   search runs through. Policies are per-action verbs on top of either. Picking
   the wrong one is how an authorization bug gets in.

2. **`PaperPolicy` splits read from write along the collaboration seam.**
   `view` / `read` / `useAi` / `comment` admit collaborators; `update` /
   `delete` / `annotate` are owner-only. A collaborator can open a shared PDF
   and discuss it but cannot highlight it, because highlights and notes are one
   person's private working material.

3. **The AI layer resolves its boundary before it scores anything.**
   `VectorSearchService::accessiblePaperIds()` produces a concrete id list
   first; only then are chunks loaded and cosine-scored. Retrieval is the last
   step before text reaches a language model, so an unscoped query there would
   leak one user's papers into another user's answer.

4. **Failure is designed, not incidental.** The same decision recurs six times:
   AI unavailable → 503 and everything else still works; a bad PDF → status
   recorded on that one paper; metadata miss → `null`, type it in;
   `ActivityService::record` never throws; notification email is best-effort.
   An enhancement failing must never break the thing it enhances.

5. **Four tables have exactly one writer each** — `paper_chunks`, `activities`,
   `notifications_inapp`, `chat_messages`. That is what lets re-indexing delete
   and rebuild wholesale, and lets the activity and notification writers be
   non-throwing without any caller needing to know.

**Two asymmetries that look like bugs and are not**

- `GET /papers/{p}/highlights` is gated on `read`, while `POST` on the same URI
  is gated on `annotate`. The reader page admits collaborators, but this
  endpoint used to demand owner-only `annotate` — so a collaborator opening a
  shared PDF got a 403 banner on a page they were entitled to use. The query is
  already `WHERE user_id = Auth::id()`, so relaxing it leaks nothing.
- `GET /ai/papers/{p}/related` sits outside the `throttle:20,1` group that wraps
  the other five AI routes. It is pure local vector arithmetic with no provider
  call, so rate-limiting it would restrict a free operation.

**Visuals**

| Diagram | Format | Lives in |
|---|---|---|
| Layer cake — 9 layers, one rule each | ASCII | `architecture.md` §2 |
| Boot sequence | ASCII | `architecture.md` §3 |
| Request lifecycle, incl. the `afterResponse()` tail | ASCII | `architecture.md` §4 |
| Subsystem map — 5 subsystems over a shared spine | Mermaid | `architecture.md` §5 |
| ERD — 25 tables | Mermaid | `architecture.md` §6 |
| The three access boundaries | ASCII | `architecture.md` §6 |
| AI ingest pipeline | ASCII | `architecture.md` §7a |
| AI query pipeline | ASCII | `architecture.md` §7b |
| Master wire graph — routes → controllers → policies → services → tables | Mermaid | `wiring.md` §2 |
| Keyword vs semantic search split | ASCII | `wiring.md` §3.3 |
| Retrieval query + the three score thresholds | ASCII | `wiring.md` §4.4 |
| `collection_members` reverse index | ASCII | `wiring.md` §5.2 |
| Navigation graph | ASCII | `wiring.md` §6.1 |
| Trace: uploading a PDF | ASCII | `wiring.md` §7.1 |
| Trace: asking the library a question | ASCII | `wiring.md` §7.2 |
| Trace: adding a collaborator | ASCII | `wiring.md` §7.3 |

**Docs touched**

- `notes/architecture.md` — new, 10 sections
- `notes/wiring.md` — new, 7 sections, all 85 routes
- `notes/conversation-log.md` — new, this file
- `notes/README.md` — new
- `.git/info/exclude` — `notes/` added, so none of this reaches GitHub

**Note on placement.** You asked for these not to be pushed. `notes/` is
excluded via `.git/info/exclude` rather than `.gitignore`, because `.gitignore`
is itself tracked — a line about these files would be pushed even though the
files were not. `git status` stays clean.

**Open threads**

- Verification routes exist but are inert: `User` does not implement
  `MustVerifyEmail`, so the `verified` middleware was removed rather than left
  claiming a protection it did not apply. Turning it on needs both halves — the
  interface *and* a real mailer.
- Embeddings are lexical (256-dim feature hashing), not neural. The seam is one
  class (`EmbeddingService`); the storage format would not change.

---

## 002 — 2026-09-07 · Real drawings instead of ASCII

**Question**

> figures and tables are not understandable. make it easy to understand. like a
> drawing, not ascii

**Answer**

Fair. Entry 001 leaned on ASCII box-art and nine-column tables — both are hard
to parse, and the tables in particular were reference material pretending to be
explanation. Replaced with actual drawings.

**What changed**

| | Before | After |
|---|---|---|
| Diagrams | 16 ASCII blocks | 7 hand-authored SVG figures in a browser page |
| Route tables | 9 columns | 4 columns (HTML) / 5 columns (markdown) |
| Reading order | two long markdown files | one visual page, two text companions |

New file: **[`visual-architecture.html`](visual-architecture.html)** — a
self-contained page, no build step, no extension needed. Adapts to light and
dark. Every figure is one claim with a caption saying what it shows.

The markdown files were rewritten to match: all ASCII art removed, tables cut
down, and pointers added to the figure that draws each idea.

**Why SVG rather than Mermaid**

Mermaid needs a renderer. VS Code does not preview it without an extension, so a
Mermaid block in a markdown file is *also* unreadable text on this machine — the
exact problem being reported. Hand-authored SVG in an HTML file renders in any
browser with nothing installed.

**The seven figures**

| # | Shows | The claim it carries |
|---|---|---|
| 1 | The whole system | Both outside services are dashed — nothing solid depends on anything dashed |
| 2 | Request lifecycle | Three gates ask three different questions; confusing them is how an authz bug gets in |
| 3 | `ownedBy` vs `accessibleBy` | Same eight papers, two different answers — drawn rather than described |
| 4 | Ingest pipeline | One bad PDF degrades one paper and nothing else |
| 5 | Query pipeline | The three thresholds on a single 0→1 scale, with the band each governs |
| 6 | Single-writer tables | Four tables, one writer each — and the promise each writer can therefore make |
| 7 | One route end to end | One row in `collection_members` moves five access checks |

**Visuals** — all seven live in `visual-architecture.html`. The markdown files
now carry tables and prose only.

**Docs touched**

- `notes/visual-architecture.html` — **new**, 7 figures + route reference
- `notes/architecture.md` — rewritten, ASCII removed, figure pointers added
- `notes/wiring.md` — rewritten, tables cut from 9 columns to 5
- `notes/README.md` — reordered so the visual page comes first
- `notes/conversation-log.md` — this entry

**Verified** — all 7 SVG fragments parse as strict XML; the HTML parses with no
structural errors (7 figures, 7 captions, 8 tables, 71 rows). Rendering was
checked by opening the page, not by an automated visual test.

**Open threads**

- Both open threads from entry 001 still stand (inert email verification;
  lexical embeddings).
- The figures are static. If a diagram would be more useful interactive —
  clicking a route to expand its trace, say — that is a different build.

---

## 003 — 2026-09-07 · The same treatment for the wiring doc

**Question**

> fix it too

*(said with `wiring.md` open in the editor — read as "give wiring.md the
drawings that architecture.md got")*

**Answer**

Entry 002 gave `architecture.md` a visual companion but left `wiring.md` as
tables and prose. Built **[`visual-wiring.html`](visual-wiring.html)** — seven
more figures, same design system, so the two pages read as one set.

**The seven new figures**

| # | Shows | The claim it carries |
|---|---|---|
| 1 | **Four gating rings** | Find your URL, and you have read off everything that must pass before the controller runs |
| 2 | **The filter funnel** | `ownedBy` is the boundary; the other eight scopes only narrow what it already returned |
| 3 | **Two search engines** | One route, two paths — and the *only* real difference is which boundary each uses |
| 4 | **Writers → tables** | Four tables have one writer each, drawn as single thick edges |
| 5 | **Comment anchoring + depth** | Three valid anchor states; reply-to-a-reply rejected by validation, not just hidden by the view |
| 6 | **Upload trace** | Swimlanes with the response boundary as a clock — left of it the user waits, right of it they don't |
| 7 | **Question trace** | Swimlanes with the access check in red, because for `/ai/ask` it *is* the whole access control |

**Two figures that carry a point the tables could not**

- **Figure 2** puts real numbers on the funnel — 240 → 48 → 17 → 9 → 4. The
  table said `ownedBy` is the access boundary; the drawing shows it doing four
  fifths of the narrowing before any user filter runs.
- **Figure 6**'s dashed vertical line is a clock. It makes `afterResponse()`
  legible as *"the user already has the page"* rather than as an API detail.

**Docs touched**

- `notes/visual-wiring.html` — **new**, 7 figures
- `notes/wiring.md` — figure table at the top; eight cross-references now name
  which page each figure is on
- `notes/README.md` — both visual pages listed first, with their figure indexes
- `notes/conversation-log.md` — this entry

**Verified** — 7/7 SVG fragments parse as strict XML; HTML structure clean
(7 figures, 7 captions). Opened in the browser; not visually diffed.

**Open threads**

- Unchanged from 001 and 002: inert email verification, lexical embeddings.
- 14 figures now live across two pages. If they start drifting from the code, the
  fix is to regenerate rather than patch — they were built from a full read of
  the routes, controllers, services and migrations, and that read is cheap to
  repeat.

---

## 004 — 2026-09-07 · The masterclass

**Question**

> 1. there are 22 features. briefly explain each of the features implementations
>    & wiring codebase part by part. where the codebase are allocated, how it has
>    been implemented, how it works with an example for each feature.
> 2. Then briefly explain the RAG & AI implementation codebase & wiring.
> 3. interactive architecture for each query
>
> Do your best to teach me to the core of this project

**Answer**

Built **[`masterclass.html`](masterclass.html)** — one page, three parts,
matching the three asks.

**Part 1 — the 22 requirements.** Pulled the real list from
`module N/features.md` rather than inventing one; the split is M1 = 1–6,
M2 = 7–10, M3 = 11–15, M4 = 16–22. Every feature is a card with six sections:
*what it does · where it lives · how it works · worked example · wiring · why it
was built that way*. 88 file references, all verified to resolve. Filterable by
keyword, expand/collapse all.

The sixth section is the one that matters for a viva. It carries the reasoning —
why `annotate` is owner-only while `comment` is not, why deleting a tag detaches
instead of cascading, why `/ai/ask` has no policy, why suspension exists instead
of deletion.

**Part 2 — RAG and AI.** The six classes and what each owns; why the embeddings
are lexical and what that costs; the prompt-budget arithmetic written out; why
the citation filter is a regex; and the five failure modes with what still works
under each. One figure: the AI layer drawn as three stacked bands, showing that
cutting the remote provider costs generation but not retrieval.

**Part 3 — the interactive explorer.** 14 representative queries across 8
groups. Click one and the page renders its full trace: 121 stages total, each
with the layer, the concrete value, the file:line, and what happens on failure.
Gates are marked in red; stages that are deliberately absent are marked as such
rather than omitted. Every route shows the SQL it actually emits.

**Two things I checked rather than assumed**

- The libxml HTML validator reported 74 "unexpected end tag" errors. They were
  all inside the `<script>` block — libxml2 doesn't implement HTML5's script-data
  state. Confirmed the file contains exactly one `</script>`, so no JS string
  breaks out, then syntax-checked the 64 KB of JavaScript with `node --check`
  and evaluated the data model to confirm it loads.
- All 145 referenced paths resolve, every line number is within its file, and
  ten spot-checks confirm the line lands on the symbol it names.

**One bug found and fixed:** a Cyrillic word (`другой`) had slipped into the
semantic-search trace text. Caught by a non-ASCII sweep, not by reading.

**Docs touched**

- `notes/masterclass.html` — **new**, ~86 KB, 22 features + RAG + explorer
- `notes/README.md` — masterclass listed first as the teaching entry point
- `notes/conversation-log.md` — this entry

**Open threads**

- The explorer covers 14 of 84 routes — chosen to cover every *shape* (no-policy,
  owner-gated, role-gated, admin-gated, JSON, throttled, fan-out). Adding the
  rest is mechanical if it would help.
- Unchanged from earlier entries: inert email verification, lexical embeddings.

---

## 005 — 2026-09-07 · Published to GitHub

**Question**

> push all the updates here https://github.com/vaskargit04/SDesk

**Answer**

This reversed entry 001's constraint (*"dont push them in the repo. save them
just in this folder not in github"*), so I checked before acting. Confirmed:
**code + notes**.

**What the repo actually looked like**

| | |
|---|---|
| `mine` | `vaskargit04/SDesk` — the target |
| `origin` | `bodruzzaman-bkm/scholardesk` — a *different* repo, which `main` was tracking |
| Divergence | ahead 3, behind 1 (`e9b3e14 docs directory`, pushed by someone else) |
| Working tree | clean — the 3 pending commits were the Render fixes, nothing from this session |

So "all the updates" meant two separate things: three already-made commits that
had never been pushed anywhere, and the `notes/` folder, which was excluded.

**Done before committing**

- Removed `notes/` from `.git/info/exclude`.
- **Corrected `notes/README.md`**, which claimed *"Local only — nothing here
  reaches GitHub"* and a "Why not .gitignore" section explaining the mechanism.
  Both were about to become false. Committing a document that lies about its own
  status is worse than not committing it.
- Swept `notes/` for credentials before publishing — no API keys, tokens or
  passwords. `.env` remains ignored and was never in scope.

**Pushed to `mine` only.** `origin` belongs to someone else and is untouched;
`main` still tracks it, and is still behind by their `docs directory` commit.

**Docs touched**

- `.git/info/exclude` — `notes/` entry removed
- `notes/README.md` — the local-only claims replaced with a provenance note
- `notes/conversation-log.md` — this entry

**Open threads**

- **`main` is still behind `origin/main` by one commit.** Pulling it is a
  separate decision — it merges a teammate's work into your branch, so it should
  be deliberate rather than a side effect of a push.
- `main` tracks `origin`, not `mine`, so future pushes to your own repo need
  `git push mine main` explicitly unless the tracking is changed.
- Unchanged: inert email verification, lexical embeddings.

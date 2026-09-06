# ScholarDesk — how it works

**Viva preparation.** CSE470.

The four `module N/features.md` documents map every requirement to a file and
a line. They are the right shape for *checking* the project and the wrong
shape for *defending* it — organised by requirement number, so nothing in them
answers "how does the system work?" or "what happens between a click and a row
in the database?"

An examiner asks across those seams. This note is the synthesis.

| | |
|---|---|
| Stack | PHP 8.3+, Laravel 13, Blade, SQLite (Postgres when deployed) |
| Size | 26 controllers, 16 services, 15 models, 6 policies, 8 enums, 58 Blade views |
| Schema | 25 tables, 22 migrations |
| Tests | 393 passing, 1,154 assertions |
| AI | Retrieval-augmented generation over a local embedder; Groq for generation |

---

## 1. Architecture

### The path a request takes

```
  HTTP request
       |
   [ Route ]              routes/web.php — names the URL and the action
       |
   [ Middleware ]         auth -> SetLocale -> EnsureUserIsNotSuspended -> admin
       |
   [ FormRequest ]        validation, before the controller runs at all
       |
   [ Controller ]         thin: authorise, delegate, return
       |
   [ Policy ]             may this user do this to this record?
       |
   [ Service ]            the domain logic — the only layer that knows *how*
       |
   [ Model / Eloquent ]   relationships, scopes, casts
       |
   [ Database ]
       |
   [ Blade view ]  <----  data flows back up
```

**Why a service layer at all?** So a controller never contains a query. Ask
"where does a paper get created?" and the answer is one place —
`PaperService::createForUser()` — not spread across a controller, a job and a
console command. `PaperController::store()` is nine lines: validate via a
FormRequest, call the service, redirect.

**Why policies?** So an access rule lives in one place. `PaperPolicy::view()`
defines who may see a paper, and the search, the reader, the export and the AI
all consult that same definition. Without it, each would re-derive the rule
and one of them would eventually get it wrong.

**Why enums?** `ReadingStatus::Read` instead of `'read'`. A typo in a string
is a silent bug; a typo in an enum case is a fatal error at the point of the
mistake.

### MVC, honestly

Laravel is MVC, but the interesting question is what went *where*:

| Layer | Holds | Does **not** hold |
|---|---|---|
| **Model** | relationships, query scopes, casts | business rules, HTTP concerns |
| **View** (Blade) | presentation, one component per repeated element | queries, decisions |
| **Controller** | validate → authorise → delegate → respond | queries, domain logic |
| **Service** | the domain logic | HTTP, view rendering |

A query scope is the seam worth being able to defend. `Paper::scopeAccessibleBy($userId)`
lives on the model because it is a *statement about papers*; who may call it is
a policy question; when to call it is a service question.

---

## 2. The database

25 tables. The shape that matters:

```
                users
                  |
      +-----------+-----------+---------------+
      |           |           |               |
   papers    collections    tags          reports (morph)
      |           |           |
      |     collection_paper (pivot)
      |     collection_members
      |
   +--+------+---------+-----------+
   |         |         |           |
 notes  highlights  comments   paper_chunks
                       |            |
                  (parent_id)   embedding JSON
                   self-ref
```

### Relationship types, and where each is used

| Type | Example | Why |
|---|---|---|
| One-to-many | `User` → `papers` | A paper has exactly one owner |
| **Many-to-many** | `papers` ↔ `collections` via `collection_paper` | A paper lives in several collections at once |
| Many-to-many | `papers` ↔ `tags` via `paper_tag` | Same reason |
| **Self-referencing** | `comments.parent_id` → `comments.id` | Threading: a reply is a comment |
| **Polymorphic** | `reports.reportable_type` + `reportable_id` | A report targets a comment *or* a paper |
| One-to-many | `Paper` → `paper_chunks` | The RAG index |

**The pivot table is the answer to requirement 5.** "A paper can be in more
than one collection, and removing it from one must not delete it from the
library" is not a feature you write code for — it falls out of the schema.
`removePaper()` calls `$collection->papers()->detach($paper->id)`, which
deletes a *pivot row*. The paper is untouched.

### Delete behaviour

Every foreign key cascades except two, and the exceptions are deliberate:

| Column | Rule | Why |
|---|---|---|
| `reports.resolved_by` | `SET NULL` | Losing a moderator's account must not reopen every report they settled |
| `users.suspended_by` | `SET NULL` | …nor reinstate everyone they suspended |

`reports.reportable_id` carries **no** foreign key at all — a polymorphic
column cannot, because the target table is not known at schema time. The
consequence is handled in the view: a report whose target was deleted renders
as "the reported content has since been deleted" rather than throwing on a
null relation.

### Three schema decisions with a story

**1. DOI uniqueness was global.** `$table->string('doi')->unique()` meant that
once *one* researcher added a paper, **no other user could ever add it**. Now
unique on `(user_id, doi)`.

**2. `comments.collection_id` was `NOT NULL`.** Requirement 18 asks for
comments on "shared collections *and* individual papers" — but a paper
belonging to no collection could not be commented on at all. The second half
was *unrepresentable*, not merely unbuilt. Made nullable, so a comment now has
exactly one anchor:

```
collection_id set, paper_id null  ->  a collection thread
paper_id set, collection_id null  ->  a paper thread
both set                          ->  a paper thread inside a collection
```

**3. Suspension is a nullable timestamp, not a boolean.** "When" is worth
knowing when reviewing a moderation decision later, and `suspended_at IS NULL`
reads as clearly as a flag.

---

## 3. How the features connect

This is what the per-module documents cannot show, because each stops at its
own boundary.

### Uploading one PDF touches all four modules

```
User uploads a PDF, or pastes a DOI
        |
   StorePaperRequest          validation (Module 1)
        |
   PaperService::createForUser()
        |
        +-- MetadataService::lookup()      Crossref -> OpenAlex -> DataCite
        |     resolves title, authors, year, venue, abstract   (Module 1, req 3)
        |
        +-- storeFetchedPdf()              open-access PDF, SSRF-guarded
        |
        +-- Paper row created
        |
   queueIndexing() -> IndexPaper job, dispatched afterResponse()
        |
        +-- PdfTextService     extract text, repair run-on words
        +-- ChunkService       4,000-char chunks, 400 overlap
        +-- EmbeddingService   256-dim vector per chunk
        +-- paper_chunks rows written
        |
   The paper is now visible to:
        - semantic search            (Module 3, req 12)
        - related papers             (Module 3, req 13)
        - every Q&A route            (Modules 2 and 3)
        |
   NotificationType::AiDone -> the owner is told it is ready   (Module 4, req 20)
```

**Why `afterResponse()` and not a queue worker?** Parsing a large PDF takes
seconds and must not sit in the request. `dispatch()->afterResponse()` runs the
job in the same process *after* the response is sent — so the upload returns
immediately, and there is no worker process to run or pay for. It is also why
the notification exists: by the time indexing finishes, the user has moved on.

### One comment fans out across three subsystems

```
POST /papers/{paper}/comments
        |
   PaperPolicy::comment()      owner OR a collaborator on any collection holding it
        |
   Comment row (collection_id null, paper_id set)
        |
        +-- ActivityService::record()  against EVERY collection holding the paper
        |     so collaborators watching that feed see it        (req 19)
        |
        +-- NotificationService::notifyMany()
              paper owner + members of those collections, minus the commenter
              in-app row (source of truth) + best-effort email  (req 20)
```

The subtlety worth being able to explain: a *paper* thread has no collection,
yet the activity must reach the collections that hold the paper. Recording
against each of them is what makes a comment visible to the people the feed
exists to serve.

### Sharing a collection widens what the AI can see

`CollectionService::addMember()` writes a `collection_members` row. That row is
read by `Collection::scopeAccessibleBy()`, which is read by
`VectorSearchService::accessiblePaperIds()`. So sharing a collection with
someone silently and correctly widens the set of papers their AI answers can
draw on — one membership row, three subsystems.

**The owner is a member row, not a special case.** `CollectionService::create()`
writes an explicit `Owner` membership for the creator, so every access check
reads membership from one place instead of special-casing `owner_id`
everywhere.

---

## 4. RAG and the AI layer

The centrepiece. Everything here is worth being able to defend from first
principles.

### What RAG is, and why

A language model cannot read your library. Two naive options both fail:

- **Send the whole paper.** A 40-page paper is far beyond the context window,
  and the cost scales with every question.
- **Fine-tune a model on your papers.** Needs training infrastructure, and it
  goes stale the moment you add a paper.

**Retrieval-augmented generation** does neither. Index the text once; at
question time, retrieve only the handful of passages most relevant to *that*
question, and put only those in the prompt. The model never sees the library —
it sees six excerpts and a question.

### The pipeline, end to end

```
PDF bytes
   |  PdfTextService          cap 600,000 chars; reject under 200; repair run-on text
raw text
   |  ChunkService            4,000 chars per chunk, 400-char overlap, min 50
chunks
   |  EmbeddingService        256-dim L2-normalised vector each
paper_chunks (content + embedding JSON)
   |
   |  ---- question time ----
   |
question
   |  EmbeddingService        the SAME function embeds the question
query vector
   |  VectorSearchService     cosine similarity against every accessible chunk
top 6 chunks
   |  RagService::answer()    label each [P<id>], assemble prompt within budget
prompt
   |  AiService               Groq, openai/gpt-oss-120b
answer
   |  answerCites()           keep only the papers the answer actually cited
answer + citations
```

### The tuning constants, and why each is what it is

| Constant | Value | Reasoning |
|---|---|---|
| `MAX_CHARS` | 600,000 | A cap on pathological PDFs; beyond this the text is not a paper |
| `MIN_USEFUL_CHARS` | 200 | Less than this means extraction failed — a scan with no text layer |
| `CHUNK_CHARS` | 4,000 | Big enough to hold an argument, small enough that six fit in a prompt |
| `OVERLAP_CHARS` | 400 | So a sentence spanning a boundary is not lost to both chunks |
| `MIN_CHUNK_CHARS` | 50 | A 20-character tail is not worth embedding |
| `DIM` | 256 | Enough to separate a personal library; small enough to score in PHP |
| `TOP_K` | 6 | What fits in the prompt budget alongside the question |
| `MIN_RETRIEVAL_SCORE` | 0.01 | Permissive — the prompt is the safety net |
| `MIN_DISPLAY_SCORE` | 0.15 | Below this is noise, not a result |
| `WEAK_MATCH_SCORE` | 0.32 | Above this is confident; below it is shown but *labelled* |

### The embedding — feature hashing, not a neural model

This is the part most likely to be probed, and the part worth understanding
properly. `EmbeddingService` uses **the hashing trick**. There is no
vocabulary, no training, and no model file.

For each token in the text:

1. **Hash the feature key** with FNV-1a, 32-bit.
2. **Index** = `hash % 256`. That is the dimension it lands in.
3. **Sign** = `hash(key . '#') & 1` → `+1` or `-1`.
4. **Add** `sign × weight` at that index.

Two feature families are added per token:

- `w:<token>` — the word itself, weight `1 + log(count)`
- `t:<trigram>` — every character trigram, weight `0.3 × weight`

Then the whole vector is **L2-normalised** to unit length.

**Why the signed hash?** With 256 dimensions and thousands of distinct
features, collisions are certain. If every colliding feature added a positive
value, collisions would systematically inflate a dimension. A pseudo-random
sign means collisions cancel on average — the error becomes noise around zero
rather than a bias.

**Why `1 + log(count)` rather than raw frequency?** Sublinear scaling. A word
appearing 50 times is more important than one appearing once, but not fifty
times more important. Raw counts let a single repeated word dominate the
vector.

**Why character trigrams?** They give morphological robustness without a
stemmer. "learn", "learning" and "learned" share the trigrams `lea`, `ear`,
`arn` — so they land near each other in vector space. The 0.3 weight keeps
them supporting evidence rather than drowning out whole words.

**Why L2-normalise?** Cosine similarity is `(a·b) / (|a||b|)`. If both vectors
are already unit length, the denominator is 1 and cosine *is* the dot product
— one multiply-add per dimension, no square roots at query time.

### Worked example

Query: `"attention mechanism"` — these are real values from the running code.

**Tokenise** → `["attention", "mechanism"]` (stop words removed, lowercased).

**Hash three of the features:**

| Feature key | FNV-1a (32-bit) | `% 256` → index | Sign |
|---|---|---|---|
| `w:attention` | 952,399,242 | **138** | +1 |
| `t:att` | 1,129,705,934 | **206** | +1 |
| `t:tte` | 2,239,671,498 | **202** | +1 |

Each token appears once, so `weight = 1 + log(1) = 1`. The word feature adds
`+1.0` at index 138; each trigram adds `+0.3` at its own index.

**Result:** a 256-dimension vector with **15 non-zero components**, L2 norm
exactly `1.000000`.

That sparsity is the point — two texts are similar when their *non-zero
dimensions overlap*, which happens when they share words or word-fragments.

**Real retrieval**, same corpus:

| Query | Top score | Verdict |
|---|---|---|
| "artificial intelligence in education" | **0.4627** | strong (≥ 0.32) |
| "machine cognition within pedagogy" | **0.2896** | weak |

Both mean roughly the same thing. The first shares vocabulary with the papers;
the second does not. **That gap is the honest limit of this approach, and it is
reproducible on demand.**

### Retrieval — and the security argument

`VectorSearchService::rank()` scores in PHP:

```php
$paperIds = $this->accessiblePaperIds($scope, $scopeId, $userId);   // FIRST
// ...
return PaperChunk::query()
    ->whereIn('paper_id', $paperIds)                                 // THEN score
    ->whereNotNull('embedding')
    ->get()
    ->map(fn ($chunk) => [... 'score' => $this->embeddings->similarity($queryVector, $chunk->embedding)])
    ->filter(fn ($hit) => $hit['score'] > self::MIN_RETRIEVAL_SCORE)
    ->sortByDesc('score')
    ->take($k);
```

**The ordering is the whole security argument.** Access is resolved to a
concrete paper-id list *before* anything is scored. Retrieval is the last step
before text reaches a language model — an unscoped query here would put one
user's papers into another user's answer, and no amount of prompt engineering
downstream would undo it.

**Why score in PHP and not a vector database?** SQLite has no vector type.
Loading and scoring is O(n) per query rather than index-assisted — but it is
*exact* rather than approximate, and it stays well under a second for a
personal library of a few hundred papers. If this moved to PostgreSQL with
pgvector, only this one class would change.

### Generation, and the citations

Chunks go into the prompt labelled with their paper id:

```
[P12] Attention Is All You Need
<chunk text>
```

The prompt then requires those labels:

> Answer the question using only the excerpts below. Cite the source of each
> claim inline using its bracket label, e.g. [P12]. If the excerpts do not
> contain the answer, say so plainly instead of guessing.

After generation, `answerCites()` scans the answer and keeps only the papers it
actually referenced — so a citation list never claims a source the answer never
used. The regex is deliberately loose about punctuation, because models drift
from `[P12]` into fullwidth brackets or parentheses; `\b` still stops `P1`
matching inside `P12`.

### Failure handling

`AiUnavailableException` → HTTP 503 → the panel shows a message and the rest of
the page keeps working. **The AI is never a hard dependency.**

One provider quirk worth knowing: **Groq reports "tokens per minute exceeded"
as HTTP 413, not 429.** Treating 413 as a generic failure produced "the AI
returned an error" when the truthful message was "you are rate limited". It is
matched explicitly.

### What works without an API key

| Feature | Needs a key? |
|---|---|
| Semantic search (req 12) | **No** — local embedder |
| Related papers (req 13) | **No** — local embedder |
| Citation export (req 15) | **No** — pure formatting |
| Summary, Q&A, review draft | **Yes** — Groq or Gemini |

This is why `/ai/papers/{paper}/related` sits *outside* the `throttle:20,1`
group: it never calls a paid provider, so there is no quota to protect.

---

## 5. Anticipated viva questions

**Why Laravel and Blade rather than React?**
The requirement was a server-rendered MVC application. Blade keeps rendering
on the server, so there is one language, one deployment artefact, and no API
layer to maintain between a front end and a back end. Alpine.js covers the
small amount of genuine interactivity; pdf.js is the only substantial
front-end library.

**How do you stop one user seeing another's papers?**
Three layers. `PaperPolicy` decides per record; `scopeAccessibleBy()` scopes
every query; and in retrieval, access is resolved to a paper-id list *before*
scoring. The third is the one that matters most, because it is the last gate
before text reaches a language model.

**Why 256 dimensions?**
Enough to separate a personal library of a few hundred papers; small enough
that cosine similarity in PHP stays under a second. It is a hashing trick, so
the dimension is a free parameter — not a vocabulary size.

**Why not a vector database?**
SQLite has no vector type, and the corpus is small. Exact O(n) scoring beats
approximate nearest-neighbour at this scale, and the whole vector layer is one
class, so swapping in pgvector later means rewriting `VectorSearchService`
alone.

**What happens if Groq is down?**
`AiUnavailableException` → 503 → the AI panel shows an error and everything
else on the page still works. Semantic search and related papers are
unaffected because they never call a provider.

**Why is search case-insensitive on Postgres specifically?**
SQLite's `LIKE` is case-insensitive for ASCII; Postgres's is case-*sensitive*.
The same code would silently return fewer results after deployment — no error,
just worse search. `App\Support\Search` picks `LIKE` or `ILIKE` per driver.

**What was the hardest bug?**
Highlights failing silently. Validation expected `{x, y, w, h}` while the
reader sent `{left, top, width, height}`, so every save returned 422 — and
because the reader ignored the response body, highlights simply never
reappeared after a reload. The test that should have caught it had encoded the
same wrong assumption. It is now tested with payloads copied verbatim from the
reader's own fetch call.

**What went wrong that you had to fix?**

| Bug | Consequence |
|---|---|
| Registration accepted a `role` field | Anyone could self-register as administrator |
| `doi` unique globally | Once one user added a paper, nobody else could |
| `removePaper` missing a `use` statement | Route-model binding resolved the wrong class — fatal on every call |
| `Str::markdown` allows `javascript:` links | Stored XSS through a note |
| `NotificationType::AiDone` never dispatched | Requirement 20 was two-thirds built and nothing errored |
| No CA bundle in PHP | Every DOI lookup failed silently; papers imported as "Untitled Paper" |

The pattern in the last two is worth naming: **something declared and never
wired**. It throws no error and fails no test, so it can only be found by
sweeping for it — comparing every enum case, policy method and controller
method against its call sites.

**What would you do differently?**
Use real neural embeddings. The lexical embedder was chosen so the app works
with no API key, and that trade held — but it cannot match paraphrase, and the
0.4627-versus-0.2896 gap above is the price. A sentence-transformer model, or
an embedding API, would close it.

**Why is the AI answer trustworthy?**
It is not asked to know anything. It is given six excerpts from the user's own
papers and told to answer from those alone and say so when they do not cover
the question. Every claim is traceable to a cited paper.

---

## Where to look in the code

| Question | File |
|---|---|
| How is a paper created? | `app/Services/PaperService.php` |
| How is metadata resolved? | `app/Services/MetadataService.php` |
| How does chunking work? | `app/Services/ChunkService.php` |
| How does the embedder work? | `app/Services/EmbeddingService.php` |
| How does retrieval work? | `app/Services/VectorSearchService.php` |
| How is a prompt built? | `app/Services/RagService.php` |
| How is a provider called? | `app/Services/AiService.php` |
| Who may do what? | `app/Policies/` |
| What are the URLs? | `routes/web.php` |

Per-requirement detail, with the real code quoted and line numbers verified:
[`module 1/features.md`](../module%201/features.md) ·
[`module 2/features.md`](../module%202/features.md) ·
[`module 3/features.md`](../module%203/features.md) ·
[`module 4/features.md`](../module%204/features.md)

# 📚 ScholarDesk

A research-paper workspace for academics: collect papers, read and annotate
them, organise them into shared collections, and ask questions across the whole
library in plain language.

Built with Laravel and Blade for the CSE470 curriculum.

---

## ✨ Features

### Library

- **Add papers three ways** — upload a PDF, paste a **DOI**, or paste an
  **article URL**. Metadata (title, authors, journal, year, abstract) is
  resolved automatically from Crossref, with OpenAlex and DataCite as
  fallbacks. A URL is resolved from an arXiv id, a DOI in the path, or the
  page's `citation_*` meta tags.
- **Batch upload** — up to 20 PDFs at once, each capped at 10 MB.
- **Search** — keyword search across title, authors, abstract and notes, plus
  **semantic search** that matches on meaning rather than exact words.
- **Filters** — by tag, collection, reading status, year and author.
- **Reading status** — *To read* → *Reading* → *Read*, changed inline from the
  library, the reader, or the paper page.
- **Coloured tags** — see [docs/tags-feature.md](docs/tags-feature.md).
- **Citations** — copy or download **BibTeX, APA and plain text** for one paper
  or a whole collection.
- **Full collection export** — one zip carrying every paper's PDF, a Markdown
  document with your notes, and a BibTeX bibliography.

### Reader

- **In-browser PDF reader** built on pdf.js — no download required.
- **Highlights** — select text to highlight it; highlights persist and are
  listed in a sidebar with connector lines back to the page.
- **Markdown notes** — per-paper notes rendered as sanitised HTML.

### AI assistant (RAG)

Every AI answer is grounded in the text of your own papers, and cites the
chunks it used.

- **Paper summary** — a structured summary of a single paper.
- **Ask this paper** — Q&A scoped to one document.
- **Ask my library** — Q&A across every paper you own, from the dashboard.
- **Ask this collection** — Q&A scoped to a collection.
- **Related papers** — nearest neighbours by embedding similarity.
- **Literature-review draft** — a first draft synthesising a collection.

How it works: a PDF's text is extracted, split into overlapping chunks, and
embedded locally. A question is embedded the same way, the closest chunks are
retrieved by cosine similarity, and those chunks — and only those — are sent to
the language model as context.

### Collaboration

- **Shared collections** with per-member roles (owner / editor / viewer).
- **Threaded comments** on collections and on individual papers.
- **Activity feed** — who added, annotated or changed what.
- **In-app notifications** with an unread badge, plus a matching email.

### Administration

- **Analytics dashboard** — papers added over time, and breakdowns by year,
  venue, tag and reading status.
- **Role-based access** — administrator and researcher.
- **Admin portal** — manage user roles, moderate comments, and work a report
  queue. Any user can flag a comment or a paper for review.
- **Bilingual UI** — English and Bangla, switchable in Settings.

---

## ⚠️ Known limitations

Stated plainly so nobody is surprised in a demo:

- **Embeddings are lexical, not neural.** `App\Services\EmbeddingService` uses
  feature hashing over word tokens and character trigrams into 256 dimensions.
  It matches wording and morphology well ("neural machine translation" finds
  "neural translation models"), but it does **not** understand true paraphrase —
  a query that shares no vocabulary with a paper will score low even when it
  means the same thing. Results below a similarity of 0.32 are shown but
  labelled *weak match*.
- **Generation needs an API key.** Summaries, Q&A and review drafts require
  `GROQ_API_KEY` (or `GEMINI_API_KEY`). Without one they report "not
  configured". Semantic search, related papers and everything else still work.
- **Groq's free tier allows 8,000 tokens per minute**, so several long
  questions in quick succession will be rate-limited for a moment.
- **PDF text quality varies.** Scanned PDFs with no text layer cannot be
  indexed — there is no OCR.

---

## 🛠️ Tech stack

| Layer | Choice |
|---|---|
| Backend | PHP 8.3+, Laravel 13 |
| Views | Blade — no SPA framework |
| Interactivity | Alpine.js, and pdf.js for the reader |
| Styling | Tailwind CSS 3, built with Vite |
| Database | SQLite locally; Postgres when deployed without a disk |
| PDF text | `smalot/pdfparser` |
| AI | Groq (default) or Google Gemini |

---

## 🚀 Getting started

Prerequisites: **PHP 8.3+**, **Composer**, **Node.js 20.19+ or 22.12+**, **Git**.

> Node's version matters: Vite 8 refuses to run on older releases, including
> 20.10.

```bash
git clone https://github.com/bodruzzaman-bkm/scholardesk.git
cd scholardesk

composer install
npm install

cp .env.example .env
php artisan key:generate

touch database/database.sqlite
php artisan migrate
php artisan storage:link
```

Then run the two dev servers in separate terminals:

```bash
npm run dev        # asset compiler
php artisan serve  # http://localhost:8000
```

A step-by-step walkthrough, including WSL, is in
[docs/setup.md](docs/setup.md).

### Enabling the AI features

Get a free key from [console.groq.com](https://console.groq.com/keys) and add it
to `.env`:

```env
AI_PROVIDER=groq
GROQ_API_KEY=your_key_here
```

Two things worth checking if AI calls fail with *cURL error 60*: PHP needs a CA
bundle, so `curl.cainfo` and `openssl.cafile` in `php.ini` must point at a
`cacert.pem`. An empty value there silently breaks every outbound HTTPS request,
including DOI lookups.

### Running the tests

```bash
php artisan test
```

**375 tests, 1,099 assertions.** No network access is needed — outbound HTTP
is faked, and `Http::preventStrayRequests()` fails the suite if any test tries
to reach a real host.

---

## ☁️ Deployment

Two hosts are supported. Both share the same `Dockerfile` and
`docker/entrypoint.sh`; the entrypoint reads `DB_CONNECTION` and
`FILESYSTEM_DISK` and configures itself accordingly.

### Render — free, no credit card

See **[docs/deploy-render.md](docs/deploy-render.md)** for the full walkthrough.

`render.yaml` declares the web service and a managed Postgres. Because a free
web service has no persistent disk, the two things that normally live on one
move off it: the database to Postgres, and the uploaded PDFs to Cloudflare R2
(S3-compatible, so Laravel's existing `s3` disk needs no code change).

Worth knowing before a demo: a free service **sleeps when idle**, so the first
request after a quiet spell takes 30–60 seconds.

### Fly.io — simpler, needs a card on file

```powershell
.\deploy.ps1        # first run creates the app, volume and secrets
.\upload-data.ps1   # optional: seed the live site with your local library
```

One volume at `/data` holds both the SQLite database and the PDFs, so there is
no Postgres and no object storage to configure. Fly does not bill an app this
size, but an account now needs a card before it will deploy.

---

## 📁 Layout

Standard Laravel, with a service layer doing the domain work:

```
app/
  Enums/          reading status, roles, member roles, activity types
  Http/
    Controllers/  thin — they validate, delegate, and return a view
    Middleware/   admin gate, locale
    Requests/     form-request validation
  Jobs/           IndexPaper — chunking and embedding, off the request
  Models/         Eloquent models
  Policies/       ownership and collection-membership authorisation
  Services/       PaperService, RagService, EmbeddingService, …
  Support/        small value helpers (DOI parsing, markdown sanitising)
docs/             setup and feature guides
resources/views/  Blade templates and components
module 1..4/      course submission packages (see below)
```

### Course submission packages

The four `module N/` folders package the project the way the CSE470 course
asks for it — module by module. Each one holds:

- `features.md` — every requirement in that module, the code path, the actual
  code, and the file and line it lives in
- `code/` — copies of the real source files, at their real project paths
- `sync.ps1` — rebuilds `code/` from the live app, so the copies cannot
  silently drift

| Folder | Requirements | Tests |
|---|---|---|
| `module 1/` | Auth + 1–6 — accounts and core library | 139 |
| `module 2/` | 7–10 — reading, annotation, single-paper AI | 69 |
| `module 3/` | 11–15 — advanced AI and citation export | 76 |
| `module 4/` | 16–22 — collaboration, analytics, administration | 98 |

They are documentation, not a second copy of the app: nothing in `module N/`
is loaded at runtime.

`tests/Feature/RequirementCoverageTest.php` walks all 22 numbered
requirements, one test each, so "everything works" is something the suite
proves rather than something a person re-checks by hand.

---

## 📄 License

MIT.

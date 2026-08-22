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
- **Citations** — copy or download BibTeX, RIS and APA for one paper or a whole
  collection. A collection can also be downloaded as a zip bundle.

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

- **Shared collections** with per-member roles (viewer / editor).
- **Comments** on collections.
- **Activity feed** — who added, annotated or changed what.
- **In-app notifications** with an unread badge.

### Administration

- **Role-based access** — administrator and researcher.
- **Admin portal** — manage user roles and moderate comments.
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
| Database | SQLite by default; any Laravel-supported driver works |
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

---

## ☁️ Deployment

`deploy.ps1` deploys to [Fly.io](https://fly.io) directly from this folder — no
GitHub connection and no local Docker needed, since Fly builds the image on its
own builders.

```powershell
.\deploy.ps1        # first run creates the app, volume and secrets
.\upload-data.ps1   # optional: seed the live site with your local library
```

The SQLite database and uploaded PDFs live on a mounted volume at `/data`, so
they survive redeploys. `docker/entrypoint.sh` symlinks the storage paths onto
that volume and runs migrations at boot.

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
```

---

## 📄 License

MIT.

# 📚 ScholarDesk

**A research-paper workspace for academics.** Collect papers, read and annotate
them, organise them into shared collections, and ask questions across your whole
library in plain language — with every AI answer grounded in your own documents
and citing the passages it used.

Built with Laravel 13 and Blade for CSE470.

---

## 🌐 Live demo

### **[scholardesk-nfs9.onrender.com](https://scholardesk-nfs9.onrender.com)**

Sign in with any of these — the deployment seeds itself with a worked library of
twelve indexed papers, tags, shared collections, threaded comments and a
moderation queue, so the site opens on the application rather than an empty
account.

| Role | Email | Password | Start here for |
|---|---|---|---|
| **Researcher** | `researcher@scholardesk.demo` | `demo1234` | The library, reader, annotations and AI panels — **best starting point** |
| **Administrator** | `admin@scholardesk.demo` | `demo1234` | User roles, comment moderation, the report queue |
| **Collaborator** | `collaborator@scholardesk.demo` | `demo1234` | The same shared collection seen as an editor rather than an owner |

> **Two things to know before you click.** The service sleeps when idle, so the
> first request after a quiet spell takes 30–60 seconds to wake — open the link
> a minute early. And the deployment is deliberately ephemeral: it rebuilds from
> the seed on every restart, so anything typed into it is not kept.

---

## ✨ Features

### Library

- **Add papers three ways** — upload a PDF, paste a **DOI**, or paste an
  **article URL**. Metadata (title, authors, journal, year, abstract) resolves
  automatically from Crossref, with OpenAlex and DataCite as fallbacks. A URL is
  resolved from an arXiv id, a DOI in the path, or the page's `citation_*` meta
  tags.
- **Batch upload** — up to 20 PDFs at once, each capped at 10 MB.
- **Search** — keyword search across title, authors, abstract and notes, plus
  **semantic search** that matches on meaning rather than exact words.
- **Filters** — by tag, collection, reading status, year and author.
- **Reading status** — *To read* → *Reading* → *Read*, changed inline from the
  library, the reader, or the paper page.
- **Coloured tags** for cross-cutting themes.
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

Every answer is grounded in the text of your own papers and cites the chunks it
used.

- **Paper summary** — a structured summary of a single paper.
- **Ask this paper** — Q&A scoped to one document.
- **Ask my library** — Q&A across every paper you own, from the dashboard.
- **Ask this collection** — Q&A scoped to a collection.
- **Related papers** — nearest neighbours by embedding similarity.
- **Literature-review draft** — a first draft synthesising a collection.

A PDF's text is extracted, split into overlapping chunks, and embedded locally.
A question is embedded the same way, the closest chunks are retrieved by cosine
similarity, and those chunks — and only those — are sent to the language model
as context.

### Collaboration

- **Shared collections** with per-member roles (owner / editor / viewer).
- **Threaded comments** on collections and on individual papers.
- **Activity feed** — who added, annotated or changed what.
- **In-app notifications** with an unread badge, and a matching email.

### Notifications and email

**Settings → Notifications & email** gives every user control over their own
mail:

- The card reports whether mail is genuinely configured **and through which
  transport**, rather than failing silently into a log file.
- Each notification type — comments, shares, mentions, AI completions, system
  announcements — can be switched off individually. Turning one off silences
  **only the email**: the in-app notification and the bell badge are unaffected.
- **Send test email** proves delivery, and surfaces a failure rather than
  swallowing it.

Notification email is best-effort by design: a mail failure is logged and
discarded so that a dead mail server can never break the request that triggered
the notification. The in-app record is the source of truth.

### Administration

- **Analytics dashboard** — papers added over time, and breakdowns by year,
  venue, tag and reading status.
- **Role-based access** — administrator and researcher.
- **Admin portal** — manage user roles, moderate comments, and work a report
  queue. Any user can flag a comment or a paper for review.
- **Bilingual UI** — English and Bangla, switchable in Settings.

---

## 🛠️ Tech stack

| Layer | Choice |
|---|---|
| Backend | PHP 8.3+, Laravel 13 |
| Views | Blade — no SPA framework |
| Interactivity | Alpine.js, and pdf.js for the reader |
| Styling | Tailwind CSS 3, built with Vite |
| Database | SQLite |
| PDF text | `smalot/pdfparser` |
| AI | Groq (default) or Google Gemini |
| Mail | Resend over HTTPS, or any SMTP server |

---

## ✅ Tests

```bash
php artisan test
```

**417 tests, 1,241 assertions.** No network access is needed — outbound HTTP is
faked, and `Http::preventStrayRequests()` fails the suite if any test tries to
reach a real host.

`tests/Feature/RequirementCoverageTest.php` walks all 22 numbered requirements,
one test each, so "everything works" is something the suite proves rather than
something a person re-checks by hand.

---

## 🚀 Running it locally

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

### Enabling the AI features

Get a free key from [console.groq.com](https://console.groq.com/keys) and add it
to `.env`:

```env
AI_PROVIDER=groq
GROQ_API_KEY=your_key_here
```

If AI calls fail with *cURL error 60*, PHP has no CA bundle: point
`curl.cainfo` and `openssl.cafile` in `php.ini` at a `cacert.pem`. An empty
value there silently breaks every outbound HTTPS request, DOI lookups included.

### Enabling email

Set `RESEND_API_KEY` and `MAIL_MAILER=resend` for HTTPS delivery, or point
`MAIL_MAILER=failover` at any SMTP server. Left unset, mail is written to
`storage/logs/laravel.log` and the settings card says so plainly.

> Hosts that block outbound SMTP — Render's free instances drop ports 25, 465
> and 587 — need the HTTPS route. `config/mail.php` also caps the SMTP socket
> at 10 seconds, because a blackholed port on a single-process server is an
> outage rather than an error.

---

## ☁️ Deployment

Two hosts, sharing one `Dockerfile` and `docker/entrypoint.sh`; the entrypoint
reads `DB_CONNECTION` and `FILESYSTEM_DISK` and configures itself accordingly.

### Render — free, no credit card

`render.yaml` declares one web service and needs no database. A free web service
has no persistent disk, so rather than half-persist the deployment behind a
managed Postgres while the uploaded PDFs evaporated anyway, it commits to being
ephemeral: SQLite on the container, rebuilt and reseeded on every boot. The site
returns to the same known library each time it starts.

`DEMO_SEED=true` builds that library — `database/seeders/DemoSeeder.php`
generates a real PDF per paper, so the reader and the indexer have genuine files
to work on. Turn the flag off outside a demonstration.

### Fly.io — simpler, needs a card on file

```powershell
.\deploy.ps1        # first run creates the app, volume and secrets
.\upload-data.ps1   # optional: seed the live site with your local library
```

One volume at `/data` holds both the SQLite database and the PDFs, so there is
no Postgres and no object storage to configure.

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
  Services/       PaperService, RagService, EmbeddingService, MailService, …
  Support/        small value helpers (DOI parsing, markdown sanitising)
notes/            architecture and wiring write-ups
resources/views/  Blade templates and components
module 1..4/      course submission packages (see below)
```

### Understanding the codebase

**[notes/architecture.md](notes/architecture.md)** explains how the system
actually works, rather than what it satisfies: the layered architecture and why
each seam is where it is, the schema and its relationship types, and the RAG
pipeline from PDF bytes to a cited answer.
**[notes/wiring.md](notes/wiring.md)** traces how one upload touches all four
modules. Both have illustrated companions —
[visual-architecture.html](notes/visual-architecture.html) and
[visual-wiring.html](notes/visual-wiring.html).

Start there for the mechanism. The `module N/features.md` documents are the
per-requirement reference.

### Course submission packages

The four `module N/` folders package the project module by module. Each holds:

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

They are documentation, not a second copy of the app: nothing in `module N/` is
loaded at runtime.

---

## ⚠️ Known limitations

Stated plainly, because a system you can describe the edges of is one you
understand:

- **Embeddings are lexical, not neural.** `App\Services\EmbeddingService` uses
  feature hashing over word tokens and character trigrams into 256 dimensions.
  It matches wording and morphology well — "neural machine translation" finds
  "neural translation models" — but it does not understand true paraphrase, so a
  query sharing no vocabulary with a paper scores low even when it means the
  same thing. Results below a similarity of 0.32 are shown but labelled *weak
  match*.
- **Generation needs an API key.** Summaries, Q&A and review drafts require
  `GROQ_API_KEY` (or `GEMINI_API_KEY`), and report "not configured" without one.
  Semantic search, related papers and everything else still work.
- **Groq's free tier allows 8,000 tokens per minute**, so several long questions
  in quick succession are briefly rate-limited.
- **PDF text quality varies.** Scanned PDFs with no text layer cannot be indexed
  — there is no OCR.

---

## 📄 License

MIT.

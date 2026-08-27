# Module 2 — Reading, Annotation & Single-Paper AI

**ScholarDesk · CSE470 · functional requirements 7–10**

This folder is the Module 2 submission: the real source files that implement
the module, plus the documentation for it.

| File | What it is |
|---|---|
| [`features.md`](features.md) | Every requirement, the code path, the actual code, and where it lives |
| [`code/`](code/) | The 39 source files, at their real project paths |
| [`sync.ps1`](sync.ps1) | Re-copies `code/` from the live app so it cannot drift |

---

## Read this first

`code/` holds **copies of files from the working application**, kept at their
real project-relative paths (`app/Services/RagService.php`, and so on). They
are the exact files that run — not a rewrite or a summary.

**They are not a standalone program.** Module 2 builds on Module 1: it needs
authentication and the paper library to have anything to read, annotate or
summarise. A Laravel application is one program, so the module boundary is a
boundary in the *feature set*, not a separate app that boots on its own.

To run or demonstrate any of this, run the full application from the project
root. Everything in `code/` is already part of it.

`code/routes/module-2-routes.php` is the one file here that is an **excerpt**
rather than a copy: the app's routes live in a single shared `routes/web.php`
covering all four modules, so the Module 2 routes are reproduced there with
comments. The app does not load that file.

---

## What is in this module

**Req 7** — read uploaded PDFs in the browser with pdf.js, and create coloured
highlights with margin notes. Highlights are database rows, so they survive a
reload, a new session and a change of zoom.

**Req 8** — write, edit and delete Markdown notes attached to a paper,
rendered as sanitised HTML.

**Req 9** — an AI summary of one paper in four sections: TL;DR, Key
contributions, Method, Limitations.

**Req 10** — ask questions about one paper and get answers drawn from that
paper's own text, retrieved by similarity rather than sent whole.

`features.md` maps each of these to the exact file and line that implements
it.

---

## Running the tests

From the **project root**, not this folder:

```bash
php artisan test --filter="Highlight|Note|NoteSecurity|PaperAiSections|ReaderAi|ChunkService|RunOnText|EmbeddingService"
```

That is **69 tests, 195 assertions**, all passing. The whole application
suite is 309 tests.

---

## Demonstrating each feature

Start the app from the project root (`php artisan serve`), sign in, and open a
paper that has an uploaded PDF.

| Requirement | Where to see it |
|---|---|
| 7 | **Read** on any paper with a PDF → select text to highlight it, pick a colour, add a margin note. Reload the page: the highlights are still there. Change the zoom: they still line up. |
| 8 | The paper page → the Notes section. Markdown renders; try a heading and a list. |
| 9 | The paper page or the reader → the **✨ AI assistant** tab → *Summarise*. |
| 10 | The same panel → type a question. The conversation is shared between the reader and the paper page. |

**Requirements 9 and 10 need an API key** — set `GROQ_API_KEY` in `.env`, or
they report "not configured". Free keys come from
[console.groq.com](https://console.groq.com/keys). **Requirements 7 and 8 need
no key at all.**

The paper must have finished indexing for requirement 10; the paper page shows
the index status, and **Re-index** retries a failed extraction. Scanned PDFs
with no text layer cannot be indexed — there is no OCR.

See [`features.md`](features.md) for the other operating limits.

---

## Keeping this folder current

If any of these files change in the app, re-run:

```powershell
.\sync.ps1
```

It wipes and rebuilds `code/` from the live source, and exits non-zero if a
listed file has been renamed or deleted.

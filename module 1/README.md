# Module 1 — User Accounts & Core Library Management

**ScholarDesk · CSE470 · authentication features + functional requirements 1–6**

This folder is the Module 1 submission: the real source files that implement
the module, plus the documentation for it.

| File | What it is |
|---|---|
| [`features.md`](features.md) | Every requirement, the code path, the actual code, and where it lives |
| [`code/`](code/) | The 74 source files, at their real project paths |
| [`sync.ps1`](sync.ps1) | Re-copies `code/` from the live app so it cannot drift |

---

## Read this first

`code/` holds **copies of files from the working application**, kept at their
real project-relative paths (`app/Services/PaperService.php`, and so on). They
are the exact files that run — not a rewrite or a summary.

**They are not a standalone program.** A Laravel application is one program,
so the module boundary is a boundary in the *feature set*, not a separate app
that boots on its own. Module 1 is the foundation the other three build on, so
it is the closest to self-contained — but it still shares `routes/web.php`,
the layout templates and the service container with everything else.

To run or demonstrate any of this, run the full application from the project
root. Everything in `code/` is already part of it.

`code/routes/module-1-routes.php` is the one file here that is an **excerpt**
rather than a copy: the app splits routes across `routes/web.php` (shared by
all four modules) and `routes/auth.php`, so the Module 1 routes are reproduced
there with comments. The app does not load that file.

---

## What is in this module

**Authentication** — register, sign in, sign out, password recovery by email.
Every account is a Researcher by default; administrators are promoted by an
existing administrator. Passwords are bcrypt-hashed at 12 rounds; sessions are
database-backed.

**Req 1** — two account types (Researcher, Administrator) plus Owner / Editor
/ Viewer roles inside shared collections, enforced by policies.

**Req 2** — add a paper by uploading a PDF, or by pasting a DOI or an article
URL. Batch upload takes up to 20 files.

**Req 3** — title, authors, year, venue and abstract are resolved
automatically from a DOI (Crossref → OpenAlex → DataCite) or a URL (arXiv id →
DOI in path → `citation_*` meta tags). Open-access PDFs are downloaded too.

**Req 4** — view, edit and delete papers; mark each as *to read*, *reading* or
*read* from the library, the reader or the paper page.

**Req 5** — named collections. A paper can sit in several; removing it from
one detaches the pivot row without deleting the paper.

**Req 6** — coloured tags, applied to papers and used to filter the library.

`features.md` maps each of these to the exact file and line that implements
it.

---

## Running the tests

From the **project root**, not this folder:

```bash
php artisan test --filter="Auth|RegistrationRole|Authorization|PaperLibrary|ImportByUrl|OpenAccessPdf|BulkUpload|ReadingStatusControl|Collection|TagSmoke|UploadLimits|MetadataService|Doi"
```

That is **158 tests, 505 assertions**, all passing. The whole application
suite is 375 tests.

---

## Demonstrating each feature

Start the app from the project root (`php artisan serve`) and register an
account.

| Requirement | Where to see it |
|---|---|
| Auth | `/register`, `/login`, `/forgot-password`. Note there is **no** account-type dropdown — that was the privilege-escalation fix. |
| 1 | Sign in as an administrator to reach `/admin`. Share a collection to assign Editor / Viewer. |
| 2 | `/papers/create` → upload a PDF, **or** paste `10.1038/nature14539`, **or** paste an arXiv URL. |
| 3 | Same form — submit a DOI and watch title, authors, year, venue and abstract fill themselves in. |
| 4 | `/papers` → the status dropdown on any row. Also on the paper page and in the reader. |
| 5 | `/collections` → create one, add a paper to two collections, remove it from one and confirm it is still in `/papers`. |
| 6 | `/tags` → create a coloured tag, apply it to a paper, then filter by it on `/papers`. |

**If imports arrive as "Untitled Paper"**, PHP has no CA bundle: set
`curl.cainfo` and `openssl.cafile` in `php.ini` to a real `cacert.pem`. Empty
values there make every outbound HTTPS call fail silently.

See [`features.md`](features.md) for the other operating limits.

---

## Keeping this folder current

If any of these files change in the app, re-run:

```powershell
.\sync.ps1
```

It wipes and rebuilds `code/` from the live source, and exits non-zero if a
listed file has been renamed or deleted.

# Module 4 — Collaboration, Analytics & System Administration

**ScholarDesk · CSE470 · functional requirements 16–22**

This folder is the Module 4 submission: the real source files that implement
the module, plus the documentation for it.

| File | What it is |
|---|---|
| [`features.md`](features.md) | Every requirement, the code path, the actual code, and where it lives |
| [`code/`](code/) | The 57 source files, at their real project paths |
| [`sync.ps1`](sync.ps1) | Re-copies `code/` from the live app so it cannot drift |

---

## Read this first

`code/` holds **copies of files from the working application**, kept at their
real project-relative paths (`app/Services/ExportService.php`, and so on).
They are the exact files that run — not a rewrite or a summary.

**They are not a standalone program.** Module 4 builds on all three earlier
modules: it needs authentication and the library (Module 1), the papers and
notes it exports (Module 2), and the citation formatting it bundles (Module
3). A Laravel application is one program, so the module boundary is a boundary
in the *feature set*, not a separate app that boots on its own.

To run or demonstrate any of this, run the full application from the project
root. Everything in `code/` is already part of it.

`code/routes/module-4-routes.php` is the one file here that is an **excerpt**
rather than a copy: the app's routes live in a single shared `routes/web.php`
covering all four modules, so the Module 4 routes are reproduced there with
comments. The app does not load that file.

---

## What is in this module

**Req 16** — download a whole collection as a single **zip**: the Markdown
document with metadata and your notes, a BibTeX bibliography, and every
paper's PDF.

**Req 17** — share a collection as Editor or Viewer, manage members and their
roles, enforced by `CollectionPolicy` on every action.

**Req 18** — threaded comments on shared collections **and** on individual
papers, with edit and delete of your own.

**Req 19** — a per-collection activity feed recording papers added and
removed, notes, comments, members joining and leaving, status changes and
generated reviews.

**Req 20** — in-app notifications with an unread badge, plus a matching email.

**Req 21** — an analytics dashboard at `/analytics`: papers added over time
and breakdowns by year, **venue**, tag and reading status.

**Req 22** — administrators manage accounts and roles, work a **report
queue**, and see system-wide statistics. The interface is available in English
and Bangla.

`features.md` maps each of these to the exact file and line that implements
it.

---

## What changed to finish this module

Three of the seven requirements were already complete. Four were partial, and
this is what closed them:

| Req | Was | Now |
|---|---|---|
| 16 | A Markdown file with no PDFs in it | A zip carrying the document, a bibliography and every PDF |
| 18 | Comments on collections only | Comments on papers too — including papers in no collection, which the schema could not represent |
| 21 | No venue breakdown, no dedicated page | `papersByVenue()` and a full `/analytics` page |
| 22 | Admins could hide a comment; nobody could report one | A polymorphic `reports` table, a report control on comments and papers, and an admin queue |

Requirement 18 needed a **schema change**: `comments.collection_id` was
`NOT NULL`, so a paper belonging to no collection could not be commented on at
all. `2026_08_28_100000_allow_paper_only_comments.php` makes it nullable.

---

## Running the tests

From the **project root**, not this folder:

```bash
php artisan test --filter="CollectionArchive|Collaboration|PaperComment|Notification|Analytics|AdminPortal|Reporting|ExportAndLocale|RequirementCoverage"
```

That is **103 tests, 312 assertions**, all passing. The whole application suite
is **380 tests, 1,112 assertions**.

`tests/Feature/RequirementCoverageTest.php` is worth knowing about: it walks
**all 22 numbered requirements** across every module, one test each, asserting
the entry point responds. It is the checklist that turns "all 22 work" into
something the suite proves.

---

## Demonstrating each feature

Start the app from the project root (`php artisan serve`) and sign in. You
will want a second account to see the collaboration features properly.

| Requirement | Where to see it |
|---|---|
| 16 | A collection page → **Full project bundle (.zip)**. Open the download: the PDFs are under `papers/`. |
| 17 | A collection page → Members → invite the second account as Viewer. Sign in as them: they can read but not add papers. |
| 18 | A **paper** page → Discussion → post a comment, then Reply to it. Also on a collection page. |
| 19 | A collection page → the activity feed, after adding a paper or posting a comment. |
| 20 | As the second account, watch the unread badge after being added to a collection. `/notifications` lists them. |
| 21 | `/analytics` → all five breakdowns. Venue fills in for papers imported by DOI. |
| 22 | As a non-owner, click ⚑ Report on a comment or paper. Then sign in as an administrator → `/admin/reports` → Resolve or Dismiss. Switch language in Settings. |

To reach the admin portal you need an administrator account. There is no
self-service route to one — that was the privilege-escalation fix in Module 1
— so promote a user from an existing admin, or directly:

```bash
php artisan tinker --execute="App\Models\User::where('email','you@example.com')->update(['role'=>'administrator']);"
```

See [`features.md`](features.md) for the operating limits — email needs SMTP
configured, archive size, and what resolving a report does and does not do.

---

## Keeping this folder current

If any of these files change in the app, re-run:

```powershell
.\sync.ps1
```

It wipes and rebuilds `code/` from the live source, and exits non-zero if a
listed file has been renamed or deleted.

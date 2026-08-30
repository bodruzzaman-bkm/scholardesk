# Module 4 — Collaboration, Analytics & System Administration

**ScholarDesk · CSE470 · functional requirements 16–22**

This folder is the Module 4 submission: the real source files that implement
the module, plus the documentation for it.

## Status — 7 of 7 implemented

| # | Requirement | Status |
|---|---|---|
| 16 | Export a collection — papers, notes, bibliography — as one file | ✅ Done |
| 17 | Share a collection as Editor or Viewer; manage members and roles | ✅ Done |
| 18 | Threaded comments on collections **and** individual papers | ✅ Done |
| 19 | Per-collection activity feed | ✅ Done |
| 20 | In-app notifications and matching emails, three triggers | ✅ Done |
| 21 | Analytics dashboard — over time, year, venue, tag, status | ✅ Done |
| 22 | Admin: accounts, roles, reported content, statistics, EN/BN | ✅ Done |

**116 tests, 354 assertions** for this module, all passing. Each requirement
is broken down below with what was built and where.

| File | What it is |
|---|---|
| [`features.md`](features.md) | Every requirement, the code path, the actual code, and where it lives |
| [`code/`](code/) | The 66 source files, at their real project paths |
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

## The seven requirements, and what was built

Quoted verbatim from the proposal. **All seven are implemented**; every line
number is verified against the live source, and every claim has a test.

---

### ✅ 16 — Export a collection as one file

> *"The Users can export a whole collection, its papers, notes, and a
> formatted bibliography, as a single downloadable file."*

**Delivered.** `GET /collections/{id}/bundle` returns a **zip** containing:

| Entry | Contents |
|---|---|
| `<collection>.md` | Every paper's metadata, **your** notes, and the latest saved review |
| `bibliography.bib` | BibTeX for the whole collection, importable as-is |
| `papers/*.pdf` | The actual PDFs |

Notes stay private to their author even inside a shared collection. A
metadata-only paper (imported by DOI, no open-access PDF) is skipped rather
than failing the export, and two papers sharing a title do not overwrite each
other in the archive.

`ExportService::collectionArchive()` · `tests/Feature/CollectionArchiveTest.php`

---

### ✅ 17 — Share a collection as Editor or Viewer

> *"Users can share a collection with collaborators as an Editor or a Viewer
> and manage members and their roles, with access enforced by the system."*

**Delivered.** Invite by email, assign Owner / Editor / Viewer, change a
member's role, remove a member. Roles are *ranked*, so a permission check
reads `atLeast(MemberRole::Editor)` rather than listing acceptable roles at
each call site.

Enforcement is in `CollectionPolicy`, not in the views — a Viewer who posts
the right form still gets a 403. Two invariants protect the model: the owner's
role cannot be reassigned, and the owner cannot be removed.

`CollectionMemberController` · `CollectionService::addMember()` ·
`tests/Feature/CollaborationTest.php`

---

### ✅ 18 — Threaded comments on collections *and* papers

> *"The Users can post threaded comments on shared collections and individual
> papers, and edit or delete their own comments."*

**Delivered, both anchors.** One `<x-comment>` component serves either; a
reply's parent is validated against the same thread, so a crafted id cannot
graft a reply onto another collection's or paper's discussion. Authors edit
and delete their own; administrators may remove one as moderation.

> This required a **schema change**. `comments.collection_id` was `NOT NULL`,
> so a paper belonging to no collection could not be discussed at all — the
> "individual papers" half was unrepresentable, not merely unbuilt.

`CommentController::store()` and `storePaper()` ·
`tests/Feature/PaperCommentTest.php`

---

### ✅ 19 — Per-collection activity feed

> *"The system maintains a per-collection activity feed that records events
> such as papers added, comments posted, and members joining."*

**Delivered.** Eight event types — the three named, plus papers removed, notes
added, members removed, reading-status changes and generated reviews. Metadata
is stored as JSON, so an entry keeps the paper title as it was and stays
readable after a rename or deletion.

A comment on a **paper** is recorded against every collection holding it: to a
collaborator watching that feed, it is exactly the event the feed exists to
surface.

`ActivityService::record()` · `tests/Feature/CollaborationTest.php`

---

### ✅ 20 — Notifications, in-app and by email

> *"The Users receive in-app notifications and matching emails when they are
> added to a collection, when someone comments, or when an AI task
> completes."*

**Delivered, all three triggers:**

| Trigger | Fires from |
|---|---|
| Added to a collection | `CollectionService::addMember()` |
| Someone comments | `CommentController::store()` / `storePaper()` |
| An AI task completes | `IndexPaper` (a paper becomes searchable) and a finished literature review |

The in-app row is the source of truth; the email is best-effort and a mail
failure never breaks the request that triggered it. Nobody is notified about
their own action.

> **Email needs credentials to actually send.** `MAIL_MAILER=failover` tries
> SMTP then falls back to writing the message to the log — so a missing app
> password degrades instead of turning "forgot password" into a 500. Add a
> Google App Password to `.env` for real delivery.

`NotificationService::notify()` · `tests/Feature/AiTaskNotificationTest.php`

---

### ✅ 21 — Analytics dashboard

> *"The system provides an analytics dashboard showing papers added over time
> and breakdowns by year, venue, tag, and reading status."*

**Delivered at `/analytics`**, all five:

| Breakdown | Method |
|---|---|
| Papers added over time | `papersAddedByMonth()` — 12 months, gaps included |
| By publication year | `papersByYear()` |
| **By venue** | `papersByVenue()` |
| By tag | `topTags()` |
| By reading status | `readingStatusBreakdown()` |

Charts are plain divs with a percentage width — no charting library, works
without JavaScript, correct in both light and dark themes. An empty library
renders zeroes rather than dividing by zero. Every query is scoped to one user
id, so there is no path that surfaces another researcher's numbers.

`AnalyticsController` · `tests/Feature/AnalyticsTest.php`

---

### ✅ 22 — System administration

> *"Administrators can manage user accounts and roles, moderate reported
> content, and view system-wide statistics, and the interface is available in
> English and Bangla."*

**Delivered, all four obligations:**

- **Accounts and roles** — list and search users, change roles, and **suspend
  an account**. Suspension rather than deletion: deleting a user cascades
  through their papers, collections, notes, highlights and comments, so it
  destroys a library to silence an account. A suspended user keeps everything
  and simply cannot sign in — enforced at the login gate *and* on every
  request, so it takes effect immediately rather than when their session
  expires.
- **Moderate reported content** — any user can flag a comment or a paper with
  a reason; administrators work a queue and mark each Resolved or Dismissed.
  Hiding a comment is reversible and leaves a tombstone so replies keep their
  context.
- **System-wide statistics** — users, admins, papers, collections, notes,
  highlights, comments, indexed papers, chunks, open reports and storage used.
- **English and Bangla** — switchable in Settings, applied by `SetLocale`. The
  suite asserts both locale files carry identical keys, so a string added to
  one and forgotten in the other fails rather than silently falling back.

`AdminController` · `ReportController` ·
`tests/Feature/ReportingTest.php`, `AccountSuspensionTest.php`,
`AdminPortalTest.php`

---

`features.md` maps every one of these to the exact file and line that
implements it, with the real code quoted.

---

## What changed to finish this module

Three of the seven requirements were already complete. Four were partial, and
this is what closed them:

| Req | Was | Now |
|---|---|---|
| 16 | A Markdown file with no PDFs in it | A zip carrying the document, a bibliography and every PDF |
| 18 | Comments on collections only | Comments on papers too — including papers in no collection, which the schema could not represent |
| 21 | No venue breakdown, no dedicated page | `papersByVenue()` and a full `/analytics` page |
| 20 | `AiDone` declared but never dispatched | Indexing and review completion both notify |
| 22 | Admins could hide a comment; nobody could report one, and no lever over an account | A polymorphic `reports` table, a report control, an admin queue, and account suspension |

Requirement 18 needed a **schema change**: `comments.collection_id` was
`NOT NULL`, so a paper belonging to no collection could not be commented on at
all. `2026_08_28_100000_allow_paper_only_comments.php` makes it nullable.

---

## Running the tests

From the **project root**, not this folder:

```bash
php artisan test --filter="CollectionArchive|Collaboration|PaperComment|Notification|Analytics|AdminPortal|Reporting|ExportAndLocale|RequirementCoverage|AiTaskNotification|AccountSuspension"
```

That is **116 tests, 354 assertions**, all passing. The whole application suite
is **393 tests, 1,154 assertions**.

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

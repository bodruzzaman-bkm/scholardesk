# Module 4 — Collaboration, Analytics & System Administration

**Functional requirements 16–22.** Source: `scholardesk/docs/ScholarDesk.pdf`.

For each requirement: the code path from URL to result, the actual code that
implements it, and the file it lives in.

**Paths are relative to the project root.** Every file listed is also copied
into this folder under `code/` at the same path — so
`app/Services/ExportService.php` is here as
`code/app/Services/ExportService.php`. Line numbers were re-verified against
the live source on 2026-08-31.

**Status: seven of seven implemented.** 98 tests, 299 assertions, all passing.

> **What changed in this module.** Three requirements were complete and four
> were only partially built. Requirement 16 exported a Markdown file with no
> PDFs in it; requirement 18 worked on collections but not on individual
> papers; requirement 21 had no venue breakdown; requirement 22 let admins
> hide a comment but gave users no way to report anything, so there was no
> queue to moderate. All four are closed below, and each section says which
> part is new.

---

# Req 16 — Export a whole collection as one file

> The Users can export a whole collection, its papers, notes, and a formatted
> bibliography, as a single downloadable file.

### Code path

```
GET /collections/{collection}/bundle       routes/web.php:85
  -> CollectionController::bundle()        app/Http/Controllers/CollectionController.php:182
     -> ExportService::collectionArchive() app/Services/ExportService.php:134
        -> ExportService::collectionBundle()  :24    the markdown document
        -> CitationService::formatMany()             bibliography.bib
        -> ZipArchive                                the PDFs
```

### What is in the archive — `app/Services/ExportService.php:134`

```php
/**
 * The whole collection as one downloadable archive (requirement 16).
 *
 * The requirement is "its papers, notes, and a formatted bibliography, as
 * a single downloadable file". A Markdown document carries the notes and
 * the bibliography but not the papers themselves, so the download is a zip:
 *
 *   <collection>.md      the existing bundle — metadata, notes, review
 *   bibliography.bib     BibTeX for every paper, importable as-is
 *   papers/*.pdf         the actual PDFs
 */
```

**This is the part that was missing.** The bundle used to be a lone `.md`, so
"its papers" was the one item in the requirement the export did not deliver.
`ZipArchive` was not used anywhere in the application before this.

### A metadata-only paper is skipped, not fatal — `ExportService.php:169-175`

```php
foreach ($papers as $paper) {
    // A metadata-only paper — imported by DOI with no open-access PDF —
    // is normal, not an error. Skip it rather than failing the export.
    if (blank($paper->file_path) || ! $disk->exists($paper->file_path)) {
        continue;
    }

    $zip->addFile($disk->path($paper->file_path), 'papers/'.$this->pdfName($paper, $used));
}
```

### Two papers can share a title — `ExportService.php:189-205`

```php
/**
 * A readable, unique filename for a paper inside the archive.
 *
 * Two papers can share a title, and a zip with duplicate entry names loses
 * all but one of them, so a counter is appended on collision.
 */
private function pdfName(Paper $paper, array &$used): string
```

Without the counter the archive silently drops papers — a zip cannot hold two
entries with the same name.

### Cleaning up — `app/Http/Controllers/CollectionController.php:182`

```php
return response()
    ->download($archive['path'], $archive['filename'], [
        'Content-Type' => 'application/zip',
    ])
    // The zip is a temporary file; without this, storage/app/tmp grows
    // by a full copy of the collection on every download.
    ->deleteFileAfterSend(true);
```

Notes stay private to their author even inside a shared collection — the
markdown document only ever contains the requesting user's notes
(`ExportService.php:28-31`).

### Files

| File | What it contributes |
|---|---|
| `routes/web.php:85` | The bundle download |
| `app/Http/Controllers/CollectionController.php` | `bundle()` :182 |
| `app/Services/ExportService.php` | `collectionArchive()` :134, `collectionBundle()` :24, `pdfName()` :189 |
| `app/Services/CitationService.php` | `formatMany()` for `bibliography.bib` |
| `tests/Feature/CollectionArchiveTest.php`, `ExportAndLocaleTest.php` | Tests |

---

# Req 17 — Share a collection with collaborators

> Users can share a collection with collaborators as an Editor or a Viewer and
> manage members and their roles, with access enforced by the system.

### Code path

```
POST   /collections/{collection}/members            routes/web.php:91
  -> CollectionMemberController::store()            app/Http/Controllers/CollectionMemberController.php:17
     -> CollectionService::addMember()              app/Services/CollectionService.php:118
PATCH  /collections/{collection}/members/{member}   -> update()  :39
DELETE /collections/{collection}/members/{member}   -> destroy() :53
```

### Roles are ranked, not listed — `app/Enums/MemberRole.php:32-45`

```php
public function rank(): int
{
    return match ($this) {
        self::Viewer => 1,
        self::Editor => 2,
        self::Owner  => 3,
    };
}

/** True when this role is at least as privileged as $minimum. */
public function atLeast(self $minimum): bool
{
    return $this->rank() >= $minimum->rank();
}
```

A permission check reads `atLeast(MemberRole::Editor)` instead of enumerating
acceptable roles at every call site.

### Enforcement is in the policy — `app/Policies/CollectionPolicy.php`

| Method | Line | Minimum role |
|---|---|---|
| `view` | :22 | Viewer |
| `update` | :27 | Editor |
| `delete` | :32 | Owner |
| `managePapers` | :38 | Editor |
| `manageMembers` | :44 | Owner |
| `comment` | :49 | Viewer |
| `useAi` | :55 | Viewer |

### The owner is a member row, not a special case — `app/Services/CollectionService.php:31`

```php
// The creator gets an explicit OWNER row so every access check can
// read membership from one place.
CollectionMember::create([
    'collection_id' => $collection->id,
    'user_id' => $owner->id,
    'role' => MemberRole::Owner,
]);
```

`2026_08_21_170000_backfill_collection_owner_members.php` retrofits this onto
collections that predate the rule.

Two invariants that would otherwise corrupt the model: the owner's own role
cannot be reassigned (`CollectionService.php:154`) and the owner cannot be
removed (`:166`).

### Files

| File | What it contributes |
|---|---|
| `routes/web.php:91-93` | Member management |
| `app/Http/Controllers/CollectionMemberController.php` | `store()` :17, `update()` :39, `destroy()` :53 |
| `app/Services/CollectionService.php` | `create()` :31, `addMember()` :118, `changeMemberRole()` :154, `removeMember()` :166 |
| `app/Enums/MemberRole.php` | `rank()` :32, `atLeast()` :42 |
| `app/Policies/CollectionPolicy.php` | Every access decision |
| `tests/Feature/CollaborationTest.php` | Tests |

---

# Req 18 — Threaded comments on collections and papers

> The Users can post threaded comments on shared collections and individual
> papers, and edit or delete their own comments.

### Code path

```
POST   /collections/{collection}/comments   routes/web.php:120
  -> CommentController::store()             app/Http/Controllers/CommentController.php:25
POST   /papers/{paper}/comments             routes/web.php:97      <- NEW
  -> CommentController::storePaper()        app/Http/Controllers/CommentController.php:72
PUT    /comments/{comment}                  -> update()  :128
DELETE /comments/{comment}                  -> destroy() :141
```

### The schema had to change first

`comments.collection_id` was `NOT NULL`, so **a paper belonging to no
collection could not be commented on at all** — the "individual papers" half
of the requirement was unrepresentable, not merely unbuilt.

`database/migrations/2026_08_28_100000_allow_paper_only_comments.php`:

```php
/**
 * A comment now has exactly one anchor:
 *
 *   collection_id set, paper_id null   -> a collection thread
 *   paper_id set, collection_id null   -> a paper thread
 *   both set                           -> a paper thread inside a collection
 *                                         (the pre-existing case, unchanged)
 */
```

### Commenting on a paper — `app/Http/Controllers/CommentController.php:72`

```php
public function storePaper(Request $request, Paper $paper, NotificationService $notifications): RedirectResponse
{
    $this->authorize('comment', $paper);

    $validated = $request->validate([
        'content' => ['required', 'string', 'max:5000'],
        // A reply's parent must live on this same paper, or a crafted id
        // could graft a reply onto another paper's thread.
        'parent_id' => [
            'nullable',
            'integer',
            Rule::exists('comments', 'id')->where('paper_id', $paper->id),
        ],
    ]);
```

The `parent_id` guard mirrors the collection-scoped one at `:29-36`. Without
it, a reply could be attached to a thread on a paper the commenter cannot see.

### Who may comment — `app/Policies/PaperPolicy.php`

```php
/**
 * Commenting follows read access, not annotate access.
 *
 * Highlights and notes are one person's private working material, so they
 * stay with the owner. A comment is the opposite — it is addressed to the
 * other people who can see the paper, which is exactly the collaborators
 * on a collection containing it (requirement 18).
 */
public function comment(User $user, Paper $paper): bool
{
    return $this->owns($user, $paper) || $this->sharedWith($user, $paper);
}
```

### Notifying the right people — `CommentController.php:107`

```php
/**
 * Everyone who can see this paper, minus whoever just commented.
 *
 * That is the owner plus the members of every collection holding it —
 * the same set PaperPolicy::comment() admits, so nobody is notified about
 * a discussion they cannot open.
 */
```

### One component, two contexts — `resources/views/components/comment.blade.php`

```blade
@props([
    'comment',
    // Exactly one of these anchors the thread. A collection thread passes
    // :collection; a paper thread passes :paper. Requirement 18 needs both.
    'collection' => null,
    'paper' => null,
    ...
])

@php
    $replyAction = $paper
        ? route('papers.comments.store', $paper)
        : route('comments.store', $collection);
@endphp
```

Both props default to null, so existing collection callers were unaffected.

### A knock-on fix

`resources/views/admin/comments.blade.php` linked
`route('collections.show', $comment->collection_id)` unconditionally. A
paper-only comment has no collection, so the moderation queue threw a routing
error on the first one. It now links the collection or the paper, and says so
when both are gone.

### Files

| File | What it contributes |
|---|---|
| `routes/web.php:120-99` | Both comment entry points |
| `app/Http/Controllers/CommentController.php` | `store()` :25, `storePaper()` :72, `notifyPaperAudience()` :107 |
| `app/Models/Comment.php` | `replies()`, `roots()`, `displayContent()` |
| `app/Policies/CommentPolicy.php`, `PaperPolicy.php` | Who may edit, delete, comment |
| `resources/views/components/comment.blade.php` | The thread component |
| `database/migrations/2026_08_28_100000_allow_paper_only_comments.php` | Nullable anchor |
| `tests/Feature/PaperCommentTest.php`, `CollaborationTest.php` | Tests |

---

# Req 19 — Per-collection activity feed

> The system maintains a per-collection activity feed that records events such
> as papers added, comments posted, and members joining.

### Every event type — `app/Enums/ActivityType.php:7-14`

```php
case PaperAdded      = 'paper_added';
case PaperRemoved    = 'paper_removed';
case NoteAdded       = 'note_added';
case CommentAdded    = 'comment_added';
case MemberAdded     = 'member_added';
case MemberRemoved   = 'member_removed';
case StatusChanged   = 'status_changed';
case ReviewGenerated = 'review_generated';
```

The three the proposal names are covered, plus five more.

> `NoteAdded` and `StatusChanged` were **defined but never recorded** — the
> enum listed them and nothing wrote them. Both are now wired up, in
> `NoteController` and `PaperController::updateStatus()`
> (`app/Http/Controllers/PaperController.php:201`, the feed write at `:218-224`).

### Recording — `app/Services/ActivityService.php:21`

```php
public function record(Collection $collection, User $actor, ActivityType $type, array $metadata = []): void
```

Metadata is a JSON column, so an entry keeps the paper title as it was at the
time. The feed stays readable after the paper is renamed or deleted.

The feed has **no route of its own**: it renders on the collection page via
`CollectionController::show()`, because a feed detached from the collection it
describes has nowhere useful to live.

### Files

| File | What it contributes |
|---|---|
| `app/Services/ActivityService.php` | `record()` :21, `forCollection()` :40 |
| `app/Models/Activity.php` | The entry, metadata cast |
| `app/Enums/ActivityType.php` | Eight event types |
| `tests/Feature/CollaborationTest.php` | Tests |

---

# Req 20 — In-app notifications and matching emails

> The Users receive in-app notifications and matching emails when they are
> added to a collection, when someone comments, or when an AI task completes.

### The three triggers the requirement names

| Trigger | Where |
|---|---|
| Added to a collection | `CollectionService::addMember()` :118 |
| Someone comments | `CommentController::store()` :25 and `storePaper()` :72 |
| An AI task completes | `NotificationType::AiDone` |

### In-app is the source of truth, email is best-effort — `app/Services/NotificationService.php:20`

```php
public function notify(User $user, NotificationType $type, string $message, ?string $link = null): void
{
    InAppNotification::create([
        'user_id' => $user->id,
        'type' => $type,
        'message' => $message,
        'link' => $link,
    ]);

    $this->email($user, $message, $link);
}
```

The class docblock states the rule:

```php
/**
 * The in-app row is the source of truth; email is best-effort. A mail failure
 * is swallowed and logged, because a down SMTP server must never break the
 * request that triggered the notification.
 */
```

### Never notify the actor — `NotificationService.php:37`

```php
public function notifyMany(iterable $users, NotificationType $type, string $message, ?string $link = null, ?User $except = null): void
```

`$except` is what stops someone being notified about their own comment.

> **Email needs configuring to actually send.** `.env.example` ships
> `MAIL_MAILER=log`, so on a default install the message is written to the log
> rather than delivered. Real delivery needs SMTP credentials in `.env`. The
> in-app half works out of the box.

### Files

| File | What it contributes |
|---|---|
| `routes/web.php:105-108` | Index, unread count, mark read, mark all |
| `app/Services/NotificationService.php` | `notify()` :20, `notifyMany()` :37, `email()` :58 |
| `app/Http/Controllers/NotificationController.php` | The page and the JSON count |
| `app/Models/InAppNotification.php`, `app/Enums/NotificationType.php` | The row and its five types |
| `tests/Feature/NotificationTest.php` | Tests |

---

# Req 21 — Analytics dashboard

> The system provides an analytics dashboard showing papers added over time
> and breakdowns by year, venue, tag, and reading status.

### Code path

```
GET /analytics                             routes/web.php:57       <- NEW
  -> AnalyticsController::index()          app/Http/Controllers/AnalyticsController.php:25
     -> AnalyticsService                   app/Services/AnalyticsService.php
  -> rendered by                           resources/views/analytics/index.blade.php
```

### All five breakdowns

| Requirement asks for | Method | Line |
|---|---|---|
| papers added over time | `papersAddedByMonth()` | `AnalyticsService.php:122` |
| by year | `papersByYear()` | `:58` |
| **by venue** | `papersByVenue()` | `:83` **← new** |
| by tag | `topTags()` | `:103` |
| by reading status | `readingStatusBreakdown()` | `:36` |

### The venue breakdown — `app/Services/AnalyticsService.php:83`

```php
/**
 * Papers grouped by publication venue, busiest first.
 *
 * Requirement 21 lists venue alongside year, tag and reading status. Blank
 * venues are excluded rather than bucketed as "Unknown": a paper imported
 * from a DOI that resolved no venue says nothing about where the user
 * publishes, and a large "Unknown" bar would dominate the chart without
 * meaning anything.
 */
public function papersByVenue(int $userId, int $limit = 10): array
{
    return Paper::query()
        ->ownedBy($userId)
        ->whereNotNull('venue')
        ->where('venue', '!=', '')
        ->groupBy('venue')
        ->select('venue', DB::raw('count(*) as aggregate'))
        ->orderByDesc('aggregate')
        ->limit($limit)
        ->pluck('aggregate', 'venue')
        ->map(fn ($v) => (int) $v)
        ->all();
}
```

### Why a separate page — `app/Http/Controllers/AnalyticsController.php:12`

```php
/**
 * Separate from the main dashboard on purpose: /dashboard is a working
 * surface — continue reading, recent papers, jump back in — while this page
 * answers "what does my library look like?" and carries every breakdown the
 * proposal names.
 */
```

Every method takes a `$userId` and scopes to it, so there is no code path that
can surface another researcher's numbers.

The charts are plain divs with a percentage width — no charting library, works
without JavaScript, correct in both themes. An empty library renders zeroes
rather than dividing by zero, which `AnalyticsTest` asserts explicitly.

### Files

| File | What it contributes |
|---|---|
| `routes/web.php:57` | `GET /analytics` |
| `app/Http/Controllers/AnalyticsController.php` | `index()` :25 |
| `app/Services/AnalyticsService.php` | Five breakdowns, `papersByVenue()` :83 |
| `resources/views/analytics/index.blade.php` | The page |
| `tests/Feature/AnalyticsTest.php` | Tests |

---

# Req 22 — System administration

> Administrators can manage user accounts and roles, moderate reported
> content, and view system-wide statistics, and the interface is available in
> English and Bangla.

Four separate obligations. Each one:

### 1. Manage user accounts and roles — `app/Http/Controllers/AdminController.php:54, :72`

```php
// An administrator must not be able to strip their own access and
// lock the last admin out of the portal.
if ($user->id === $request->user()->id) {
    return back()->with('error', 'You cannot change your own role.');
}
```

### 2. Moderate reported content — the part that was missing

An admin could hide a comment, but **no user could report anything**, so the
queue the requirement describes did not exist.

**The report** — `database/migrations/2026_08_28_110000_create_reports_table.php`:

```php
// Comment or Paper. Not constrained — a morph cannot carry a
// foreign key — so deletion is handled in the models instead.
$table->morphs('reportable');

// One open report per person per item. Without this the queue is
// floodable by a single user clicking Report repeatedly, which
// would bury the genuine reports underneath.
$table->unique(['user_id', 'reportable_type', 'reportable_id'], 'reports_one_per_user_per_item');
```

**Filing one** — `app/Http/Controllers/ReportController.php:34`:

```php
/**
 * The content types a user may report.
 *
 * An allowlist rather than accepting a class name from the request:
 * a free-form `reportable_type` would let a caller point a report at any
 * model in the application.
 */
private const REPORTABLE = [
    'comment' => Comment::class,
    'paper' => Paper::class,
];
```

The controller docblock explains the authorization shape, which is unusual and
deliberate:

```php
/**
 * Reporting is deliberately NOT gated by a policy on the target. Someone who
 * can see a comment can report it, and that is the whole population that could
 * be harmed by it. Requiring ownership would mean only the author of a comment
 * could report their own comment, which is not a moderation system.
 *
 * Access is still checked, though: you cannot report something you cannot see,
 * because that would confirm the existence of other users' private papers.
 */
```

Re-reporting is `updateOrCreate`, not `create` — the unique index would
otherwise turn a second report from the same person into a 500
(`ReportController.php:60-72`).

**Working the queue** — `AdminController::reports()` :117 and
`resolveReport()` :141:

```php
/**
 * Close a report as resolved or dismissed.
 *
 * Resolving does not itself hide the reported content — that stays a
 * separate, deliberate action on the comment. An administrator can decide
 * a report is valid and still leave the content up.
 */
```

`ReportStatus` keeps Resolved and Dismissed distinct rather than collapsing
them into "closed", so the record of which reporters were right survives.

The queue renders even when the reported item has since been deleted
(`admin/reports.blade.php`) — a report whose target vanished must not take the
whole page down.

### 3. System-wide statistics — `AdminController::index()` :29

Users, administrators, papers, collections, notes, highlights, comments,
indexed papers, chunks, **open reports**, and storage used.

### 4. English and Bangla — `lang/en/app.php`, `lang/bn/app.php`

46 keys each, switched per user in Settings and applied by
`app/Http/Middleware/SetLocale.php`. `RequirementCoverageTest` asserts the two
files have **identical key sets**, so a string added to one and forgotten in
the other fails the suite rather than silently falling back to English.

### Files

| File | What it contributes |
|---|---|
| `routes/web.php:126, :143-150` | Reporting and the admin portal |
| `app/Http/Controllers/AdminController.php` | `index()` :29, `users()` :54, `updateRole()` :72, `comments()` :90, `toggleCommentVisibility()` :104, `reports()` :117, `resolveReport()` :142 |
| `app/Http/Controllers/ReportController.php` | `store()` :39, `authorizeVisibility()` :83 |
| `app/Models/Report.php`, `app/Enums/ReportStatus.php` | The report and its lifecycle |
| `app/Http/Middleware/EnsureUserIsAdmin.php` | The `admin` gate |
| `app/Http/Middleware/SetLocale.php`, `lang/en`, `lang/bn` | Bilingual UI |
| `resources/views/admin/*.blade.php` | Portal, users, moderation, reports |
| `resources/views/components/report-button.blade.php` | Filing a report |
| `tests/Feature/AdminPortalTest.php`, `ReportingTest.php`, `ExportAndLocaleTest.php` | Tests |

---

# Summary

| # | Feature | Primary implementation | Status |
|---|---|---|---|
| 16 | Collection archive with PDFs | `ExportService::collectionArchive()` :134 | Built |
| 17 | Share as Editor / Viewer | `CollectionService::addMember()` :118 | Built |
| 18 | Threaded comments, both anchors | `CommentController::storePaper()` :72 | Built |
| 19 | Activity feed | `ActivityService::record()` :21 | Built |
| 20 | Notifications, in-app + email | `NotificationService::notify()` :20 | Built |
| 21 | Analytics with venue | `AnalyticsService::papersByVenue()` :83 | Built |
| 22 | Admin, reporting, EN/BN | `ReportController` + `AdminController::reports()` :117 | Built |

Run this module's tests from the project root:

```bash
php artisan test --filter="CollectionArchive|Collaboration|PaperComment|Notification|Analytics|AdminPortal|Reporting|ExportAndLocale|RequirementCoverage"
```

→ 98 tests, 299 assertions, passing. The whole application suite is **368
tests, 1,082 assertions**.

---

# Operating limits

1. **Email notifications need SMTP.** `.env.example` ships `MAIL_MAILER=log`,
   so on a default install the "matching email" is written to the log. In-app
   notifications work with no configuration.

2. **The archive holds the PDFs, so it is as large as the collection.** A
   50-paper collection is a 50-PDF download. It is built to a temporary file
   and deleted after sending, but the request holds it in `storage/app/tmp`
   for the duration.

3. **Resolving a report does not remove the content.** That is deliberate —
   the two decisions are separate — but it means an administrator working the
   queue has to hide the comment as well when it warrants it.

4. **`morphs('reportable')` carries no foreign key**, because a polymorphic
   column cannot. A report whose target is deleted is shown as "the reported
   content has since been deleted" rather than disappearing.

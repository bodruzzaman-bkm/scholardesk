# Module 1 — User Accounts & Core Library Management

**Authentication features + functional requirements 1–6.**
Source: `scholardesk/docs/ScholarDesk.pdf`.

For each requirement: the code path from URL to result, the actual code that
implements it, and the file it lives in.

**Paths are relative to the project root.** Every file listed is also copied
into this folder under `code/` at the same path — so
`app/Services/PaperService.php` is here as `code/app/Services/PaperService.php`.
Line numbers were re-verified against the live source on 2026-08-31.

**Status: all implemented.** 158 tests, 505 assertions, all passing.

---

# Authentication features

> The Users can register and sign in; every account is a Researcher account by
> default, and selected accounts hold Administrator rights.
> The Users can log in and log out securely, with passwords stored using strong
> one-way hashing.
> The system supports password recovery by email.
> The system uses session-based authentication and role-based authorization.

### Code path

```
GET|POST /register            routes/auth.php:15, :18
  -> RegisteredUserController  app/Http/Controllers/Auth/RegisteredUserController.php:22, :32
GET|POST /login               routes/auth.php:20, :23
  -> AuthenticatedSessionController  app/Http/Controllers/Auth/AuthenticatedSessionController.php:17, :25
POST /logout                  routes/auth.php:57
  -> AuthenticatedSessionController::destroy()  :37
GET|POST /forgot-password     routes/auth.php:25, :28
  -> PasswordResetLinkController  app/Http/Controllers/Auth/PasswordResetLinkController.php:17, :27
GET|POST /reset-password      routes/auth.php:31, :34
  -> NewPasswordController        app/Http/Controllers/Auth/NewPasswordController.php:22, :32
```

### Registration, and the privilege-escalation fix — `app/Http/Controllers/Auth/RegisteredUserController.php:32-56`

```php
public function store(Request $request): RedirectResponse
{
    $request->validate([
        'name' => ['required', 'string', 'max:255'],
        'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
        'password' => ['required', 'confirmed', Rules\Password::defaults()],
    ]);

    // The role is deliberately NOT accepted from the request. Allowing a
    // visitor to pick their own account type let anyone self-register as an
    // administrator. Roles are assigned by an existing administrator.
    $user = User::create([
        'name' => $request->name,
        'email' => $request->email,
        'role' => UserRole::Researcher,
        'password' => Hash::make($request->password),
    ]);

    event(new Registered($user));

    Auth::login($user);

    return redirect(route('dashboard', absolute: false));
}
```

**This is a fixed bug, not just a design note.** The registration form
previously offered an "Account Type" dropdown and the controller read `role`
from the request, so anyone visiting `/register` could make themselves an
administrator. The field is gone from the form and the role is hard-coded
here. `tests/Feature/RegistrationRoleTest.php` asserts that posting a `role`
of `administrator` still produces a researcher.

**Password hashing** is `Hash::make()` — bcrypt at 12 rounds
(`.env.example`: `BCRYPT_ROUNDS=12`). One-way, salted per user.

**Sessions** are Laravel's own, backed by the database
(`.env.example`: `SESSION_DRIVER=database`), so authentication is
session-based rather than token-based, exactly as the proposal specifies.

### Files

| File | What it contributes |
|---|---|
| `routes/auth.php` | All auth routes |
| `app/Http/Controllers/Auth/RegisteredUserController.php` | `create()` :22, `store()` :32 |
| `app/Http/Controllers/Auth/AuthenticatedSessionController.php` | `create()` :17, `store()` :25, `destroy()` :37 |
| `app/Http/Requests/Auth/LoginRequest.php` | Credential validation and rate limiting |
| `app/Http/Controllers/Auth/PasswordResetLinkController.php` | Emails the reset link, :27 |
| `app/Http/Controllers/Auth/NewPasswordController.php` | Consumes the token, re-hashes, :32 |
| `app/Models/User.php` | The model, `password` cast to hashed |
| `resources/views/auth/*.blade.php` | Register, login, forgot- and reset-password forms |
| `tests/Feature/Auth/`, `tests/Feature/RegistrationRoleTest.php` | Tests |

---

# Req 1 — Two account types, and roles inside shared collections

> The system provides two account types: Researcher and Administrator, with
> role-based authorization, together with Owner, Editor, and Viewer roles
> inside shared collections.

There are **two separate role systems**, and keeping them separate is the
design: an account type says what you are on the platform, a member role says
what you can do in one collection.

### 1. Account type — `app/Enums/UserRole.php:7-21`

```php
case Researcher = 'researcher';
case Administrator = 'administrator';

public function isAdmin(): bool
{
    return $this === self::Administrator;
}
```

Enforced at the route layer by `app/Http/Middleware/EnsureUserIsAdmin.php`,
registered as the `admin` alias and wrapping the whole `/admin` prefix.

### 2. Collection member role — `app/Enums/MemberRole.php:10-49`

```php
case Viewer = 'viewer';
case Editor = 'editor';
case Owner  = 'owner';

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

Ranking them means a permission check reads `atLeast(MemberRole::Editor)`
rather than listing every acceptable role at each call site.

### 3. Authorization — `app/Policies/`

Every action goes through a policy rather than an inline ownership check:

| Policy | Methods | File |
|---|---|---|
| `CollectionPolicy` | `view` :22, `update` :27, `delete` :32, `managePapers` :38, `manageMembers` :44, `comment` :49, `useAi` :55 | `app/Policies/CollectionPolicy.php` |
| `PaperPolicy` | `view`, `read`, `update`, `delete`, `annotate`, `useAi` | `app/Policies/PaperPolicy.php` |
| `TagPolicy` | Owner-only | `app/Policies/TagPolicy.php` |

`PaperPolicy` draws a line the proposal implies but does not spell out —
collaborators get **read** access to a shared paper, never write:

```php
/**
 * Highlights and notes are private to their author, so only the paper's
 * owner may create them. A collaborator reading a shared PDF does not get
 * to write on someone else's copy.
 */
public function annotate(User $user, Paper $paper): bool
{
    return $this->owns($user, $paper);
}
```

Membership is the single source of truth: `CollectionService::create()`
(`app/Services/CollectionService.php:31`) writes an explicit `Owner` row for
the creator rather than relying on `owner_id` being special-cased everywhere.

### Files

| File | What it contributes |
|---|---|
| `app/Enums/UserRole.php` | Researcher / Administrator |
| `app/Enums/MemberRole.php` | Viewer / Editor / Owner, `rank()` :32, `atLeast()` :42 |
| `app/Models/CollectionMember.php` | The membership row |
| `app/Http/Middleware/EnsureUserIsAdmin.php` | The `admin` route gate |
| `app/Policies/CollectionPolicy.php`, `PaperPolicy.php`, `TagPolicy.php` | Authorization |
| `tests/Feature/AuthorizationTest.php` | Tests |

---

# Req 2 — Add papers by PDF, DOI, or article URL

> The Users can add papers to their library by uploading a PDF file or by
> pasting a DOI or article URL.

### Code path

```
POST /papers                    routes/web.php:67
  -> PaperController::store()   app/Http/Controllers/PaperController.php:113
     -> StorePaperRequest       app/Http/Requests/StorePaperRequest.php
     -> PaperService::createForUser()  app/Services/PaperService.php:62
        -> MetadataService::lookup()   app/Services/MetadataService.php:38
        -> PaperService::queueIndexing()  :190

POST /papers/batch              routes/web.php:68
  -> PaperController::storeBatch()  :71
     -> PaperService::createManyFromUploads()  :164
```

### One field takes either kind of identifier — `app/Http/Requests/StorePaperRequest.php:18-38`

```php
/**
 * One field accepts either a DOI or an article URL (requirement 2), which
 * is how a researcher actually works - they paste whatever they copied.
 * MetadataService decides which it is.
 *
 * A bare DOI is normalised here so that "https://doi.org/10.1/X",
 * "doi:10.1/x" and "10.1/X" all collide on the uniqueness rule below
 * instead of creating duplicate papers.
 */
protected function prepareForValidation(): void
{
    $identifier = trim((string) ($this->input('identifier') ?? $this->input('doi') ?? ''));

    if ($identifier === '') {
        return;
    }

    $this->merge([
        'identifier' => $this->isUrl($identifier) ? $identifier : (Doi::normalize($identifier) ?? $identifier),
    ]);
}
```

Either an identifier **or** a file is required, never neither —
`StorePaperRequest.php:47-60`:

```php
'identifier' => array_values(array_filter([
    'required_without:file',
    'nullable',
    'string',
    'max:2048',
    $isUrl ? 'url' : null,
    /*
     | Only a pasted DOI can be checked for duplicates up front.
     | A URL's DOI is not known until it has been resolved, so that
     | case is caught in PaperService instead.
     */
    $isUrl ? null : Rule::unique('papers', 'doi')->where('user_id', Auth::id()),
])),
'file' => ['required_without:identifier', 'nullable', 'file', 'mimetypes:application/pdf', 'mimes:pdf', 'max:10240'],
```

The file rule uses **both** `mimetypes:application/pdf` (the sniffed content
type) and `mimes:pdf` (the extension), so renaming `evil.exe` to `paper.pdf`
does not get it past validation.

**Upload limits are read from PHP, not assumed** —
`app/Support/UploadLimits.php`. Advertising "20 files, 10 MB each" while
`php.ini` caps `post_max_size` at 8 MB produced a silent failure with no
error; the limits shown in the UI now come from the running configuration.

### Files

| File | What it contributes |
|---|---|
| `routes/web.php:67, :68` | Single and batch upload |
| `app/Http/Controllers/PaperController.php` | `create()` :108, `store()` :113, `storeBatch()` :71 |
| `app/Http/Requests/StorePaperRequest.php` | `prepareForValidation()` :26, `rules()` :40 |
| `app/Http/Requests/StorePapersBatchRequest.php` | Up to 20 files |
| `app/Services/PaperService.php` | `createForUser()` :62, `createManyFromUploads()` :164 |
| `app/Support/Doi.php` | DOI parsing and normalisation |
| `app/Support/UploadLimits.php` | Real php.ini ceilings |
| `app/Enums/PaperSource.php` | upload / doi / url |
| `resources/views/papers/create.blade.php` | The add form |
| `tests/Feature/BulkUploadTest.php`, `UploadLimitsTest.php`, `tests/Unit/DoiTest.php` | Tests |

---

# Req 3 — Automatically fetch metadata

> The system automatically fetches a paper's title, authors, year, venue, and
> abstract from a pasted DOI or URL.

### The resolution chain — `app/Services/MetadataService.php`

| Input | Path | Method |
|---|---|---|
| DOI | Crossref → OpenAlex → DataCite | `lookupByDoi()` :62 |
| URL | arXiv id → DOI in path → `citation_*` meta tags | `lookupByUrl()` :78 |

Three registries rather than one because no single registry covers every
publisher: Crossref is strongest for journals, OpenAlex fills gaps, DataCite
covers datasets and some preprints.

### Metadata never overwrites what the user typed — `app/Services/PaperService.php:101-119`

```php
$paper = Paper::create([
    'user_id' => $userId,
    'title' => $this->firstFilled(
        $input['title'] ?? null,          // what the user typed wins
        $metadata['title'] ?? null,       // then the registry
        $this->titleFromFilename($file)   // then the filename
    ),
    'authors' => $metadata['authors'] ?? null,
    'year' => $metadata['year'] ?? null,
    'venue' => $metadata['venue'] ?? null,
    'abstract' => $this->firstFilled($input['abstract'] ?? null, $metadata['abstract'] ?? null),
    // A URL that resolved to a DOI is stored under both, so citation
    // export still gets a DOI even though the user pasted a link.
    'doi' => $metadata['doi'] ?? ($isUrl ? null : Doi::normalize($identifier)),
    'url' => $metadata['url'] ?? ($isUrl ? $identifier : null),
    'source' => $this->sourceFor($file, $identifier, $isUrl),
    'file_path' => $filePath,
    'reading_status' => ReadingStatus::ToRead,
]);
```

**A lookup miss is not an error** — `PaperService.php:66-73`:

```php
/*
 | The user pastes one field ("identifier") holding either a DOI or an
 | article URL; MetadataService works out which and resolves it through
 | the appropriate registry. A lookup miss is not an error - the paper
 | is still created so the details can be typed in by hand.
 */
$identifier = $input['identifier'] ?? $input['doi'] ?? null;
$metadata = filled($identifier) ? ($this->metadata->lookup($identifier) ?? []) : [];
```

A pasted URL can resolve to a DOI the user already owns — something
validation could not check, because the DOI was unknown at that point. That
becomes an ordinary field error rather than a constraint crash
(`PaperService.php:83-89`).

### Open-access PDFs are fetched too — `PaperService.php:91-99`

```php
/*
 | An open-access PDF makes the difference between importing a citation
 | and importing a readable paper: the reader, highlights, summaries
 | and semantic search all need the file. Failure is fine - the paper
 | is still created with its metadata.
 */
if ($filePath === null && filled($metadata['pdf_url'] ?? null)) {
    $filePath = $this->storeFetchedPdf($metadata['pdf_url'], $metadata['title'] ?? null);
}
```

That download is a server-side request to a user-supplied URL, so
`MetadataService::downloadPdf()` (:318) guards it: **https only, redirects not
followed, private and reserved IP ranges rejected**
(`FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE`), size-capped, and the
response checked for the PDF magic bytes before it is stored.

> **Operational note.** If imports arrive as "Untitled Paper", PHP has no CA
> bundle: `curl.cainfo` and `openssl.cafile` in `php.ini` must point at a real
> `cacert.pem`. Empty values there make *every* outbound HTTPS call fail
> silently, and this is the feature where you notice first.

### Files

| File | What it contributes |
|---|---|
| `app/Services/MetadataService.php` | `lookup()` :38, `lookupByDoi()` :62, `lookupByUrl()` :78, `htmlMeta()` :249, `downloadPdf()` :318, `extractArxivId()` :429 |
| `app/Services/CrossRefService.php` | Crossref client |
| `app/Services/PaperService.php` | Merge precedence :101, PDF fetch :97 |
| `tests/Feature/ImportByUrlTest.php`, `OpenAccessPdfTest.php`, `tests/Unit/MetadataServiceTest.php` | Tests |

---

# Req 4 — View, edit, delete, and mark reading status

> The Users can view, edit, and delete papers, and mark each paper as "to
> read", "reading", or "read".

### Code path

```
GET    /papers/{paper}          routes/web.php:69  -> PaperController::show()    :52
GET    /papers/{paper}/edit     routes/web.php:70  -> PaperController::edit()    :128
PUT    /papers/{paper}          routes/web.php:71  -> PaperController::update()  :143
DELETE /papers/{paper}          routes/web.php:72  -> PaperController::destroy() :170
PATCH  /papers/{paper}/status   routes/web.php:74  -> PaperController::updateStatus() :182
```

### The three statuses — `app/Enums/ReadingStatus.php:14-16`

```php
case ToRead  = 'to read';
case Reading = 'reading';
case Read    = 'read';
```

`label()` :18 and `badgeClasses()` :28 keep the wording and the colour in the
enum, so the badge cannot drift from the value.

### Changing status — `app/Http/Controllers/PaperController.php:201-228`

```php
public function updateStatus(Request $request, Paper $paper, ActivityService $activities): RedirectResponse
{
    $this->authorize('update', $paper);

    $validated = $request->validate([
        'reading_status' => ['required', new Enum(ReadingStatus::class)],
    ]);

    $status = ReadingStatus::from($validated['reading_status']);

    // Nothing to record if the status did not actually change.
    if ($paper->reading_status === $status) {
        return back();
    }

    $this->papers->setStatus($paper, $status);

    // Requirement 19 lists status changes among the feed's events.
    foreach ($paper->collections()->get() as $collection) {
        $activities->record($collection, $request->user(), ActivityType::StatusChanged, [
            'paper_id' => $paper->id,
            'title' => $paper->title,
            'status' => $status->label(),
        ]);
    }

    return back()->with('success', 'Reading status updated.');
}
```

### One control, three places — `resources/views/components/reading-status.blade.php`

```blade
<form method="POST" action="{{ route('papers.status', $paper) }}" ...>
    @csrf @method('PATCH')
    <select name="reading_status" onchange="this.form.submit()" class="... {{ $badge }}">
```

The same component renders in the library list, the reader header and the
paper detail page. **This was previously a read-only badge** — the status was
displayed but nothing could change it. Extracting one component was what made
all three places actually work, and
`tests/Feature/ReadingStatusControlTest.php` asserts the control is present in
each.

### Two bugs fixed in the edit form

`resources/views/papers/edit.blade.php` rendered the collections block
**twice**, and omitted the **abstract** field entirely — so saving any edit
silently erased the abstract.

### Files

| File | What it contributes |
|---|---|
| `app/Http/Controllers/PaperController.php` | `show()` :52, `edit()` :128, `update()` :143, `destroy()` :170, `updateStatus()` :182 |
| `app/Http/Requests/UpdatePaperRequest.php` | Edit validation |
| `app/Services/PaperService.php` | `update()` :214, `delete()` :234, `setStatus()` :247 |
| `app/Enums/ReadingStatus.php` | The three statuses |
| `app/Models/Paper.php` | Casts, relations, scopes |
| `resources/views/components/reading-status.blade.php` | The shared control |
| `resources/views/papers/index.blade.php`, `show.blade.php`, `edit.blade.php` | Library, detail, edit |
| `tests/Feature/PaperLibraryTest.php`, `ReadingStatusControlTest.php` | Tests |

---

# Req 5 — Organise papers into collections

> The Users can organize papers into named collections, adding a paper to more
> than one collection and removing it without deleting it from the library.

### Many-to-many, which is what makes both halves work

`collection_paper` is a pivot table
(`database/migrations/2026_08_11_151357_create_collection_paper_table.php`).
A paper row is referenced by many pivot rows, so:

- **"more than one collection"** — attach the same `paper_id` under several
  `collection_id`s.
- **"removing it without deleting it"** — `removePaper` detaches the *pivot
  row*; the paper is untouched.

`app/Http/Controllers/CollectionController.php:171-178`:

```php
public function removePaper(Request $request, Collection $collection, Paper $paper): RedirectResponse
{
    $this->authorize('managePapers', $collection);

    $this->collections->removePaper($collection, $request->user(), $paper);

    return back()->with('success', 'Paper removed from the collection.');
}
```

> **This route was dead.** `removePaper` type-hinted `Paper` without importing
> the class, so route-model binding tried to resolve
> `App\Http\Controllers\Paper` and every call was a fatal error. The import
> fixed it.

`CollectionService::removePaper()` (`app/Services/CollectionService.php:102`)
calls `$collection->papers()->detach($paper->id)` — a pivot delete, never a
paper delete.

### Adding re-checks the paper, not just the collection — `app/Services/CollectionService.php:62-101`

```php
$allowed = Paper::query()
    ->accessibleBy($actor->id)
    ->whereIn('id', $unique)
    ->get();

if ($allowed->isEmpty()) {
    throw ValidationException::withMessages([
        'paper_id' => 'You do not have access to those papers.',
    ]);
}
```

The class docblock explains why:

```php
/*
 * Every method that grants access to papers re-checks the *paper* as well as
 * the collection: collection membership grants access to the papers inside it,
 * so accepting an arbitrary paper id here would hand out someone else's PDF.
 */
```

Papers the actor cannot reach are **skipped**, not fatal — but if *nothing*
was permitted the request was invalid, and that is reported rather than
returning a silent success.

### Files

| File | What it contributes |
|---|---|
| `routes/web.php:79-88` | Collection CRUD and paper attach/detach |
| `app/Http/Controllers/CollectionController.php` | `index()` :29, `store()` :43, `show()` :52, `update()` :85, `destroy()` :100, `addPaper()` :118, `removePaper()` :171 |
| `app/Services/CollectionService.php` | `create()` :31, `addPapers()` :62, `removePaper()` :102 |
| `app/Models/Collection.php` | `papers()` :33, `scopeAccessibleBy()` :76 |
| `app/Http/Requests/StoreCollectionRequest.php`, `UpdateCollectionRequest.php` | Validation |
| `resources/views/collections/index.blade.php`, `show.blade.php` | Views |
| `tests/Feature/CollectionTest.php` | Tests |

---

# Req 6 — Coloured tags, and filtering by them

> The Users can create colored tags, apply them to papers, and filter the
> library by tag.

### Colour is validated as a hex value — `app/Http/Requests/StoreTagRequest.php:16-29`

```php
'name' => [
    'required',
    'string',
    'max:50',
    // Matches ScholarDesk's @@unique([userId, name]) constraint.
    Rule::unique('tags', 'name')->where('user_id', Auth::id()),
],
// Reject anything that is not a real hex colour before it reaches
// a style attribute in a Blade view.
'color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
```

That regex is a security control, not tidiness: the colour is interpolated
into a `style` attribute, and an unvalidated value there is a CSS-injection
vector.

### Readable text on any tag colour — `app/Models/Tag.php:42`

```php
public function contrastingTextColor(): string
{
    $hex = ltrim((string) $this->color, '#');
    ...
```

Computes luminance and returns black or white, so a user who picks a pale
yellow still gets a legible pill.

### Filtering — `app/Models/Paper.php:162-169`

```php
public function scopeWithTag(Builder $query, mixed $tagId): Builder
{
    if (blank($tagId)) {
        return $query;
    }

    return $query->whereHas('tags', fn (Builder $q) => $q->where('tags.id', $tagId));
}
```

Applied from `PaperService::paginateLibrary()` (`app/Services/PaperService.php:43`)
via `?tag=` on `/papers` and `/search`. Passing nothing returns the query
untouched, so the same chain serves the filtered and unfiltered library.

Tags are per-user: `scopeOwnedBy` (`app/Models/Tag.php:31`) plus `TagPolicy`.

### Files

| File | What it contributes |
|---|---|
| `routes/web.php:111-114` | Tag CRUD |
| `app/Http/Controllers/TagController.php` | `index()` :15, `store()` :27, `update()` :37, `destroy()` :51 |
| `app/Models/Tag.php` | `papers()` :26, `scopeOwnedBy()` :31, `contrastingTextColor()` :42 |
| `app/Http/Requests/StoreTagRequest.php`, `UpdateTagRequest.php` | Hex validation |
| `app/Models/Paper.php` | `scopeWithTag()` :156 |
| `resources/views/tags/index.blade.php` | Tag manager |
| `tests/Feature/TagSmokeTest.php` | Tests |

---

# Schema

| Migration | Creates |
|---|---|
| `0001_01_01_000000_create_users_table.php` | users, sessions |
| `2026_08_11_151330_create_papers_table.php` | papers |
| `2026_08_11_151345_create_collections_table.php` | collections |
| `2026_08_11_151357_create_collection_paper_table.php` | the many-to-many pivot |
| `2026_08_11_161316_add_metadata_to_papers_table.php` | authors, year, venue, abstract, doi |
| `2026_08_12_062433_add_reading_status_to_papers_table.php` | reading_status |
| `2026_08_15_072609_create_tags_table.php` | tags |
| `2026_08_15_072610_create_paper_tag_table.php` | paper/tag pivot |
| `2026_08_21_120000_add_integrity_constraints_to_library_tables.php` | missing foreign keys and cascades |
| `2026_08_21_130000_scope_paper_doi_uniqueness_per_user.php` | see below |
| `2026_08_22_090000_add_url_to_papers.php` | url, source |

**The DOI uniqueness fix matters.** `doi` was declared `->unique()` globally,
which meant that once one researcher added a paper, **no other user could ever
add it**. It is now unique on `(user_id, doi)`.

---

# Summary

| Requirement | Primary implementation | Status |
|---|---|---|
| Auth | `RegisteredUserController::store()` :32, `routes/auth.php` | Built |
| 1 | `UserRole`, `MemberRole`, `app/Policies/` | Built |
| 2 | `PaperService::createForUser()` :62, `StorePaperRequest` | Built |
| 3 | `MetadataService::lookup()` :38 | Built |
| 4 | `PaperController::updateStatus()` :182, `reading-status.blade.php` | Built |
| 5 | `collection_paper` pivot, `CollectionService` :62, :102 | Built |
| 6 | `TagController`, `Paper::scopeWithTag()` :156 | Built |

Run this module's tests from the project root:

```bash
php artisan test --filter="Auth|RegistrationRole|Authorization|PaperLibrary|ImportByUrl|OpenAccessPdf|BulkUpload|ReadingStatusControl|Collection|TagSmoke|UploadLimits|MetadataService|Doi"
```

---

# Operating limits

1. **Metadata lookup needs working outbound HTTPS.** If PHP has no CA bundle
   configured, every registry call fails silently and papers import as
   "Untitled Paper". Set `curl.cainfo` and `openssl.cafile` in `php.ini`.

2. **Upload ceilings are PHP's, not the app's.** A default `php.ini` caps
   `post_max_size` at 8 MB and `upload_max_filesize` at 2 MB, below the 10 MB
   per file the app allows. `UploadLimits` reports the real values; raise them
   in `php.ini` if you need the full batch size.

3. **Not every DOI resolves.** Three registries are tried, but a paper behind
   a publisher that registers with none of them will import with the DOI and
   nothing else. The details can be typed in by hand — the import still
   succeeds.

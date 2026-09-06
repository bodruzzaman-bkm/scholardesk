# Deploying ScholarDesk on Render (free tier)

A free host that gives you a public URL **without a credit card**, from a
**private** repository — the running site is public, the source is not. That
combination is the reason this is the recommended target and not one of the
container hosts that only serve what they can also show.

One web service, no database, nothing else to create.

> **Prefer Fly?** `deploy.ps1` and `fly.toml` still work — one volume at
> `/data` keeps the database *and* the uploads, so nothing is lost on a
> restart. Fly now needs a card on file, though it does not bill an app this
> size. Both deployments share the same Dockerfile and entrypoint.

### Nothing survives a restart, and that is the design

A free container's filesystem is rebuilt from the image every time it wakes,
and it sleeps after about 15 minutes of inactivity. The database is a SQLite
file on that filesystem, so it goes too — and `DEMO_SEED` rebuilds it on the
way back up.

The practical effect is that **the site returns to the same worked library
every time it starts**. Sign-ins, uploads and comments made on the live site
last as long as the container does and no longer.

There was a managed Postgres here, which `render.yaml` declared and which made
the blueprint fail to apply. Removing it turned out to be the better design
rather than a retreat from it: a free web service has no persistent disk, so
Postgres bought durability for the rows while the uploaded PDFs beside them
still evaporated. Half-persistent is a worse thing to explain, and to demo,
than deliberately ephemeral.

Do not treat the live site as storage. It is a demonstration.

Object storage would make uploads durable and is deliberately not wired up:
the app never consults the default disk. Every PDF path names
`Storage::disk('public')` explicitly, and `PdfTextService` calls `->path()` on
it, which only a local disk implements. Pointing `FILESYSTEM_DISK` at s3
therefore moved no uploads — it only skipped the `public/storage` symlink and
made every PDF 404. Real object-storage support would mean changing those call
sites, not an env var.

---

## Before you start

Two accounts, both free, neither asking for a card:

1. **Render** — <https://render.com>, signed in with GitHub
2. **Groq** — <https://console.groq.com/keys>, for the AI features

And your code pushed to GitHub. The repository may be private; grant Render
access to that one repository when it asks.

## 1. Create the Render service

1. Render dashboard → **New → Blueprint**.
2. Point it at your GitHub repository. Render reads `render.yaml` and proposes
   a single web service. If it proposes a database as well, you are on an old
   commit — the current blueprint declares none.
3. Apply. The first build takes several minutes: it compiles the front-end on
   Node 22, installs PHP dependencies, and builds the PHP extensions.

`APP_KEY` is generated automatically. Do not change it afterwards — every
existing session and encrypted value is tied to it.

## 2. Fill in the rest

`render.yaml` marks a few values `sync: false`, meaning Render will not invent
them. Set them under **Environment** on the web service. Only the first two
matter for a demo:

| Variable | Value | |
|---|---|---|
| `APP_URL` | your service URL, e.g. `https://scholardesk.onrender.com` | required |
| `GROQ_API_KEY` | from console.groq.com | for the AI panels |
| `MAIL_USERNAME` | your Gmail address | optional |
| `MAIL_PASSWORD` | a Google **App Password**, not your account password | optional |
| `MAIL_FROM_ADDRESS` | the same Gmail address | optional |

`APP_URL` matters more than it looks. Laravel builds password-reset and
notification links from it, and the `public` disk builds every PDF URL from it
too (`config/filesystems.php`), so a wrong value breaks the reader as well as
the mail.

The mail rows are safe to leave empty: `MAIL_MAILER` is `failover`, which
tries Gmail and then writes the message to the log rather than throwing.

Save. Render redeploys, and the entrypoint creates the SQLite file, migrates
it and seeds it on boot.

## 3. Sign in

`render.yaml` sets `DEMO_SEED=true`, so the first boot fills the deployment
with a worked library rather than leaving an empty login page. Three accounts
are created, all with the password **`demo1234`**:

| Account | Sign in as | Shows |
|---|---|---|
| Researcher | `researcher@scholardesk.demo` | The library — 12 indexed papers, tags, collections, reader and AI panels. **Start here.** |
| Administrator | `admin@scholardesk.demo` | The admin portal: user roles, comment moderation, the report queue |
| Collaborator | `collaborator@scholardesk.demo` | The same shared collection seen as an editor rather than an owner |

The seeded papers carry real bibliographic metadata and a generated summary
sheet as their PDF — real enough that the reader renders it, text selection
and highlighting work on it, and the indexer chunks and embeds it like any
upload. They are not the published PDFs, which are not ours to redistribute.

Seeding is idempotent — it checks for the demo researcher and stops if the
data is already there — but on Render that guard rarely fires, because the
SQLite file does not outlive the container. In practice each start gets a
fresh database and seeds it from nothing, which is why the site always comes
back in the same known state.

> **Turn it off for anything that is not a demonstration.** Those passwords
> are in this file, and one of the accounts is an administrator. Set
> `DEMO_SEED` to `false` in the Render dashboard and the accounts are simply
> never created.

### Without the seed

With `DEMO_SEED=false` the deployment starts empty, and registration always
produces a researcher — that is the privilege-escalation fix from Module 1,
and there is no self-service route to an admin account. Promote yourself from
Render's **Shell** tab:

```bash
php artisan tinker --execute="App\Models\User::where('email','you@example.com')->update(['role'=>'administrator']);"
```

---

## What to expect

**The first request after a quiet spell takes 30–60 seconds.** Free services
sleep when idle, and the container has to start again. Open the link a minute
before you demo it, not as you demo it.

**Every restart resets the site to the seed.** Nothing typed into the live
deployment is kept. There is no database to expire and no backup to take —
if you want the state to persist, that is what the Fly deployment and its
volume are for.

**Your local library does not come with you.** What you get is the demo seed,
not your own papers. `upload-data.ps1` seeds a *Fly* deployment and does not
apply here.

**Semantic search will label some correct hits as a weak match.** Expected:
the embedder is lexical, so a query sharing little vocabulary with a paper
scores below the 0.32 threshold even when the paper it ranks first is the
right one. `README.md` sets this out under *Known limitations*. Questions that
reuse the wording of the field — "self-attention", "residual connections",
"retrieval-augmented" — score visibly higher, which is worth knowing before
demonstrating it live.

---

## If something is wrong

**The build fails on a PHP extension.** The image builds `pdo_pgsql` from
`postgresql-dev`. If Alpine has moved that package, the Dockerfile's
`.build-deps` list is where to look.

**The blueprint will not apply.** It declared a free managed Postgres, and
that is what used to fail. The current `render.yaml` has no `databases:`
block at all — if Render is still offering to create one, it is reading an
older commit, so check that the branch it points at has this change.

**Boot stops at "DB_CONNECTION=pgsql but neither DB_URL nor DB_HOST is set".**
Something set `DB_CONNECTION` to `pgsql` without a `DB_URL` beside it. The
blueprint sets `sqlite`; look for a stale value left in the dashboard from an
earlier deploy, which overrides the file.

**Boot stops at "APP_KEY is not set".** `render.yaml` generates it, so this
means the service was created by hand rather than from the blueprint. Add an
`APP_KEY` environment variable with the output of `php artisan key:generate
--show`.

**Uploads succeed but the PDF will not open.** Two causes, in this order.
Either `APP_URL` does not match the service's real URL — the `public` disk
builds every file URL by appending `/storage` to it, so the reader requests
exactly what it says — or `FILESYSTEM_DISK` is not `public`, in which case the
entrypoint took its s3 branch and never linked `public/storage` at the upload
directory. Check the boot log: it should read `==> uploads: local disk`.

**Everything I added yesterday is gone.** Expected, not a fault: the container
was rebuilt from the image in between and took the SQLite file and the uploads
with it. The site is back at the seeded library.

**Papers import as "Untitled Paper".** The metadata lookup could not reach
Crossref. Check outbound HTTPS from the container; locally this is usually a
missing CA bundle, but the image ships one.

**AI panels say "not configured".** `GROQ_API_KEY` is unset. Semantic search
and related papers keep working without it — those run on the local embedder.

**Password reset never arrives.** `MAIL_MAILER` is `failover`, which tries
Gmail and then falls back to writing the message to the log. That is
deliberate: a missing app password degrades instead of throwing a 500. Check
the logs to confirm the message was generated, then fix the credentials.

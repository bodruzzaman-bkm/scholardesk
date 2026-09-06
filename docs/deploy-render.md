# Deploying ScholarDesk on Render (free tier)

A free host that gives you a public URL **without a credit card**. The
trade-off is that a free web service has no persistent disk, so the database
moves off the container to a managed Postgres — which `render.yaml` creates
for you, alongside the web service.

> **Prefer Fly?** `deploy.ps1` and `fly.toml` still work — one volume at
> `/data` keeps the database *and* the uploads, so nothing is lost on a
> restart. Fly now needs a card on file, though it does not bill an app this
> size. Both deployments share the same Dockerfile and entrypoint.

### What survives a restart, and what does not

A free container's filesystem is rebuilt from the image every time it wakes,
and it sleeps after about 15 minutes of inactivity.

| | Survives |
|---|---|
| Accounts, papers, notes, highlights, tags, collections, comments, activity | **Yes** — all in Postgres |
| The PDF *bytes* of a paper uploaded on the live site | **No** — gone at the next restart |

The paper record stays; only the file behind it disappears, so the reader
shows nothing for it afterwards. For a demo this is a non-issue — the
container stays awake throughout — but do not treat the live site as storage.

Object storage would fix it and is deliberately not wired up: the app never
consults the default disk. Every PDF path names `Storage::disk('public')`
explicitly, and `PdfTextService` calls `->path()` on it, which only a local
disk implements. Pointing `FILESYSTEM_DISK` at s3 therefore moved no uploads —
it only skipped the `public/storage` symlink and made every PDF 404. Real
object-storage support would mean changing those call sites, not an env var.

---

## Before you start

Two accounts, both free, neither asking for a card:

1. **Render** — <https://render.com>, signed in with GitHub
2. **Groq** — <https://console.groq.com/keys>, for the AI features

And your code pushed to GitHub, which it already is.

## 1. Create the Render services

1. Render dashboard → **New → Blueprint**.
2. Point it at your GitHub repository. Render reads `render.yaml` and proposes
   a web service plus a Postgres database.
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

Save. Render redeploys, and the entrypoint runs the migrations against
Postgres on boot.

## 3. Create an administrator

Registration always produces a researcher — that is the privilege-escalation
fix from Module 1, and there is no self-service route to an admin account.
Promote yourself from Render's **Shell** tab:

```bash
php artisan tinker --execute="App\Models\User::where('email','you@example.com')->update(['role'=>'administrator']);"
```

---

## What to expect

**The first request after a quiet spell takes 30–60 seconds.** Free services
sleep when idle, and the container has to start again. Open the link a minute
before you demo it, not as you demo it.

**Free Postgres instances expire.** Check the current term in the Render
dashboard and take a dump before it lapses:

```bash
pg_dump "$DB_URL" > scholardesk-backup.sql
```

**Your local library does not come with you.** The deployment starts empty.
`upload-data.ps1` seeds a *Fly* deployment and does not apply here — on Render
you re-add papers through the interface, or import a dump into Postgres.

---

## If something is wrong

**The build fails on a PHP extension.** The image builds `pdo_pgsql` from
`postgresql-dev`. If Alpine has moved that package, the Dockerfile's
`.build-deps` list is where to look.

**Boot stops at "DB_CONNECTION=pgsql but neither DB_URL nor DB_HOST is set".**
The blueprint did not wire the database. Check the web service has a `DB_URL`
sourced from `scholardesk-db`. Note it is `DB_URL`, not `DATABASE_URL` —
`config/database.php` reads the former.

**Uploads succeed but the PDF will not open.** Two causes, in this order.
Either `APP_URL` does not match the service's real URL — the `public` disk
builds every file URL by appending `/storage` to it, so the reader requests
exactly what it says — or `FILESYSTEM_DISK` is not `public`, in which case the
entrypoint took its s3 branch and never linked `public/storage` at the upload
directory. Check the boot log: it should read `==> uploads: local disk`.

**A PDF that opened yesterday is blank today.** Expected, not a fault. The
container was rebuilt from the image in between and the uploaded file went
with it; the paper record in Postgres outlived its bytes. Re-upload the PDF on
the paper's page.

**Papers import as "Untitled Paper".** The metadata lookup could not reach
Crossref. Check outbound HTTPS from the container; locally this is usually a
missing CA bundle, but the image ships one.

**AI panels say "not configured".** `GROQ_API_KEY` is unset. Semantic search
and related papers keep working without it — those run on the local embedder.

**Password reset never arrives.** `MAIL_MAILER` is `failover`, which tries
Gmail and then falls back to writing the message to the log. That is
deliberate: a missing app password degrades instead of throwing a 500. Check
the logs to confirm the message was generated, then fix the credentials.

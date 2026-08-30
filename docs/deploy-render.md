# Deploying ScholarDesk on Render (free tier)

A free host that gives you a public URL without a credit card. The trade-off
is that a free web service has **no persistent disk**, so the two things
ScholarDesk normally keeps on one have to move off it:

| Normally | On Render |
|---|---|
| SQLite file on a volume | a managed **Postgres** database |
| PDFs in `storage/app/public` on a volume | **Cloudflare R2** object storage |

`render.yaml` declares the web service and the database. R2 is the one part
Render cannot create for you.

> **Prefer Fly?** `deploy.ps1` and `fly.toml` still work and are simpler —
> one volume, no object storage, no Postgres. Fly now needs a card on file,
> though it does not bill an app this size. Both deployments share the same
> Dockerfile and entrypoint.

---

## Before you start

Three accounts, all free:

1. **Render** — <https://render.com>
2. **Cloudflare** — for R2 object storage
3. **Groq** — <https://console.groq.com/keys>, for the AI features

And your code pushed to GitHub, which it already is.

---

## 1. Create the R2 bucket

R2 is S3-compatible, so Laravel's existing `s3` disk talks to it unchanged.

1. Cloudflare dashboard → **R2** → **Create bucket**, name it `scholardesk`.
2. **Settings → Public access** → enable a public URL (or attach a custom
   domain). Copy that URL — it becomes `AWS_URL`, and it is what the PDF
   reader fetches from.
3. **Manage R2 API Tokens** → **Create API token**, with *Object Read & Write*
   on that bucket. Copy the Access Key ID and Secret.
4. Note your account ID from the R2 endpoint:
   `https://<account-id>.r2.cloudflarestorage.com`

Without these the app still boots — uploads just vanish on the next restart.

## 2. Create the Render services

1. Render dashboard → **New → Blueprint**.
2. Point it at your GitHub repository. Render reads `render.yaml` and proposes
   a web service plus a Postgres database.
3. Apply. The first build takes several minutes: it compiles the front-end on
   Node 22, installs PHP dependencies, and builds the PHP extensions.

`APP_KEY` is generated automatically. Do not change it afterwards — every
existing session and encrypted value is tied to it.

## 3. Fill in the secrets

`render.yaml` marks ten values `sync: false`, meaning Render will not invent
them. Set them under **Environment** on the web service:

| Variable | Value |
|---|---|
| `APP_URL` | your service URL, e.g. `https://scholardesk.onrender.com` |
| `AWS_ACCESS_KEY_ID` | R2 access key |
| `AWS_SECRET_ACCESS_KEY` | R2 secret |
| `AWS_BUCKET` | `scholardesk` |
| `AWS_ENDPOINT` | `https://<account-id>.r2.cloudflarestorage.com` |
| `AWS_URL` | the bucket's public URL |
| `GROQ_API_KEY` | from console.groq.com |
| `MAIL_USERNAME` | your Gmail address |
| `MAIL_PASSWORD` | a Google **App Password**, not your account password |
| `MAIL_FROM_ADDRESS` | the same Gmail address |

`APP_URL` matters more than it looks: Laravel builds password-reset and
notification links from it, so a wrong value sends people to the wrong host.

Save. Render redeploys, and the entrypoint runs the migrations against
Postgres on boot.

## 4. Create an administrator

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

**Uploads succeed but the PDF will not open.** `AWS_URL` is wrong or the
bucket is not public. `Storage::url()` returns that value verbatim, so the
reader requests exactly what it says.

**Papers import as "Untitled Paper".** The metadata lookup could not reach
Crossref. Check outbound HTTPS from the container; locally this is usually a
missing CA bundle, but the image ships one.

**AI panels say "not configured".** `GROQ_API_KEY` is unset. Semantic search
and related papers keep working without it — those run on the local embedder.

**Password reset never arrives.** `MAIL_MAILER` is `failover`, which tries
Gmail and then falls back to writing the message to the log. That is
deliberate: a missing app password degrades instead of throwing a 500. Check
the logs to confirm the message was generated, then fix the credentials.

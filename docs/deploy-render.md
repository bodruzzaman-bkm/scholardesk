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
3. Apply. The build takes a couple of minutes: it compiles the front-end on
   Node 22, installs PHP dependencies, and builds two PHP extensions.

## 2. Fill in the rest

`render.yaml` marks a few values `sync: false`, meaning Render will not invent
them. Set them under **Environment** on the web service. Only the first two
matter for a demo:

| Variable | Value | |
|---|---|---|
| `APP_KEY` | output of `php artisan key:generate --show` | **required** |
| `APP_URL` | your service URL, e.g. `https://scholardesk-nfs9.onrender.com` | required |
| `GROQ_API_KEY` | from console.groq.com | for the AI panels |

`APP_URL` matters more than it looks. Laravel builds password-reset and
notification links from it, and the `public` disk builds every PDF URL from it
too (`config/filesystems.php`), so a wrong value breaks the reader as well as
the mail.

Mail needs nothing set by hand. `render.yaml` ships working Ethereal SMTP
credentials, so the deployment sends real mail out of the box — see
[Mail](#mail) below.

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

## Mail

**SMTP cannot work on a free instance, and trying takes the site down.**

Render blocks outbound traffic to ports 25, 465 and 587 on free web services.
That is every port an SMTP server listens on, so no provider avoids it — the
port is the problem, not the host. Worse, the traffic is dropped rather than
refused, so the connection hangs instead of failing. `docker/entrypoint.sh`
serves with `php artisan serve`, a single-process server, so one request stuck
on a mail socket stops the site answering anything at all until the platform
restarts the container. A notification email becomes an outage.

So this deployment does not use SMTP. `MAIL_MAILER` is **`resend`**, which
hands the message to Resend's HTTPS API on port 443 — not blocked, and nothing
to hang on. As a second line of defence `config/mail.php` sets an SMTP
`timeout` of 10 seconds rather than Laravel's `null`, which inherits PHP's
60-second `default_socket_timeout`: if SMTP is ever switched back on here by
mistake, it now fails as a caught error instead of as downtime.

### The one thing to set

`RESEND_API_KEY`, in the Render dashboard. Sign up at
[resend.com](https://resend.com), create an API key, paste it in, save.

Until it is set, **Settings → Notifications & email** reports the mailer as
unconfigured and says so plainly, rather than showing green over a transport
that cannot send.

Two limits apply to a Resend account with no verified domain:

- the sender must be `onboarding@resend.dev`, which is what `MAIL_FROM_ADDRESS`
  is set to;
- it will only deliver to the address the account was opened with. The seeded
  `@scholardesk.demo` users therefore cannot receive anything — register an
  account on the site using your own address to watch mail arrive.

Verifying a domain in the Resend dashboard lifts both.

### Other routes

- **Upgrade to any paid instance.** The port block is free-tier only. Set
  `MAIL_MAILER=failover` and the Ethereal credentials already in `render.yaml`
  work immediately, with no signup at all.
- **Postmark or Mailgun** instead of Resend — both publish an HTTPS API, and
  `config/mail.php` already declares the Postmark transport.
- **Demonstrate it locally**, where nothing is blocked and the committed
  Ethereal credentials work as they stand. See below.

### Ethereal

The credentials in `render.yaml` are for **Ethereal**, a throwaway capture
mailbox: it accepts mail over real SMTP with real authentication, then
publishes it on the web instead of delivering it onward. Read what has been
sent by signing in at <https://ethereal.email> with that `MAIL_USERNAME` and
`MAIL_PASSWORD`.

Ethereal rather than Gmail, because Gmail refuses an ordinary account password
over SMTP and demands an App Password, which requires 2-Step Verification on
somebody's personal Google account — not a credential a shared demo can carry.
It also accepts any recipient, which matters here: every seeded account lives
at the fake `@scholardesk.demo` domain that no real mail server can deliver to,
so with Gmail the demo accounts and working mail were mutually exclusive.

To run the demo locally, set `MAIL_MAILER=failover` in `.env` alongside those
credentials and `php artisan serve`. Sending works, and the messages appear in
the Ethereal web inbox.

### The settings card

Users control their own email under **Settings → Notifications & email**. The
card reports whether mail is configured and through which transport, each
notification type can be switched off individually, and *Send test email*
surfaces a failure rather than swallowing it. Turning a type off silences only
the email — the in-app notification and the bell badge are unaffected.

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

**Boot stops at "APP_KEY is not set".** Set it — the blueprint marks it
`sync: false` on purpose and cannot invent one. Use the full output of
`php artisan key:generate --show`, including the `base64:` prefix.

**Every page 500s but `/up` returns 200, and the log says "Unsupported cipher
or incorrect key length".** `APP_KEY` is present but is not a Laravel key.
The Encrypter measures it against AES-256-CBC, which wants exactly 32 bytes,
so any other random string fails — this is what Render's `generateValue: true`
produced before the blueprint stopped using it. The failure happens inside the
cookie middleware, which is why `/up` is unaffected and the service reports
itself healthy while serving nothing. Replace the value with the output of
`php artisan key:generate --show`.

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

**Password reset never arrives.** Look for it at <https://ethereal.email>, not
in a real inbox — see [Mail](#mail). If it is not there either, `MAIL_MAILER`
is `failover`, which tries SMTP and then falls back to writing the message to
the log. That is deliberate: broken credentials degrade instead of throwing a
500. Check the logs to confirm the message was generated, then fix the
credentials. **Settings → Notifications & email** reports the transport in use
and will send a test message whose failure is shown rather than swallowed.

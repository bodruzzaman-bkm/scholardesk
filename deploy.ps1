# ScholarDesk - deploy to Fly.io
#
# Deploys straight from this folder. No GitHub, no git remote, no Docker
# needed locally: Fly builds the image on its own builders.
#
#   Usage:  .\deploy.ps1
#
# Re-running it after a code change just redeploys; the volume, the database
# and the uploaded PDFs are left alone.

Set-Location $PSScriptRoot

# NOTE: deliberately NOT 'Stop'.
#
# In Windows PowerShell 5.1, redirecting a native program's stderr (2>&1)
# wraps each line in an ErrorRecord, and under 'Stop' that terminates the
# script even when the program exited 0. flyctl writes ordinary status text to
# stderr - "no access token available" on a first run, for instance - so a
# 'Stop' preference aborts the script on a perfectly normal condition.
# Failures are detected from $LASTEXITCODE instead, which is what actually
# reflects success.
$ErrorActionPreference = 'Continue'

function Step($msg) { Write-Host "`n==> $msg" -ForegroundColor Cyan }
function Ok($msg)   { Write-Host "    $msg" -ForegroundColor Green }
function Warn($msg) { Write-Host "    $msg" -ForegroundColor Yellow }
function Fail($msg) { Write-Host "`n!!! $msg" -ForegroundColor Red; exit 1 }

# Runs flyctl, capturing output and the real exit code without letting stderr
# masquerade as a fatal error.
function Invoke-Fly {
    param([string[]]$FlyArgs)

    # Each stderr line arrives as an ErrorRecord, which PowerShell would print
    # as a red error block even when nothing is wrong. Converting to plain
    # strings as they stream keeps both channels while rendering quietly.
    $out = & flyctl @FlyArgs 2>&1 | ForEach-Object { $_.ToString() } | Out-String

    return [pscustomobject]@{
        Output = $out
        Code   = $LASTEXITCODE
        Ok     = ($LASTEXITCODE -eq 0)
    }
}

# --- 1. flyctl -------------------------------------------------------------
Step 'Checking for flyctl'

$flyBin = Join-Path $env:USERPROFILE '.fly\bin'
if (Test-Path $flyBin) { $env:PATH = "$flyBin;$env:PATH" }

if (-not (Get-Command flyctl -ErrorAction SilentlyContinue)) {
    Warn 'flyctl not found - installing it for the current user'
    iwr https://fly.io/install.ps1 -useb | iex
    $env:PATH = "$flyBin;$env:PATH"
}

if (-not (Get-Command flyctl -ErrorAction SilentlyContinue)) {
    Fail "flyctl installed but is not on PATH. Close this window, open a new PowerShell, and run .\deploy.ps1 again."
}
Ok ((Invoke-Fly @('version')).Output.Trim())

# --- 2. Account ------------------------------------------------------------
Step 'Checking your Fly account'

$who = Invoke-Fly @('auth', 'whoami')
if (-not $who.Ok) {
    Warn 'Not signed in. A browser window will open now.'
    Warn 'Create a free Fly account (or sign in), then return to this window.'

    # Interactive: must NOT capture its output or the browser prompt is hidden.
    & flyctl auth login
    if ($LASTEXITCODE -ne 0) { Fail 'Sign-in did not complete. Run .\deploy.ps1 again once you are signed in.' }

    $who = Invoke-Fly @('auth', 'whoami')
    if (-not $who.Ok) { Fail 'Still not signed in. Try: flyctl auth login' }
}
Ok "signed in as $($who.Output.Trim())"

# --- 3. App ----------------------------------------------------------------
$appName = (Select-String -Path fly.toml -Pattern '^app\s*=\s*"(.+)"').Matches[0].Groups[1].Value
Step "Ensuring the app '$appName' exists"

$status = Invoke-Fly @('status', '--app', $appName)
if (-not $status.Ok) {
    Warn "creating '$appName'"
    $create = Invoke-Fly @('apps', 'create', $appName)
    if (-not $create.Ok) {
        Write-Host $create.Output
        Fail "Could not create '$appName'. App names are global - open fly.toml, change the 'app' line to something unique, and run this again."
    }
}
Ok 'app ready'

# --- 4. Persistent volume --------------------------------------------------
# Without this the database and every uploaded PDF are wiped on each deploy.
Step 'Ensuring the persistent volume exists'

$vols = Invoke-Fly @('volumes', 'list', '--app', $appName)
if ($vols.Output -notmatch 'scholardesk_data') {
    $region = (Select-String -Path fly.toml -Pattern '^primary_region\s*=\s*"(.+)"').Matches[0].Groups[1].Value
    Warn "creating a 1 GB volume in $region"
    $mk = Invoke-Fly @('volumes', 'create', 'scholardesk_data', '--app', $appName, '--region', $region, '--size', '1', '--yes')
    if (-not $mk.Ok) { Write-Host $mk.Output; Fail 'Could not create the volume.' }
    Ok 'volume created'
} else {
    Ok 'volume already exists - your data will be preserved'
}

# --- 5. Secrets ------------------------------------------------------------
# Kept out of the image and out of fly.toml, which is a plain-text file.
Step 'Setting secrets'

$secrets = (Invoke-Fly @('secrets', 'list', '--app', $appName)).Output

if ($secrets -notmatch 'APP_KEY') {
    $key = (& php artisan key:generate --show).Trim()
    if (-not $key) { Fail 'Could not generate an APP_KEY. Is php on PATH?' }
    $r = Invoke-Fly @('secrets', 'set', "APP_KEY=$key", '--app', $appName, '--stage')
    if ($r.Ok) { Ok 'APP_KEY generated' } else { Write-Host $r.Output; Fail 'Could not set APP_KEY.' }
} else {
    Ok 'APP_KEY already set'
}

# Read the AI key out of the local .env so it is never typed or committed.
$groqLine = Select-String -Path .env -Pattern '^GROQ_API_KEY=(.+)$' -ErrorAction SilentlyContinue
if ($groqLine -and $secrets -notmatch 'GROQ_API_KEY') {
    $groqKey = $groqLine.Matches[0].Groups[1].Value.Trim()
    $r = Invoke-Fly @('secrets', 'set', "GROQ_API_KEY=$groqKey", '--app', $appName, '--stage')
    if ($r.Ok) { Ok 'GROQ_API_KEY copied from .env' } else { Warn 'Could not set GROQ_API_KEY; AI features will be off.' }
} elseif ($secrets -match 'GROQ_API_KEY') {
    Ok 'GROQ_API_KEY already set'
} else {
    Warn 'No GROQ_API_KEY found - AI summaries and Q&A will show "not configured".'
    Warn 'Everything else, including semantic search, still works.'
}

# --- 6. Deploy -------------------------------------------------------------
Step 'Building and deploying (the first run takes a few minutes)'

# Streamed, not captured, so you can watch the build and see any error.
& flyctl deploy --app $appName --ha=false
if ($LASTEXITCODE -ne 0) {
    Fail "Deploy failed. Copy the error above. Logs: flyctl logs --app $appName"
}

# --- 7. Done ---------------------------------------------------------------
Step 'Done'
$url = "https://$appName.fly.dev"
Write-Host "`n    Your site: $url" -ForegroundColor Green
Write-Host "    Share that link - it stays up whether or not your PC is on.`n"

Write-Host "    Next:" -ForegroundColor DarkGray
Write-Host "      .\upload-data.ps1                 # copy your papers to the live site" -ForegroundColor DarkGray
Write-Host "      flyctl logs --app $appName" -ForegroundColor DarkGray
Write-Host "      flyctl ssh console --app $appName" -ForegroundColor DarkGray
Write-Host "      .\deploy.ps1                      # redeploy after a change" -ForegroundColor DarkGray

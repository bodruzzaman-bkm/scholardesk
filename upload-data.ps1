# ScholarDesk - copy your local library up to the deployed site.
#
# A fresh deployment starts with an empty volume, so the site has no papers and
# no accounts. This copies the local SQLite database and the uploaded PDFs onto
# the volume, so the deployed site looks exactly like your machine.
#
#   Usage:  .\upload-data.ps1
#
# Run it AFTER .\deploy.ps1 has succeeded at least once.
#
# WARNING: this REPLACES the deployed database. Anything your teammate created
# on the live site is overwritten. It is for seeding, not for syncing.

Set-Location $PSScriptRoot

# Not 'Stop': flyctl writes progress to stderr, and in PowerShell 5.1 a
# redirected native stderr becomes a terminating error even on success.
# Exit codes are checked instead.
$ErrorActionPreference = 'Continue'

function Step($msg) { Write-Host "`n==> $msg" -ForegroundColor Cyan }
function Ok($msg)   { Write-Host "    $msg" -ForegroundColor Green }
function Fail($msg) { Write-Host "`n!!! $msg" -ForegroundColor Red; exit 1 }

$flyBin = Join-Path $env:USERPROFILE '.fly\bin'
if (Test-Path $flyBin) { $env:PATH = "$flyBin;$env:PATH" }

if (-not (Get-Command flyctl -ErrorAction SilentlyContinue)) {
    Fail 'flyctl not found. Run .\deploy.ps1 first.'
}

$appName = (Select-String -Path fly.toml -Pattern '^app\s*=\s*"(.+)"').Matches[0].Groups[1].Value

Write-Host "This REPLACES the database on ${appName}.fly.dev with your local one." -ForegroundColor Yellow
Write-Host "Anything created on the live site will be lost." -ForegroundColor Yellow
$answer = Read-Host "Type 'yes' to continue"
if ($answer -ne 'yes') { Write-Host 'Cancelled.'; exit 0 }

# Sends one command to flyctl's sftp shell over stdin.
function Send-Sftp([string]$command) {
    # ToString() keeps stderr from rendering as a red error block; the exit
    # code is what actually decides success.
    $command | & flyctl ssh sftp shell --app $appName 2>&1 |
        ForEach-Object { $_.ToString() } | Out-Null
    return ($LASTEXITCODE -eq 0)
}

# --- Database --------------------------------------------------------------
Step 'Uploading the database'

if (-not (Test-Path 'database/database.sqlite')) {
    Fail 'database/database.sqlite not found.'
}

if (Send-Sftp 'put database/database.sqlite /data/database.sqlite') {
    Ok 'database uploaded'
} else {
    Fail "Upload failed. Is the machine running? Check: flyctl status --app $appName"
}

# --- PDFs ------------------------------------------------------------------
# Sent one at a time: the sftp shell has no recursive put, and this way a
# single failed file is visible rather than silently skipped.
Step 'Uploading the PDFs'

$pdfs = Get-ChildItem 'storage/app/public/papers' -Filter *.pdf -ErrorAction SilentlyContinue

if (-not $pdfs) {
    Ok 'no local PDFs to upload'
} else {
    & flyctl ssh console --app $appName --command 'mkdir -p /data/uploads/papers' 2>&1 | Out-Null

    $failed = @()
    $i = 0
    foreach ($pdf in $pdfs) {
        $i++
        Write-Host "    [$i/$($pdfs.Count)] $($pdf.Name)"

        # Built by concatenation: the path needs literal double quotes around
        # it, and nesting those inside an interpolated string is where
        # PowerShell quoting goes wrong.
        $cmd = 'put "' + $pdf.FullName + '" /data/uploads/papers/' + $pdf.Name
        if (-not (Send-Sftp $cmd)) { $failed += $pdf.Name }
    }

    if ($failed.Count) {
        Write-Host "    could not upload: $($failed -join ', ')" -ForegroundColor Yellow
    }
    Ok "$($pdfs.Count - $failed.Count) of $($pdfs.Count) PDF(s) uploaded"
}

# --- Ownership -------------------------------------------------------------
# The app runs as www-data; files arrive owned by root.
Step 'Fixing permissions'
& flyctl ssh console --app $appName --command 'chown -R www-data:www-data /data' 2>&1 | Out-Null
Ok 'done'

Write-Host "`n    Visit https://${appName}.fly.dev - your library should be there.`n" -ForegroundColor Green

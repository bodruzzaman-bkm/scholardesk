# Module 2 - Reading, Annotation & Single-Paper AI
#
# Copies the live source files that implement requirements 7-10 into .\code\,
# keeping their real project-relative paths.
#
#   Usage:  .\sync.ps1        (run from inside the "module 2" folder)
#
# Re-run it after changing any of these files in the app, so the submission
# copy never drifts from the code that actually runs. ASCII only, on purpose:
# Windows PowerShell 5.1 reads a BOM-less UTF-8 file as Windows-1252, and a
# stray em-dash then breaks the parser.

Set-Location $PSScriptRoot

$ErrorActionPreference = 'Stop'

$projectRoot = Resolve-Path '..'
$dest        = Join-Path $PSScriptRoot 'code'

$files = @(
    # -- Req 7: in-browser reader + coloured highlights -------------------
    'app/Http/Controllers/PaperController.php',
    'app/Http/Controllers/HighlightController.php',
    'app/Models/Highlight.php',
    'app/Policies/HighlightPolicy.php',
    'app/Policies/PaperPolicy.php',
    'resources/views/papers/read.blade.php',
    'database/migrations/2026_08_15_183612_create_highlights_table.php',

    # -- Req 8: markdown notes ---------------------------------------------
    'app/Http/Controllers/NoteController.php',
    'app/Models/Note.php',
    'app/Policies/NotePolicy.php',
    'app/Support/Markdown.php',
    'resources/views/notes/edit.blade.php',
    'database/migrations/2026_08_16_000000_create_notes_table.php',

    # -- Reqs 9 and 10: single-paper summary and Q&A ----------------------
    'app/Http/Controllers/AiController.php',
    'app/Services/RagService.php',
    'app/Services/AiService.php',
    'app/Services/VectorSearchService.php',
    'app/Services/EmbeddingService.php',
    'app/Models/ChatSession.php',
    'app/Models/ChatMessage.php',
    'app/Exceptions/AiUnavailableException.php',
    'resources/views/components/ai/summary.blade.php',
    'resources/views/components/ai/chat.blade.php',
    'resources/views/components/ai/runtime.blade.php',
    'database/migrations/2026_08_21_150000_create_ai_tables.php',

    # -- Indexing pipeline: what makes req 10 possible at all -------------
    'app/Services/PdfTextService.php',
    'app/Services/ChunkService.php',
    'app/Services/IndexingService.php',
    'app/Jobs/IndexPaper.php',
    'app/Models/PaperChunk.php',

    # -- Tests --------------------------------------------------------------
    'tests/Feature/HighlightTest.php',
    'tests/Feature/NoteTest.php',
    'tests/Feature/NoteSecurityTest.php',
    'tests/Feature/PaperAiSectionsTest.php',
    'tests/Feature/ReaderAiTest.php',
    'tests/Unit/ChunkServiceTest.php',
    'tests/Unit/RunOnTextTest.php',
    'tests/Unit/EmbeddingServiceTest.php'
)

if (Test-Path $dest) { Remove-Item $dest -Recurse -Force }
New-Item -ItemType Directory -Path $dest | Out-Null

$copied  = 0
$missing = @()

foreach ($rel in $files) {
    $src = Join-Path $projectRoot $rel

    if (-not (Test-Path $src)) { $missing += $rel; continue }

    $target = Join-Path $dest $rel
    $dir    = Split-Path $target -Parent
    if (-not (Test-Path $dir)) { New-Item -ItemType Directory -Path $dir -Force | Out-Null }

    Copy-Item $src $target
    $copied++
}

# The routes excerpt is authored here rather than copied: the app keeps all
# four modules' routes in one shared routes/web.php, so there is no "module 2
# routes" file to copy. It is written into code\routes\ after the rebuild,
# because the wipe above would otherwise remove it.
$routesSrc = Join-Path $PSScriptRoot 'routes-excerpt.php'
if (Test-Path $routesSrc) {
    $routesDir = Join-Path $dest 'routes'
    New-Item -ItemType Directory -Path $routesDir -Force | Out-Null
    Copy-Item $routesSrc (Join-Path $routesDir 'module-2-routes.php')
    $copied++
} else {
    $missing += 'routes-excerpt.php'
}

Write-Host "Copied $copied file(s) into module 2\code" -ForegroundColor Green

if ($missing.Count) {
    Write-Host "MISSING (renamed or deleted in the app?):" -ForegroundColor Yellow
    $missing | ForEach-Object { Write-Host "    $_" -ForegroundColor Yellow }
    exit 1
}

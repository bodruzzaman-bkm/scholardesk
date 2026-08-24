# Module 3 - Advanced AI Research & Citation Export
#
# Copies the live source files that implement requirements 11-15 into
# .\code\, keeping their real project-relative paths.
#
#   Usage:  .\sync.ps1        (run from inside the "module 3" folder)
#
# Re-run it after changing any of these files in the app, so the submission
# copy never drifts from the code that actually runs. ASCII only, on purpose:
# Windows PowerShell 5.1 reads a BOM-less UTF-8 file as Windows-1252, and a
# stray em-dash then breaks the parser.

Set-Location $PSScriptRoot

$ErrorActionPreference = 'Stop'

$projectRoot = Resolve-Path '..'
$dest        = Join-Path $PSScriptRoot 'code'

# Every file below is a real file in the app, listed by the requirement it
# serves. Files appear once even when several requirements use them.
$files = @(
    # -- Req 11: collection-wide Q&A with citations ---------------------
    'app/Http/Controllers/AiController.php',
    'app/Services/RagService.php',
    'app/Services/AiService.php',
    'app/Models/ChatSession.php',
    'app/Models/ChatMessage.php',
    'app/Exceptions/AiUnavailableException.php',
    'resources/views/components/ai/runtime.blade.php',
    'resources/views/components/ai/chat.blade.php',
    'resources/views/components/ai/summary.blade.php',

    # -- Req 12: semantic + keyword search with filters -----------------
    'app/Http/Controllers/SearchController.php',
    'app/Services/VectorSearchService.php',
    'app/Services/EmbeddingService.php',
    'app/Services/PaperService.php',
    'resources/views/search/index.blade.php',

    # -- Req 13: related papers -----------------------------------------
    'resources/views/components/ai/related.blade.php',

    # -- Req 14: literature-review draft --------------------------------
    'app/Models/LiteratureReview.php',
    'resources/views/components/ai/review.blade.php',

    # -- Req 15: citation export ----------------------------------------
    'app/Services/CitationService.php',

    # -- Indexing pipeline: what makes 11-14 possible at all ------------
    'app/Services/ChunkService.php',
    'app/Services/IndexingService.php',
    'app/Services/PdfTextService.php',
    'app/Jobs/IndexPaper.php',
    'app/Models/PaperChunk.php',
    'database/migrations/2026_08_21_150000_create_ai_tables.php',

    # -- Tests -----------------------------------------------------------
    'tests/Feature/AiLayerTest.php',
    'tests/Feature/SemanticSearchTest.php',
    'tests/Feature/SearchRelevanceTest.php',
    'tests/Feature/ReviewSelectionTest.php',
    'tests/Feature/CitationMatchingTest.php',
    'tests/Unit/CitationServiceTest.php',
    'tests/Unit/EmbeddingServiceTest.php',
    'tests/Unit/ChunkServiceTest.php',
    'tests/Unit/RunOnTextTest.php'
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
# four modules' routes in one shared routes/web.php, so there is no
# "module 3 routes" file to copy. It is written into code\routes\ after the
# rebuild, because the wipe above would otherwise remove it.
$routesSrc = Join-Path $PSScriptRoot 'routes-excerpt.php'
if (Test-Path $routesSrc) {
    $routesDir = Join-Path $dest 'routes'
    New-Item -ItemType Directory -Path $routesDir -Force | Out-Null
    Copy-Item $routesSrc (Join-Path $routesDir 'module-3-routes.php')
    $copied++
} else {
    $missing += 'routes-excerpt.php'
}

Write-Host "Copied $copied file(s) into module 3\code" -ForegroundColor Green

if ($missing.Count) {
    Write-Host "MISSING (renamed or deleted in the app?):" -ForegroundColor Yellow
    $missing | ForEach-Object { Write-Host "    $_" -ForegroundColor Yellow }
    exit 1
}

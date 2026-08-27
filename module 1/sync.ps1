# Module 1 - User Accounts & Core Library Management
#
# Copies the live source files that implement the authentication features and
# requirements 1-6 into .\code\, keeping their real project-relative paths.
#
#   Usage:  .\sync.ps1        (run from inside the "module 1" folder)
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
    # -- Authentication features -----------------------------------------
    'app/Http/Controllers/Auth/RegisteredUserController.php',
    'app/Http/Controllers/Auth/AuthenticatedSessionController.php',
    'app/Http/Controllers/Auth/PasswordResetLinkController.php',
    'app/Http/Controllers/Auth/NewPasswordController.php',
    'app/Http/Requests/Auth/LoginRequest.php',
    'app/Models/User.php',
    'resources/views/auth/register.blade.php',
    'resources/views/auth/login.blade.php',
    'resources/views/auth/forgot-password.blade.php',
    'resources/views/auth/reset-password.blade.php',
    'routes/auth.php',
    'database/migrations/0001_01_01_000000_create_users_table.php',

    # -- Req 1: two account types + Owner/Editor/Viewer -------------------
    'app/Enums/UserRole.php',
    'app/Enums/MemberRole.php',
    'app/Models/CollectionMember.php',
    'app/Http/Middleware/EnsureUserIsAdmin.php',
    'app/Policies/PaperPolicy.php',
    'app/Policies/CollectionPolicy.php',
    'app/Policies/TagPolicy.php',

    # -- Reqs 2 and 3: add papers, fetch metadata -------------------------
    'app/Http/Controllers/PaperController.php',
    'app/Services/PaperService.php',
    'app/Services/MetadataService.php',
    'app/Services/CrossRefService.php',
    'app/Http/Requests/StorePaperRequest.php',
    'app/Http/Requests/StorePapersBatchRequest.php',
    'app/Enums/PaperSource.php',
    'app/Support/Doi.php',
    'app/Support/UploadLimits.php',
    'resources/views/papers/create.blade.php',
    'database/migrations/2026_08_11_151330_create_papers_table.php',
    'database/migrations/2026_08_11_161316_add_metadata_to_papers_table.php',
    'database/migrations/2026_08_22_090000_add_url_to_papers.php',
    'database/migrations/2026_08_21_130000_scope_paper_doi_uniqueness_per_user.php',

    # -- Req 4: view / edit / delete + reading status ---------------------
    'app/Models/Paper.php',
    'app/Enums/ReadingStatus.php',
    'app/Http/Requests/UpdatePaperRequest.php',
    'resources/views/papers/index.blade.php',
    'resources/views/papers/show.blade.php',
    'resources/views/papers/edit.blade.php',
    'resources/views/components/reading-status.blade.php',
    'database/migrations/2026_08_12_062433_add_reading_status_to_papers_table.php',

    # -- Req 5: collections ------------------------------------------------
    'app/Http/Controllers/CollectionController.php',
    'app/Services/CollectionService.php',
    'app/Models/Collection.php',
    'app/Http/Requests/StoreCollectionRequest.php',
    'app/Http/Requests/UpdateCollectionRequest.php',
    'resources/views/collections/index.blade.php',
    'resources/views/collections/show.blade.php',
    'database/migrations/2026_08_11_151345_create_collections_table.php',
    'database/migrations/2026_08_11_151357_create_collection_paper_table.php',

    # -- Req 6: coloured tags ----------------------------------------------
    'app/Http/Controllers/TagController.php',
    'app/Models/Tag.php',
    'app/Http/Requests/StoreTagRequest.php',
    'app/Http/Requests/UpdateTagRequest.php',
    'resources/views/tags/index.blade.php',
    'database/migrations/2026_08_15_072609_create_tags_table.php',
    'database/migrations/2026_08_15_072610_create_paper_tag_table.php',

    # -- Shared schema integrity -------------------------------------------
    'database/migrations/2026_08_21_120000_add_integrity_constraints_to_library_tables.php',

    # -- Tests --------------------------------------------------------------
    'tests/Feature/Auth/RegistrationTest.php',
    'tests/Feature/Auth/AuthenticationTest.php',
    'tests/Feature/Auth/PasswordResetTest.php',
    'tests/Feature/RegistrationRoleTest.php',
    'tests/Feature/AuthorizationTest.php',
    'tests/Feature/PaperLibraryTest.php',
    'tests/Feature/ImportByUrlTest.php',
    'tests/Feature/OpenAccessPdfTest.php',
    'tests/Feature/BulkUploadTest.php',
    'tests/Feature/ReadingStatusControlTest.php',
    'tests/Feature/CollectionTest.php',
    'tests/Feature/TagSmokeTest.php',
    'tests/Feature/UploadLimitsTest.php',
    'tests/Unit/MetadataServiceTest.php',
    'tests/Unit/DoiTest.php'
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
# four modules' web routes in one shared routes/web.php, so there is no
# "module 1 routes" file to copy. It is written into code\routes\ after the
# rebuild, because the wipe above would otherwise remove it.
$routesSrc = Join-Path $PSScriptRoot 'routes-excerpt.php'
if (Test-Path $routesSrc) {
    $routesDir = Join-Path $dest 'routes'
    New-Item -ItemType Directory -Path $routesDir -Force | Out-Null
    Copy-Item $routesSrc (Join-Path $routesDir 'module-1-routes.php')
    $copied++
} else {
    $missing += 'routes-excerpt.php'
}

Write-Host "Copied $copied file(s) into module 1\code" -ForegroundColor Green

if ($missing.Count) {
    Write-Host "MISSING (renamed or deleted in the app?):" -ForegroundColor Yellow
    $missing | ForEach-Object { Write-Host "    $_" -ForegroundColor Yellow }
    exit 1
}

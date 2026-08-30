# Module 4 - Collaboration, Analytics & System Administration
#
# Copies the live source files that implement requirements 16-22 into
# .\code\, keeping their real project-relative paths.
#
#   Usage:  .\sync.ps1        (run from inside the "module 4" folder)
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
    # -- Req 16: export a collection as one archive -----------------------
    'app/Services/ExportService.php',
    'app/Services/CitationService.php',
    'app/Http/Controllers/CollectionController.php',

    # -- Req 17: share a collection, Editor / Viewer ----------------------
    'app/Http/Controllers/CollectionMemberController.php',
    'app/Services/CollectionService.php',
    'app/Models/CollectionMember.php',
    'app/Enums/MemberRole.php',
    'app/Policies/CollectionPolicy.php',

    # -- Req 18: threaded comments, collections and papers ----------------
    'app/Http/Controllers/CommentController.php',
    'app/Models/Comment.php',
    'app/Policies/CommentPolicy.php',
    'app/Policies/PaperPolicy.php',
    'resources/views/components/comment.blade.php',
    'database/migrations/2026_08_28_100000_allow_paper_only_comments.php',

    # -- Req 19: per-collection activity feed -----------------------------
    'app/Services/ActivityService.php',
    'app/Models/Activity.php',
    'app/Enums/ActivityType.php',

    # -- Req 20: in-app notifications and email ---------------------------
    'app/Services/NotificationService.php',
    'app/Http/Controllers/NotificationController.php',
    'app/Models/InAppNotification.php',
    'app/Enums/NotificationType.php',
    'resources/views/notifications/index.blade.php',

    # -- Req 21: analytics dashboard ---------------------------------------
    'app/Http/Controllers/AnalyticsController.php',
    'app/Services/AnalyticsService.php',
    'resources/views/analytics/index.blade.php',
    'resources/views/components/stat-card.blade.php',

    # -- Req 22: administration, reporting, bilingual UI ------------------
    'app/Http/Controllers/AdminController.php',
    'app/Http/Controllers/ReportController.php',
    'app/Http/Middleware/EnsureUserIsNotSuspended.php',
    'app/Http/Requests/Auth/LoginRequest.php',
    'app/Models/Report.php',
    'app/Models/User.php',
    'app/Jobs/IndexPaper.php',
    'app/Enums/ReportStatus.php',
    'app/Enums/UserRole.php',
    'app/Http/Middleware/EnsureUserIsAdmin.php',
    'app/Http/Middleware/SetLocale.php',
    'app/Http/Controllers/SettingsController.php',
    'app/Enums/Locale.php',
    'resources/views/admin/index.blade.php',
    'resources/views/admin/users.blade.php',
    'resources/views/admin/comments.blade.php',
    'resources/views/admin/reports.blade.php',
    'resources/views/components/report-button.blade.php',
    'resources/views/settings/edit.blade.php',
    'lang/en/app.php',
    'lang/bn/app.php',
    'lang/en/auth.php',
    'lang/bn/auth.php',
    'database/migrations/2026_08_28_110000_create_reports_table.php',
    'database/migrations/2026_08_31_120000_add_suspension_to_users.php',

    # -- Shared schema for collaboration ----------------------------------
    'database/migrations/2026_08_21_140000_create_collaboration_tables.php',
    'database/migrations/2026_08_21_160000_add_locale_to_users.php',
    'database/migrations/2026_08_21_170000_backfill_collection_owner_members.php',

    # -- Tests --------------------------------------------------------------
    'tests/Feature/CollectionArchiveTest.php',
    'tests/Feature/CollaborationTest.php',
    'tests/Feature/PaperCommentTest.php',
    'tests/Feature/NotificationTest.php',
    'tests/Feature/AnalyticsTest.php',
    'tests/Feature/AdminPortalTest.php',
    'tests/Feature/ReportingTest.php',
    'tests/Feature/ExportAndLocaleTest.php',
    'tests/Feature/RequirementCoverageTest.php',
    'tests/Feature/AiTaskNotificationTest.php',
    'tests/Feature/AccountSuspensionTest.php'
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
# four modules' routes in one shared routes/web.php, so there is no "module 4
# routes" file to copy. It is written into code\routes\ after the rebuild,
# because the wipe above would otherwise remove it.
$routesSrc = Join-Path $PSScriptRoot 'routes-excerpt.php'
if (Test-Path $routesSrc) {
    $routesDir = Join-Path $dest 'routes'
    New-Item -ItemType Directory -Path $routesDir -Force | Out-Null
    Copy-Item $routesSrc (Join-Path $routesDir 'module-4-routes.php')
    $copied++
} else {
    $missing += 'routes-excerpt.php'
}

Write-Host "Copied $copied file(s) into module 4\code" -ForegroundColor Green

if ($missing.Count) {
    Write-Host "MISSING (renamed or deleted in the app?):" -ForegroundColor Yellow
    $missing | ForEach-Object { Write-Host "    $_" -ForegroundColor Yellow }
    exit 1
}

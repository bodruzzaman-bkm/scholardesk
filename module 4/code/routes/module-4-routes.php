<?php

/*
|--------------------------------------------------------------------------
| Module 4 routes - Collaboration, Analytics & System Administration
|--------------------------------------------------------------------------
|
| These are the Module 4 routes as they appear in the app's routes/web.php.
| They are reproduced here as an excerpt because routes/web.php is one shared
| file covering all four modules; this is not a second copy that the app
| loads.
|
| Every route below sits inside the app's `Route::middleware('auth')` group.
|
| Requirement numbers refer to the ScholarDesk proposal.
*/

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\CollectionController;
use App\Http\Controllers\CollectionMemberController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

/*
| Req 16 - the whole collection as one downloadable file.
|
| `bundle` returns a zip carrying the markdown document, a BibTeX
| bibliography and every paper's PDF. `export` is the citations-only download
| from Module 3, listed here because the two sit side by side in the UI.
*/
Route::get('/collections/{collection}/bundle', [CollectionController::class, 'bundle'])->name('collections.bundle');
Route::get('/collections/{collection}/export', [CollectionController::class, 'export'])->name('collections.export');

/*
| Req 17 - share a collection as Editor or Viewer, and manage members.
| Access is enforced by CollectionPolicy, not by these routes.
*/
Route::post('/collections/{collection}/members', [CollectionMemberController::class, 'store'])->name('collections.members.add');
Route::patch('/collections/{collection}/members/{member}', [CollectionMemberController::class, 'update'])->name('collections.members.update');
Route::delete('/collections/{collection}/members/{member}', [CollectionMemberController::class, 'destroy'])->name('collections.members.remove');

/*
| Req 18 - threaded comments on shared collections AND individual papers.
|
| Two entry points because the anchor differs: a collection thread validates
| a reply's parent against collection membership, a paper thread against the
| paper. Editing and deleting are shared.
*/
Route::post('/collections/{collection}/comments', [CommentController::class, 'store'])->name('comments.store');
Route::post('/papers/{paper}/comments', [CommentController::class, 'storePaper'])->name('papers.comments.store');
Route::put('/comments/{comment}', [CommentController::class, 'update'])->name('comments.update');
Route::delete('/comments/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');

/*
| Req 19 - the per-collection activity feed has no route of its own: it is
| rendered on the collection page by CollectionController::show(), because a
| feed detached from the collection it describes has nowhere useful to live.
*/

/*
| Req 20 - in-app notifications. The unread count is polled as JSON so the
| badge updates without a page reload.
*/
Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.count');
Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.readAll');

/*
| Req 21 - the analytics dashboard: papers over time, and breakdowns by year,
| venue, tag and reading status.
*/
Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics');

/*
| Req 22 - reporting is open to any signed-in user; moderating it is not.
| Without this route an administrator had a hide button but no queue.
*/
Route::post('/reports', [ReportController::class, 'store'])->name('reports.store');

/*
| Req 22 - the bilingual UI. The locale is stored per user and applied by
| SetLocale middleware.
*/
Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
Route::patch('/settings/locale', [SettingsController::class, 'updateLocale'])->name('settings.locale');

/*
| Req 22 - the administrator portal. Gated by the `admin` middleware alias,
| which resolves to EnsureUserIsAdmin.
*/
Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminController::class, 'index'])->name('index');

    // User accounts and roles.
    Route::get('/users', [AdminController::class, 'users'])->name('users');
    Route::patch('/users/{user}/role', [AdminController::class, 'updateRole'])->name('users.role');

    // Comment moderation: hiding is reversible and keeps the thread shape.
    Route::get('/comments', [AdminController::class, 'comments'])->name('comments');
    Route::patch('/comments/{comment}/visibility', [AdminController::class, 'toggleCommentVisibility'])->name('comments.visibility');

    // The report queue.
    Route::get('/reports', [AdminController::class, 'reports'])->name('reports');
    Route::patch('/reports/{report}', [AdminController::class, 'resolveReport'])->name('reports.resolve');
});

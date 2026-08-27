<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AiController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\CollectionController;
use App\Http\Controllers\CollectionMemberController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HighlightController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PaperController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TagController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Settings (UI language)
    Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::patch('/settings/locale', [SettingsController::class, 'updateLocale'])->name('settings.locale');

    // Search (keyword + semantic)
    Route::get('/search', [SearchController::class, 'index'])->name('search');

    // Analytics dashboard (requirement 21)
    Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics');

    /*
     | Papers / library
     |
     | Static segments are declared before the {paper} wildcard so that
     | /papers/create and /papers/batch are not swallowed by /papers/{paper}.
     */
    Route::get('/papers', [PaperController::class, 'index'])->name('papers.index');
    Route::get('/papers/create', [PaperController::class, 'create'])->name('papers.create');
    Route::post('/papers', [PaperController::class, 'store'])->name('papers.store');
    Route::post('/papers/batch', [PaperController::class, 'storeBatch'])->name('papers.storeBatch');
    Route::get('/papers/{paper}', [PaperController::class, 'show'])->name('papers.show');
    Route::get('/papers/{paper}/edit', [PaperController::class, 'edit'])->name('papers.edit');
    Route::put('/papers/{paper}', [PaperController::class, 'update'])->name('papers.update');
    Route::delete('/papers/{paper}', [PaperController::class, 'destroy'])->name('papers.destroy');
    Route::get('/papers/{paper}/read', [PaperController::class, 'read'])->name('papers.read');
    Route::patch('/papers/{paper}/status', [PaperController::class, 'updateStatus'])->name('papers.status');
    Route::get('/papers/{paper}/export', [PaperController::class, 'export'])->name('papers.export');
    Route::post('/papers/{paper}/reindex', [PaperController::class, 'reindex'])->name('papers.reindex');

    // Collections
    Route::get('/collections', [CollectionController::class, 'index'])->name('collections.index');
    Route::post('/collections', [CollectionController::class, 'store'])->name('collections.store');
    Route::get('/collections/{collection}', [CollectionController::class, 'show'])->name('collections.show');
    Route::put('/collections/{collection}', [CollectionController::class, 'update'])->name('collections.update');
    Route::delete('/collections/{collection}', [CollectionController::class, 'destroy'])->name('collections.destroy');
    Route::get('/collections/{collection}/export', [CollectionController::class, 'export'])->name('collections.export');
    Route::get('/collections/{collection}/bundle', [CollectionController::class, 'bundle'])->name('collections.bundle');
    Route::post('/collections/{collection}/papers', [CollectionController::class, 'addPaper'])->name('collections.papers.add');
    Route::post('/collections/{collection}/papers/upload', [CollectionController::class, 'uploadPapers'])->name('collections.papers.upload');
    Route::delete('/collections/{collection}/papers/{paper}', [CollectionController::class, 'removePaper'])->name('collections.papers.remove');

    // Collaborators
    Route::post('/collections/{collection}/members', [CollectionMemberController::class, 'store'])->name('collections.members.add');
    Route::patch('/collections/{collection}/members/{member}', [CollectionMemberController::class, 'update'])->name('collections.members.update');
    Route::delete('/collections/{collection}/members/{member}', [CollectionMemberController::class, 'destroy'])->name('collections.members.remove');

    // Comments — on a collection, or on a single paper (requirement 18)
    Route::post('/collections/{collection}/comments', [CommentController::class, 'store'])->name('comments.store');
    Route::post('/papers/{paper}/comments', [CommentController::class, 'storePaper'])->name('papers.comments.store');
    Route::put('/comments/{comment}', [CommentController::class, 'update'])->name('comments.update');
    Route::delete('/comments/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');

    // Content reports (requirement 22)
    Route::post('/reports', [ReportController::class, 'store'])->name('reports.store');

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.count');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.readAll');

    // Tags
    Route::get('/tags', [TagController::class, 'index'])->name('tags.index');
    Route::post('/tags', [TagController::class, 'store'])->name('tags.store');
    Route::put('/tags/{tag}', [TagController::class, 'update'])->name('tags.update');
    Route::delete('/tags/{tag}', [TagController::class, 'destroy'])->name('tags.destroy');

    // PDF highlights (JSON, consumed by the reader)
    Route::get('/papers/{paper}/highlights', [HighlightController::class, 'index'])->name('highlights.index');
    Route::post('/papers/{paper}/highlights', [HighlightController::class, 'store'])->name('highlights.store');
    Route::patch('/highlights/{highlight}', [HighlightController::class, 'update'])->name('highlights.update');
    Route::delete('/highlights/{highlight}', [HighlightController::class, 'destroy'])->name('highlights.destroy');

    // Markdown notes
    Route::post('/papers/{paper}/notes', [NoteController::class, 'store'])->name('notes.store');
    Route::get('/notes/{note}/edit', [NoteController::class, 'edit'])->name('notes.edit');
    Route::put('/notes/{note}', [NoteController::class, 'update'])->name('notes.update');
    Route::delete('/notes/{note}', [NoteController::class, 'destroy'])->name('notes.destroy');

    /*
     | AI (JSON). Rate-limited because each call hits a paid/quota'd provider.
     | Related-papers is pure local vector maths, so it is not limited.
     */
    Route::middleware('throttle:20,1')->group(function () {
        // Library-wide Q&A: the flagship feature without needing a collection.
        Route::post('/ai/ask', [AiController::class, 'askLibrary'])->name('ai.ask');
        Route::post('/ai/papers/{paper}/summary', [AiController::class, 'summarize'])->name('ai.paper.summary');
        Route::post('/ai/papers/{paper}/ask', [AiController::class, 'askPaper'])->name('ai.paper.ask');
        Route::post('/ai/collections/{collection}/ask', [AiController::class, 'askCollection'])->name('ai.collection.ask');
        Route::post('/ai/collections/{collection}/review', [AiController::class, 'review'])->name('ai.collection.review');
    });
    Route::get('/ai/papers/{paper}/related', [AiController::class, 'related'])->name('ai.paper.related');

    // Admin portal
    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', [AdminController::class, 'index'])->name('index');
        Route::get('/users', [AdminController::class, 'users'])->name('users');
        Route::patch('/users/{user}/role', [AdminController::class, 'updateRole'])->name('users.role');
        Route::get('/comments', [AdminController::class, 'comments'])->name('comments');
        Route::patch('/comments/{comment}/visibility', [AdminController::class, 'toggleCommentVisibility'])->name('comments.visibility');
        Route::get('/reports', [AdminController::class, 'reports'])->name('reports');
        Route::patch('/reports/{report}', [AdminController::class, 'resolveReport'])->name('reports.resolve');
    });
});

require __DIR__.'/auth.php';

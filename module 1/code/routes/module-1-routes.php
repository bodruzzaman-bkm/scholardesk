<?php

/*
|--------------------------------------------------------------------------
| Module 1 routes - User Accounts & Core Library Management
|--------------------------------------------------------------------------
|
| These are the Module 1 routes as they appear in the app's routes/web.php
| and routes/auth.php. They are reproduced here as an excerpt because those
| files cover all four modules; this is not a second copy that the app loads.
|
| Requirement numbers refer to the ScholarDesk proposal.
*/

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\CollectionController;
use App\Http\Controllers\PaperController;
use App\Http\Controllers\TagController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Authentication features (routes/auth.php)
|--------------------------------------------------------------------------
|
| Register and sign in; log out; password recovery by email. Sessions are
| Laravel's own, so authentication is session-based rather than token-based.
*/
Route::middleware('guest')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('register', [RegisteredUserController::class, 'store']);

    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    // Password recovery by email.
    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');
    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('reset-password', [NewPasswordController::class, 'store'])->name('password.store');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});

/*
|--------------------------------------------------------------------------
| Library routes (routes/web.php), all inside the 'auth' middleware group
|--------------------------------------------------------------------------
*/

/*
| Reqs 2, 3 and 4 - add, view, edit, delete papers.
|
| Static segments are declared BEFORE the {paper} wildcard, so /papers/create
| and /papers/batch are not swallowed by /papers/{paper}.
*/
Route::get('/papers', [PaperController::class, 'index'])->name('papers.index');
Route::get('/papers/create', [PaperController::class, 'create'])->name('papers.create');
Route::post('/papers', [PaperController::class, 'store'])->name('papers.store');
Route::post('/papers/batch', [PaperController::class, 'storeBatch'])->name('papers.storeBatch');
Route::get('/papers/{paper}', [PaperController::class, 'show'])->name('papers.show');
Route::get('/papers/{paper}/edit', [PaperController::class, 'edit'])->name('papers.edit');
Route::put('/papers/{paper}', [PaperController::class, 'update'])->name('papers.update');
Route::delete('/papers/{paper}', [PaperController::class, 'destroy'])->name('papers.destroy');

// Req 4 - mark a paper as "to read", "reading" or "read".
Route::patch('/papers/{paper}/status', [PaperController::class, 'updateStatus'])->name('papers.status');

/*
| Req 5 - collections. A paper can sit in several collections, and removing
| it from one detaches the pivot row without deleting the paper.
*/
Route::get('/collections', [CollectionController::class, 'index'])->name('collections.index');
Route::post('/collections', [CollectionController::class, 'store'])->name('collections.store');
Route::get('/collections/{collection}', [CollectionController::class, 'show'])->name('collections.show');
Route::put('/collections/{collection}', [CollectionController::class, 'update'])->name('collections.update');
Route::delete('/collections/{collection}', [CollectionController::class, 'destroy'])->name('collections.destroy');
Route::post('/collections/{collection}/papers', [CollectionController::class, 'addPaper'])->name('collections.papers.add');
Route::post('/collections/{collection}/papers/upload', [CollectionController::class, 'uploadPapers'])->name('collections.papers.upload');
Route::delete('/collections/{collection}/papers/{paper}', [CollectionController::class, 'removePaper'])->name('collections.papers.remove');

/*
| Req 6 - coloured tags. Filtering by tag is handled by the ?tag= parameter
| on /papers and /search, not by a separate route.
*/
Route::get('/tags', [TagController::class, 'index'])->name('tags.index');
Route::post('/tags', [TagController::class, 'store'])->name('tags.store');
Route::put('/tags/{tag}', [TagController::class, 'update'])->name('tags.update');
Route::delete('/tags/{tag}', [TagController::class, 'destroy'])->name('tags.destroy');

/*
| Req 1 - the administrator half of "two account types". Gated by the `admin`
| middleware alias, which resolves to EnsureUserIsAdmin.
*/
Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
    // Full portal listed in Module 4; the role gate itself belongs to Req 1.
});

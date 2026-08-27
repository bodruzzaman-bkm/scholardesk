<?php

/*
|--------------------------------------------------------------------------
| Module 2 routes - Reading, Annotation & Single-Paper AI
|--------------------------------------------------------------------------
|
| These are the Module 2 routes as they appear in the app's routes/web.php.
| They are reproduced here as an excerpt because routes/web.php is one shared
| file covering all four modules; this is not a second copy that the app
| loads.
|
| Every route below sits inside the app's `Route::middleware('auth')` group.
|
| Requirement numbers refer to the ScholarDesk proposal.
*/

use App\Http\Controllers\AiController;
use App\Http\Controllers\HighlightController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\PaperController;
use Illuminate\Support\Facades\Route;

/*
| Req 7 - read a PDF in the browser.
|
| Redirects to the paper page when there is no file attached, rather than
| rendering an empty viewer.
*/
Route::get('/papers/{paper}/read', [PaperController::class, 'read'])->name('papers.read');

/*
| Req 7 - coloured highlights with margin notes.
|
| JSON rather than redirects: these are called with fetch() from the reader,
| which stays on the page. Highlights persist across sessions because they
| are rows in the database, not browser state.
*/
Route::get('/papers/{paper}/highlights', [HighlightController::class, 'index'])->name('highlights.index');
Route::post('/papers/{paper}/highlights', [HighlightController::class, 'store'])->name('highlights.store');
Route::patch('/highlights/{highlight}', [HighlightController::class, 'update'])->name('highlights.update');
Route::delete('/highlights/{highlight}', [HighlightController::class, 'destroy'])->name('highlights.destroy');

/*
| Req 8 - markdown notes attached to a paper. Ordinary form posts, because
| these are full page interactions rather than in-reader ones.
*/
Route::post('/papers/{paper}/notes', [NoteController::class, 'store'])->name('notes.store');
Route::get('/notes/{note}/edit', [NoteController::class, 'edit'])->name('notes.edit');
Route::put('/notes/{note}', [NoteController::class, 'update'])->name('notes.update');
Route::delete('/notes/{note}', [NoteController::class, 'destroy'])->name('notes.destroy');

/*
| Reqs 9 and 10 - single-paper summary and Q&A.
|
| Throttled because each call spends a paid/quota'd provider's tokens. Groq's
| free tier allows 8,000 tokens per minute.
*/
Route::middleware('throttle:20,1')->group(function () {
    // Req 9 - TL;DR / contributions / method / limitations.
    Route::post('/ai/papers/{paper}/summary', [AiController::class, 'summarize'])->name('ai.paper.summary');

    // Req 10 - answers drawn from this paper's own content.
    Route::post('/ai/papers/{paper}/ask', [AiController::class, 'askPaper'])->name('ai.paper.ask');
});

/*
| Supporting: re-run text extraction and embedding for one paper. Without an
| indexed PDF, req 10 has nothing to retrieve from.
*/
Route::post('/papers/{paper}/reindex', [PaperController::class, 'reindex'])->name('papers.reindex');

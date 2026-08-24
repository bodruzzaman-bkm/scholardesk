<?php

/*
|--------------------------------------------------------------------------
| Module 3 routes - Advanced AI Research & Citation Export
|--------------------------------------------------------------------------
|
| These are the Module 3 routes as they appear in the app's routes/web.php.
| They are reproduced here as an excerpt because routes/web.php is one shared
| file covering all four modules; this is not a second copy that the app
| loads.
|
| Every route below sits inside the app's `Route::middleware('auth')` group.
|
| Requirement numbers refer to the ScholarDesk proposal.
*/

use App\Http\Controllers\AiController;
use App\Http\Controllers\CollectionController;
use App\Http\Controllers\PaperController;
use App\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

/*
| Req 12 - Semantic and keyword search.
|
| One route serves both; ?mode=semantic switches the ranking strategy from
| SQL LIKE matching to cosine similarity over chunk embeddings.
*/
Route::get('/search', [SearchController::class, 'index'])->name('search');

/*
| Reqs 11 and 14 - AI generation.
|
| Throttled because each call spends a paid/quota'd provider's tokens. Groq's
| free tier allows 8,000 tokens per minute, so an unthrottled page could
| exhaust the quota with a handful of clicks.
*/
Route::middleware('throttle:20,1')->group(function () {
    // Req 11, library scope: cross-paper Q&A without needing a collection.
    Route::post('/ai/ask', [AiController::class, 'askLibrary'])->name('ai.ask');

    // Single-paper summary and Q&A (Module 2, listed for context).
    Route::post('/ai/papers/{paper}/summary', [AiController::class, 'summarize'])->name('ai.paper.summary');
    Route::post('/ai/papers/{paper}/ask', [AiController::class, 'askPaper'])->name('ai.paper.ask');

    // Req 11 - synthesized answer across a collection, with citations.
    Route::post('/ai/collections/{collection}/ask', [AiController::class, 'askCollection'])->name('ai.collection.ask');

    // Req 14 - literature-review draft, from selected papers or all of them.
    Route::post('/ai/collections/{collection}/review', [AiController::class, 'review'])->name('ai.collection.review');
});

/*
| Req 13 - Related papers.
|
| Deliberately OUTSIDE the throttle group: this is pure local vector
| arithmetic over stored embeddings and never calls an external provider,
| so there is no quota to protect.
*/
Route::get('/ai/papers/{paper}/related', [AiController::class, 'related'])->name('ai.paper.related');

/*
| Req 15 - Citation export, individually or for a whole collection.
|
| Both accept ?format=bibtex|apa|text and fall back to BibTeX on anything
| unrecognised.
*/
Route::get('/papers/{paper}/export', [PaperController::class, 'export'])->name('papers.export');
Route::get('/collections/{collection}/export', [CollectionController::class, 'export'])->name('collections.export');

/*
| Supporting: re-run text extraction and embedding for one paper. Without an
| indexed PDF a paper is invisible to reqs 11, 12 and 13.
*/
Route::post('/papers/{paper}/reindex', [PaperController::class, 'reindex'])->name('papers.reindex');

<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PaperController;
use App\Http\Controllers\CollectionController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\HighlightController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NoteController;


Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    // Routes for Paper Management
    Route::get('/papers/create', [PaperController::class, 'create'])->name('papers.create');
    Route::post('/papers', [PaperController::class, 'store'])->name('papers.store');
    Route::get('/papers', [PaperController::class, 'index'])->name('papers.index'); 
    Route::get('/papers/{paper}', [PaperController::class, 'show'])->name('papers.show');
    Route::get('/papers/{paper}/read', [PaperController::class, 'read'])->name('papers.read');
    Route::get('/papers/create', [PaperController::class, 'create'])->name('papers.create');
    Route::post('/papers', [PaperController::class, 'store'])->name('papers.store');
    Route::post('/papers', [PaperController::class, 'store'])->name('papers.store');
    Route::delete('/papers/{paper}', [PaperController::class, 'destroy'])->name('papers.destroy');
    Route::get('/papers/{paper}/edit', [PaperController::class, 'edit'])->name('papers.edit'); 
    Route::put('/papers/{paper}', [PaperController::class, 'update'])->name('papers.update');
    // Routes for Collection Management
    Route::get('/collections', [CollectionController::class, 'index'])->name('collections.index');
    Route::post('/collections', [CollectionController::class, 'store'])->name('collections.store');
    Route::get('/collections/{collection}', [CollectionController::class, 'show'])->name('collections.show');
    Route::delete('/collections/{collection}/papers/{paper}', [CollectionController::class, 'removePaper'])->name('collections.papers.remove');
    // Routes for Tag Management
    Route::post('/tags', [TagController::class, 'store'])->name('tags.store');
    // Routes for PDF Highlights & Notes (API format for JS)
    Route::get('/papers/{paper}/highlights', [HighlightController::class, 'index'])->name('highlights.index');
    Route::post('/papers/{paper}/highlights', [HighlightController::class, 'store'])->name('highlights.store');
    Route::delete('/highlights/{highlight}', [HighlightController::class, 'destroy'])->name('highlights.destroy');
    // Markdown Notes Routes
    Route::post('/papers/{paper}/notes', [NoteController::class, 'store'])->name('notes.store');
    Route::get('/notes/{note}/edit', [NoteController::class, 'edit'])->name('notes.edit');
    Route::put('/notes/{note}', [NoteController::class, 'update'])->name('notes.update');
    Route::delete('/notes/{note}', [NoteController::class, 'destroy'])->name('notes.destroy');


});

require __DIR__.'/auth.php';

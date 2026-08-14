<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PaperController;
use App\Http\Controllers\CollectionController;


Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    // Routes for Paper Management
    Route::get('/papers/create', [PaperController::class, 'create'])->name('papers.create');
    Route::post('/papers', [PaperController::class, 'store'])->name('papers.store');
    Route::get('/papers', [PaperController::class, 'index'])->name('papers.index'); 
    Route::get('/papers/{paper}', [PaperController::class, 'show'])->name('papers.show');
    Route::get('/papers/create', [PaperController::class, 'create'])->name('papers.create');
    Route::post('/papers', [PaperController::class, 'store'])->name('papers.store');
    Route::post('/papers', [PaperController::class, 'store'])->name('papers.store');
    Route::delete('/papers/{paper}', [PaperController::class, 'destroy'])->name('papers.destroy');
    Route::get('/papers/{paper}/edit', [PaperController::class, 'edit'])->name('papers.edit'); 
    Route::put('/papers/{paper}', [PaperController::class, 'update'])->name('papers.update'); 
    Route::get('/collections', [CollectionController::class, 'index'])->name('collections.index');
    Route::post('/collections', [CollectionController::class, 'store'])->name('collections.store');
    Route::get('/collections/{collection}', [CollectionController::class, 'show'])->name('collections.show');
    Route::delete('/collections/{collection}/papers/{paper}', [CollectionController::class, 'removePaper'])->name('collections.papers.remove');
});

require __DIR__.'/auth.php';

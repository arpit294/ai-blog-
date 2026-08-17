<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ArticleController;
use App\Http\Controllers\AutomationProfileController;
use App\Http\Controllers\Admin\AutomationRunController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('dashboard');
    
    Route::resource('categories', CategoryController::class);
    Route::resource('articles', ArticleController::class);
    
    // Article Review Workflow
    Route::post('/articles/create-with-ai', [ArticleController::class, 'createWithAi'])->name('articles.createWithAi');
    Route::get('/articles/active-run-status', [ArticleController::class, 'activeRunStatus'])->name('articles.activeRunStatus');
    Route::patch('/articles/{article}/approve', [ArticleController::class, 'approve'])->name('articles.approve');
    Route::patch('/articles/{article}/reject', [ArticleController::class, 'reject'])->name('articles.reject');
    Route::patch('/articles/{article}/request-changes', [ArticleController::class, 'requestChanges'])->name('articles.requestChanges');
    Route::patch('/articles/{article}/regenerate-title', [ArticleController::class, 'regenerateTitle'])->name('articles.regenerateTitle');
    Route::patch('/articles/{article}/regenerate-image', [ArticleController::class, 'regenerateImage'])->name('articles.regenerateImage');
    Route::patch('/articles/{article}/regenerate-section', [ArticleController::class, 'regenerateSection'])->name('articles.regenerateSection');
    Route::patch('/articles/{article}/rerun-quality-checks', [ArticleController::class, 'rerunQualityChecks'])->name('articles.rerunQualityChecks');

    Route::resource('automation-profiles', AutomationProfileController::class);
    Route::resource('automation-runs', AutomationRunController::class)->only(['index', 'show']);
    
    // Topic Memory
    Route::get('/topic-memory', [\App\Http\Controllers\Admin\TopicController::class, 'index'])->name('topic-memory.index');
    Route::patch('/topic-memory/{topic}/block', [\App\Http\Controllers\Admin\TopicController::class, 'block'])->name('topic-memory.block');
    Route::patch('/topic-memory/{topic}/approve', [\App\Http\Controllers\Admin\TopicController::class, 'approve'])->name('topic-memory.approve');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

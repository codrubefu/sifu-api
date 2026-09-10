<?php

use App\Articles\Http\Controllers\Api\ArticleController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.bearer')->group(function (): void {
    Route::get('/articles', [ArticleController::class, 'index'])
        ->middleware('right:articles.view,articles.manage');
    Route::post('/articles', [ArticleController::class, 'store'])
        ->middleware('right:articles.create,articles.manage');
    Route::get('/articles/{article}', [ArticleController::class, 'show'])
        ->middleware('right:articles.view,articles.manage');
    Route::put('/articles/{article}', [ArticleController::class, 'update'])
        ->middleware('right:articles.update,articles.manage');
    Route::patch('/articles/{article}', [ArticleController::class, 'update'])
        ->middleware('right:articles.update,articles.manage');
    Route::delete('/articles/{article}', [ArticleController::class, 'destroy'])
        ->middleware('right:articles.delete,articles.manage');
    Route::get('/articles-feed', [ArticleController::class, 'feed']);
    Route::post('/articles/{article}/view', [ArticleController::class, 'markViewed']);
});

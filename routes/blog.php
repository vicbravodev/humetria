<?php

declare(strict_types=1);

use App\Modules\Blog\Http\Controllers\ArticleIndexController;
use App\Modules\Blog\Http\Controllers\ArticleShowController;
use App\Modules\Blog\Http\Controllers\BlogSitemapController;
use Illuminate\Support\Facades\Route;

Route::prefix('blog')->name('blog.')->group(function (): void {
    Route::get('/', ArticleIndexController::class)->name('index');
    Route::get('sitemap.xml', BlogSitemapController::class)->name('sitemap');
    Route::get('{slug}', ArticleShowController::class)->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')->name('show');
});

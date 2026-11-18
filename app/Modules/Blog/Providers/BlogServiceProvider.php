<?php

declare(strict_types=1);

namespace App\Modules\Blog\Providers;

use App\Modules\Blog\Contracts\ArticleLibrary;
use App\Modules\Blog\Support\FileArticleLibrary;
use Illuminate\Support\ServiceProvider;

final class BlogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ArticleLibrary::class, fn (): FileArticleLibrary => new FileArticleLibrary(config()->string('blog.path')));
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Blog\Http\Controllers;

use App\Modules\Blog\Contracts\ArticleLibrary;
use App\Modules\Blog\Support\BlogSeo;
use Illuminate\Contracts\View\View;

final class ArticleShowController
{
    public function __invoke(string $slug, ArticleLibrary $library): View
    {
        $article = $library->find($slug) ?? abort(404);

        return view('blog.show', [
            'article' => $article,
            'canonical' => BlogSeo::articleUrl($article->slug),
            'jsonLd' => BlogSeo::articleJsonLd($article),
        ]);
    }
}

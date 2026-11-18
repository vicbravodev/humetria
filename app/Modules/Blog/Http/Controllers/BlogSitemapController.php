<?php

declare(strict_types=1);

namespace App\Modules\Blog\Http\Controllers;

use App\Modules\Blog\Contracts\ArticleLibrary;
use App\Modules\Blog\Support\BlogSeo;
use Illuminate\Http\Response;

/** The articles for search engines; the fixed pages (the index included) are in public/sitemap.xml. */
final class BlogSitemapController
{
    public function __invoke(ArticleLibrary $library): Response
    {
        $entries = array_map(
            fn ($article): array => ['loc' => BlogSeo::articleUrl($article->slug), 'lastmod' => $article->updatedAt->toDateString()],
            $library->published(),
        );

        return response()->view('blog.sitemap', ['entries' => $entries], 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}

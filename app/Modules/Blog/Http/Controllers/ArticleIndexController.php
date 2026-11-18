<?php

declare(strict_types=1);

namespace App\Modules\Blog\Http\Controllers;

use App\Modules\Blog\Contracts\ArticleLibrary;
use App\Modules\Blog\Support\BlogSeo;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class ArticleIndexController
{
    public function __invoke(Request $request, ArticleLibrary $library): View
    {
        $articles = $library->published();
        $perPage = config()->integer('blog.per_page');
        $lastPage = max(1, (int) ceil(count($articles) / $perPage));
        $page = max(1, $request->integer('page', 1));

        abort_if($page > $lastPage, 404);

        return view('blog.index', [
            'articles' => array_slice($articles, ($page - 1) * $perPage, $perPage),
            'canonical' => BlogSeo::indexUrl($page),
            'previous' => $page > 1 ? BlogSeo::indexUrl($page - 1) : null,
            'next' => $page < $lastPage ? BlogSeo::indexUrl($page + 1) : null,
        ]);
    }
}

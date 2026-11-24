<?php

declare(strict_types=1);

namespace App\Modules\Blog\Http\Controllers;

use App\Modules\Blog\Contracts\ArticleLibrary;
use App\Modules\Blog\Support\BlogPresenter;
use App\Modules\Blog\Support\BlogSeo;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ArticleIndexController
{
    public function __invoke(Request $request, ArticleLibrary $library): Response
    {
        $articles = $library->published();
        $perPage = config()->integer('blog.per_page');
        $lastPage = max(1, (int) ceil(count($articles) / $perPage));
        $page = max(1, $request->integer('page', 1));

        abort_if($page > $lastPage, 404);

        return Inertia::render('blog/index', [
            'seo' => BlogSeo::indexSeo($page),
            'articles' => array_map(BlogPresenter::card(...), array_slice($articles, ($page - 1) * $perPage, $perPage)),
            'pagination' => [
                'previous' => $page > 1 ? route('blog.index', $page > 2 ? ['page' => $page - 1] : [], false) : null,
                'next' => $page < $lastPage ? route('blog.index', ['page' => $page + 1], false) : null,
            ],
        ]);
    }
}

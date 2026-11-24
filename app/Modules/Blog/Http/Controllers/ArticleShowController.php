<?php

declare(strict_types=1);

namespace App\Modules\Blog\Http\Controllers;

use App\Modules\Blog\Contracts\ArticleLibrary;
use App\Modules\Blog\Data\Article;
use App\Modules\Blog\Support\BlogPresenter;
use App\Modules\Blog\Support\BlogSeo;
use Inertia\Inertia;
use Inertia\Response;

final class ArticleShowController
{
    /**
     * Up to three other articles: those of the same tool first, then the newest.
     *
     * @param  list<Article>  $published  newest first
     * @return list<Article>
     */
    private function related(Article $article, array $published): array
    {
        $others = array_values(array_filter($published, fn (Article $other): bool => $other->slug !== $article->slug));
        usort($others, fn (Article $a, Article $b): int => ($b->tool === $article->tool) <=> ($a->tool === $article->tool));

        return array_slice($others, 0, 3);
    }

    public function __invoke(string $slug, ArticleLibrary $library): Response
    {
        $article = $library->find($slug) ?? abort(404);

        return Inertia::render('blog/show', [
            'seo' => BlogSeo::articleSeo($article, BlogPresenter::coverAlt($article)),
            'article' => BlogPresenter::article($article),
            'related' => array_map(BlogPresenter::card(...), $this->related($article, $library->published())),
        ]);
    }
}

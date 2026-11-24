<?php

declare(strict_types=1);

namespace App\Modules\Blog\Http\Controllers;

use App\Modules\Blog\Contracts\ArticleLibrary;
use App\Modules\Blog\Data\Article;
use App\Modules\Blog\Support\BlogSeo;
use Illuminate\Contracts\View\View;

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

    public function __invoke(string $slug, ArticleLibrary $library): View
    {
        $article = $library->find($slug) ?? abort(404);

        return view('blog.show', [
            'article' => $article,
            'coverAlt' => __('blog.cover_alt', ['tool' => $article->toolName, 'title' => $article->title]),
            'related' => $this->related($article, $library->published()),
            'canonical' => BlogSeo::articleUrl($article->slug),
            'jsonLd' => BlogSeo::articleJsonLd($article),
        ]);
    }
}

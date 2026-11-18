<?php

declare(strict_types=1);

namespace App\Modules\Blog\Support;

use App\Modules\Blog\Contracts\ArticleLibrary;
use App\Modules\Blog\Data\Article;
use App\Modules\Blog\Exceptions\InvalidArticle;
use Illuminate\Support\Facades\Log;

/**
 * The articles are the .md files of one folder. A file that breaks the rules is left out and logged; the CI test over
 * content/blog is what keeps that from reaching production. ponytail: no cache, a scan per request; add one past ~100 articles.
 */
final readonly class FileArticleLibrary implements ArticleLibrary
{
    public function __construct(private string $path) {}

    public function published(): array
    {
        $articles = [];

        foreach (glob($this->path.'/*.md') ?: [] as $file) {
            $article = $this->read(basename($file, '.md'), $file);

            if ($article?->isPublished(now())) {
                $articles[] = $article;
            }
        }

        usort($articles, fn (Article $a, Article $b): int => $b->publishedAt <=> $a->publishedAt ?: $a->slug <=> $b->slug);

        return $articles;
    }

    public function find(string $slug): ?Article
    {
        $file = $this->path.'/'.$slug.'.md';

        if (! ArticleParser::isSlug($slug) || ! is_file($file)) {
            return null;
        }

        $article = $this->read($slug, $file);

        return $article?->isPublished(now()) ? $article : null;
    }

    private function read(string $slug, string $file): ?Article
    {
        try {
            return ArticleParser::parse($slug, (string) file_get_contents($file));
        } catch (InvalidArticle $invalid) {
            Log::warning('blog.article_invalid', ['slug' => $slug, 'problems' => $invalid->problems]);

            return null;
        }
    }
}

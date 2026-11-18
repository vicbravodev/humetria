<?php

declare(strict_types=1);

namespace App\Modules\Blog\Contracts;

use App\Modules\Blog\Data\Article;

interface ArticleLibrary
{
    /**
     * The articles a visitor may read, the newest first.
     *
     * @return list<Article>
     */
    public function published(): array;

    /** A published article by its slug, or null when there is none (a draft, a future one or no such file). */
    public function find(string $slug): ?Article;
}

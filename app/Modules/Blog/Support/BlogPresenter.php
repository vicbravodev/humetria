<?php

declare(strict_types=1);

namespace App\Modules\Blog\Support;

use App\Modules\Blog\Data\Article;

/** What the React pages of the blog receive: plain arrays with the dates already written the way the page shows them. */
final class BlogPresenter
{
    private const string DATE = 'j \d\e F \d\e Y';

    /**
     * @return array{slug: string, title: string, description: string, url: string, publishedAt: string, publishedOn: string, readingMinutes: int, tool: string, toolName: string, family: string, cover: string}
     */
    public static function card(Article $article): array
    {
        return [
            'slug' => $article->slug,
            'title' => $article->title,
            'description' => $article->description,
            'url' => route('blog.show', $article->slug, false),
            'publishedAt' => $article->publishedAt->toDateString(),
            'publishedOn' => $article->publishedAt->translatedFormat(self::DATE),
            'readingMinutes' => $article->readingMinutes,
            'tool' => $article->tool,
            'toolName' => $article->toolName,
            'family' => $article->family,
            'cover' => $article->cover(),
        ];
    }

    public static function coverAlt(Article $article): string
    {
        return __('blog.cover_alt', ['tool' => $article->toolName, 'title' => $article->title]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function article(Article $article): array
    {
        return [
            ...self::card($article),
            'coverAlt' => self::coverAlt($article),
            'toolUrl' => route($article->toolRoute, absolute: false),
            'updatedOn' => $article->updatedAt->gt($article->publishedAt) ? $article->updatedAt->translatedFormat(self::DATE) : null,
            'reviewedOn' => $article->reviewedAt?->translatedFormat(self::DATE),
            'headings' => $article->headings,
            'body' => $article->body,
            'sources' => $article->sources,
        ];
    }
}

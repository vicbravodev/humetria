<?php

declare(strict_types=1);

namespace App\Modules\Blog\Support;

use App\Modules\Blog\Data\Article;

/** The tags of the head of the blog's pages: the canonical addresses and the JSON-LD that search engines read. */
final class BlogSeo
{
    private const string IMAGE = '/og-v1.png';

    public static function base(): string
    {
        return rtrim(config()->string('app.url'), '/');
    }

    public static function indexUrl(int $page = 1): string
    {
        return self::base().route('blog.index', $page > 1 ? ['page' => $page] : [], false);
    }

    public static function articleUrl(string $slug): string
    {
        return self::base().route('blog.show', $slug, false);
    }

    public static function imageUrl(): string
    {
        return self::base().self::IMAGE;
    }

    /** JSON_HEX_TAG turns `<` and `>` into escapes, so a title with `</script>` cannot close the block it is printed in. */
    public static function articleJsonLd(Article $article): string
    {
        $site = config()->string('app.name');
        $organization = ['@type' => 'Organization', 'name' => $site, 'url' => self::base().'/'];

        return json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $article->title,
            'description' => $article->description,
            'datePublished' => $article->publishedAt->toDateString(),
            'dateModified' => $article->updatedAt->toDateString(),
            'inLanguage' => 'es-MX',
            'mainEntityOfPage' => self::articleUrl($article->slug),
            'image' => [self::imageUrl()],
            'author' => $organization,
            'publisher' => [...$organization, 'logo' => ['@type' => 'ImageObject', 'url' => self::base().'/app-icon-512.png']],
        ], JSON_HEX_TAG | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}

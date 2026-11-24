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

    /**
     * The tags of the head of the index, in the shape the React `SeoHead` and the Blade fallback read (App\Http\PageSeo).
     *
     * @return array{title: string, description: string, canonical: string, image: array{url: string, width: int, height: int, alt: string}, locale: string, siteName: string, jsonLd: string}
     */
    public static function indexSeo(int $page = 1): array
    {
        $site = config()->string('app.name');
        $jsonLd = [
            '@context' => 'https://schema.org',
            '@type' => 'CollectionPage',
            'name' => __('blog.title'),
            'description' => __('blog.description'),
            'url' => self::indexUrl($page),
            'inLanguage' => 'es-MX',
            'isPartOf' => ['@type' => 'WebSite', 'name' => $site, 'url' => self::base().'/'],
        ];

        return self::seo(__('blog.title'), __('blog.description'), self::indexUrl($page), self::imageUrl(), __('blog.image_alt'), $jsonLd);
    }

    /**
     * @return array{title: string, description: string, canonical: string, image: array{url: string, width: int, height: int, alt: string}, locale: string, siteName: string, jsonLd: string}
     */
    public static function articleSeo(Article $article, string $coverAlt): array
    {
        return [
            ...self::seo($article->title, $article->description, self::articleUrl($article->slug), self::imageUrl($article), $coverAlt, []),
            'jsonLd' => self::articleJsonLd($article),
        ];
    }

    /**
     * @param  array<string, mixed>  $graph
     * @return array{title: string, description: string, canonical: string, image: array{url: string, width: int, height: int, alt: string}, locale: string, siteName: string, jsonLd: string}
     */
    private static function seo(string $title, string $description, string $canonical, string $image, string $imageAlt, array $graph): array
    {
        return [
            'title' => $title,
            'description' => $description,
            'canonical' => $canonical,
            'image' => ['url' => $image, 'width' => 1200, 'height' => 630, 'alt' => $imageAlt],
            'locale' => 'es_MX',
            'siteName' => config()->string('app.name'),
            'jsonLd' => json_encode($graph, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ];
    }

    /** The image to share: the article's own cover (with its title) or, for the index, the one of the whole site. */
    public static function imageUrl(?Article $article = null): string
    {
        return self::base().($article?->ogImage() ?? self::IMAGE);
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
            'image' => [self::imageUrl($article)],
            'author' => $organization,
            'publisher' => [...$organization, 'logo' => ['@type' => 'ImageObject', 'url' => self::base().'/app-icon-512.png']],
        ], JSON_HEX_TAG | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}

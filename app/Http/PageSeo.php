<?php

declare(strict_types=1);

namespace App\Http;

use Illuminate\Support\Facades\Lang;
use InvalidArgumentException;

/**
 * The one source of the tags of the head of a public page. The route passes this array as the `seo` prop; the React
 * `SeoHead` (what Node renders) and the Blade fallback (what PHP prints when Node does not answer) both read it.
 *
 * @phpstan-type Seo array{title: string, description: string, canonical: string, image: array{url: string, width: int, height: int, alt: string}, locale: string, siteName: string, jsonLd: string}
 */
final class PageSeo
{
    private const string LOCALE = 'es_MX';

    private const string IMAGE = '/og-v1.png';

    /** @return Seo */
    public static function for(string $routeName): array
    {
        if (! Lang::has("seo.{$routeName}.title")) {
            throw new InvalidArgumentException("The route [{$routeName}] has no text in lang/es/seo.php.");
        }

        return self::build(
            route($routeName, [], false),
            self::string(__("seo.{$routeName}.title")),
            self::string(__("seo.{$routeName}.description")),
            self::string(__("seo.{$routeName}.image_alt")),
        );
    }

    /**
     * A tool's page: its text comes from ToolPages, and its structured data says what it is (the software), what it
     * answers (its questions), what it shows (its film) and where it sits (a breadcrumb under the landing).
     *
     * @return Seo
     */
    public static function tool(string $key): array
    {
        $page = ToolPages::page($key);
        $base = self::base();
        $url = $base.route(ToolPages::route($key), [], false);
        $film = $page['film'];

        return self::build(route(ToolPages::route($key), [], false), $page['title'], $page['description'], $page['h1'], [
            [
                '@type' => 'SoftwareApplication',
                'name' => self::string(config('app.name')).' · '.$page['name'],
                'description' => $page['description'],
                'url' => $url,
                'applicationCategory' => 'BusinessApplication',
                'operatingSystem' => 'Web',
                'inLanguage' => 'es-MX',
            ],
            [
                '@type' => 'FAQPage',
                'mainEntity' => array_map(fn (array $faq): array => [
                    '@type' => 'Question',
                    'name' => $faq['question'],
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq['answer']],
                ], $page['faqs']),
            ],
            [
                '@type' => 'VideoObject',
                'name' => $film['title'],
                'description' => $film['description'],
                'thumbnailUrl' => "{$base}/video/{$film['name']}.jpg",
                'contentUrl' => "{$base}/media/films/{$film['name']}.mp4",
                'uploadDate' => $film['uploaded'],
                'duration' => "PT{$film['duration']}S",
                'inLanguage' => 'es-MX',
            ],
            self::breadcrumbs([['Inicio', $base.'/'], [$page['name'], $url]]),
        ]);
    }

    /** @return Seo */
    public static function guide(): array
    {
        $guide = ToolPages::guide();
        $base = self::base();
        $path = route(ToolPages::GUIDE_ROUTE, [], false);
        $siteName = self::string(config('app.name'));

        return self::build($path, $guide['title'], $guide['description'], $guide['h1'], [
            [
                '@type' => 'Article',
                'headline' => $guide['h1'],
                'description' => $guide['description'],
                'url' => $base.$path,
                'dateModified' => $guide['updated'],
                'inLanguage' => 'es-MX',
                'author' => ['@type' => 'Organization', 'name' => $siteName],
                'publisher' => ['@type' => 'Organization', 'name' => $siteName],
            ],
            self::breadcrumbs([
                ['Inicio', $base.'/'],
                ['Registro de jornada', $base.route(ToolPages::route('attendance'), [], false)],
                ['Guía 2027', $base.$path],
            ]),
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $graph  the nodes of this page, after the site's own
     * @return Seo
     */
    private static function build(string $path, string $title, string $description, string $imageAlt, array $graph = []): array
    {
        $siteName = self::string(config('app.name'));
        $base = self::base();

        return [
            'title' => $title,
            'description' => $description,
            'canonical' => $base.$path,
            'image' => [
                'url' => $base.self::IMAGE,
                'width' => 1200,
                'height' => 630,
                'alt' => $imageAlt,
            ],
            'locale' => self::LOCALE,
            'siteName' => $siteName,
            'jsonLd' => self::jsonLd($siteName, $base, $graph),
        ];
    }

    /**
     * @param  list<array{string, string}>  $items  name and address, from the top
     * @return array<string, mixed>
     */
    private static function breadcrumbs(array $items): array
    {
        return [
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(fn (array $item, int $index): array => [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $item[0],
                'item' => $item[1],
            ], $items, array_keys($items)),
        ];
    }

    private static function base(): string
    {
        return rtrim(self::string(config('app.url')), '/');
    }

    /** JSON_HEX_TAG turns `<` and `>` into escapes, so a text with `</script>` cannot close the block it is printed in. */
    /** @param  list<array<string, mixed>>  $graph */
    private static function jsonLd(string $siteName, string $base, array $graph): string
    {
        return json_encode([
            '@context' => 'https://schema.org',
            '@graph' => [
                ['@type' => 'Organization', 'name' => $siteName, 'url' => $base.'/', 'logo' => $base.'/app-icon-512.png'],
                ['@type' => 'WebSite', 'name' => $siteName, 'url' => $base.'/', 'inLanguage' => 'es-MX'],
                ...$graph,
            ],
        ], JSON_HEX_TAG | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    private static function string(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}

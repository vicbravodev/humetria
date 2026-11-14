<?php

declare(strict_types=1);

namespace App\Http;

use Illuminate\Support\Facades\Lang;
use InvalidArgumentException;

/**
 * The one source of the tags of the head of a public page. The route passes this array as the `seo` prop; the React
 * `SeoHead` (what Node renders) and the Blade fallback (what PHP prints when Node does not answer) both read it.
 */
final class PageSeo
{
    private const string LOCALE = 'es_MX';

    private const string IMAGE = '/og-v1.png';

    /** @return array{title: string, description: string, canonical: string, image: array{url: string, width: int, height: int, alt: string}, locale: string, siteName: string, jsonLd: string} */
    public static function for(string $routeName): array
    {
        if (! Lang::has("seo.{$routeName}.title")) {
            throw new InvalidArgumentException("The route [{$routeName}] has no text in lang/es/seo.php.");
        }

        $siteName = self::string(config('app.name'));
        $base = rtrim(self::string(config('app.url')), '/');
        $canonical = $base.route($routeName, [], false);

        return [
            'title' => self::string(__("seo.{$routeName}.title")),
            'description' => self::string(__("seo.{$routeName}.description")),
            'canonical' => $canonical,
            'image' => [
                'url' => $base.self::IMAGE,
                'width' => 1200,
                'height' => 630,
                'alt' => self::string(__("seo.{$routeName}.image_alt")),
            ],
            'locale' => self::LOCALE,
            'siteName' => $siteName,
            'jsonLd' => self::jsonLd($siteName, $base),
        ];
    }

    /** JSON_HEX_TAG turns `<` and `>` into escapes, so a text with `</script>` cannot close the block it is printed in. */
    private static function jsonLd(string $siteName, string $base): string
    {
        return json_encode([
            '@context' => 'https://schema.org',
            '@graph' => [
                ['@type' => 'Organization', 'name' => $siteName, 'url' => $base.'/', 'logo' => $base.'/app-icon-512.png'],
                ['@type' => 'WebSite', 'name' => $siteName, 'url' => $base.'/', 'inLanguage' => 'es-MX'],
            ],
        ], JSON_HEX_TAG | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    private static function string(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}

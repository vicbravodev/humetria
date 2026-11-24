<?php

declare(strict_types=1);

namespace App\Http;

/**
 * The links of the public header, in one file (resources/js/lib/public-nav.json) that the React header of the landing and
 * the Blade header of the blog both read, so the two are the same page by construction and cannot drift.
 */
final class PublicNav
{
    /** @return list<array<string, string>> a `section` of the landing (`/#id`) or a `page` with its route name and path */
    public static function links(): array
    {
        $data = json_decode((string) file_get_contents(resource_path('js/lib/public-nav.json')), true, 8, JSON_THROW_ON_ERROR);
        $links = [];

        foreach (is_array($data) ? $data : [] as $link) {
            $entry = [];

            foreach (is_array($link) ? $link : [] as $key => $value) {
                if (is_string($key) && is_string($value)) {
                    $entry[$key] = $value;
                }
            }

            $links[] = $entry;
        }

        return $links;
    }

    /** Where a link goes from a page that is not the landing. */
    /** @param array<string, string> $link */
    public static function href(array $link): string
    {
        return $link['kind'] === 'page' ? $link['path'] : '/#'.$link['id'];
    }
}

<?php

declare(strict_types=1);

namespace App\Http;

/**
 * The links of the public header, in one file (resources/js/lib/public-nav.json) that the React header of the landing and
 * the Blade header of the blog both read, so the two are the same page by construction and cannot drift.
 *
 * Four things sit in the bar (Herramientas, Cómo funciona, Precios and the group Recursos); the rest lives inside the group.
 */
final class PublicNav
{
    /**
     * @return list<array<string, mixed>> `id`, `label`, `kind` and `test`, plus `section`, `route`, `path` or, for a group, its `items`
     */
    public static function links(): array
    {
        $links = [];

        foreach (self::file('links') as $link) {
            $entry = self::strings($link);
            $items = is_array($link) && is_array($link['items'] ?? null) ? array_map(self::strings(...), array_values($link['items'])) : null;

            $links[] = [...$entry, ...($items === null ? [] : ['items' => $items])];
        }

        return $links;
    }

    /**
     * The nine tools with the family each one belongs to and the lines of its icon: what the menu «Herramientas» lists.
     *
     * @return list<array<string, string|list<string>>> `key`, `name`, `hint`, `family`, `route` and `paths`
     */
    public static function tools(): array
    {
        $tools = [];

        foreach (self::file('tools') as $tool) {
            $entry = self::strings($tool);
            $paths = is_array($tool) && is_array($tool['paths'] ?? null) ? array_values(array_filter($tool['paths'], is_string(...))) : [];

            $tools[] = [...$entry, 'paths' => $paths];
        }

        return $tools;
    }

    /**
     * The lines of the icon of a link inside the group (Blog, Roadmap…), 24 px with a 1.75 stroke like the tools'.
     *
     * @return list<string>
     */
    public static function iconPaths(string $id): array
    {
        foreach (self::file('links') as $link) {
            foreach (is_array($link) && is_array($link['items'] ?? null) ? $link['items'] : [] as $item) {
                if (is_array($item) && ($item['id'] ?? null) === $id && is_array($item['paths'] ?? null)) {
                    return array_values(array_filter($item['paths'], is_string(...)));
                }
            }
        }

        return [];
    }

    /** @return list<array<string, string>> */
    public static function families(): array
    {
        return array_map(self::strings(...), self::file('families'));
    }

    /**
     * Every link a person can reach, the group's included, in reading order: what the phone menu lists.
     *
     * @return list<array<string, string>>
     */
    public static function flat(): array
    {
        $flat = [];

        foreach (self::links() as $link) {
            if (is_array($link['items'] ?? null)) {
                array_push($flat, ...array_map(self::strings(...), array_values($link['items'])));
            } else {
                $flat[] = self::strings($link);
            }
        }

        return $flat;
    }

    /**
     * Where a link goes from a page that is not the landing.
     *
     * @param  array<string, string>  $link
     */
    public static function href(array $link): string
    {
        return $link['kind'] === 'page' ? $link['path'] : '/#'.$link['id'];
    }

    /** @return list<mixed> */
    private static function file(string $key): array
    {
        $data = json_decode((string) file_get_contents(resource_path('js/lib/public-nav.json')), true, 8, JSON_THROW_ON_ERROR);
        $part = is_array($data) ? ($data[$key] ?? []) : [];

        return is_array($part) ? array_values($part) : [];
    }

    /** @return array<string, string> */
    private static function strings(mixed $link): array
    {
        $entry = [];

        foreach (is_array($link) ? $link : [] as $key => $value) {
            if (is_string($key) && is_string($value)) {
                $entry[$key] = $value;
            }
        }

        return $entry;
    }
}

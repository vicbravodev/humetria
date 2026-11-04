<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Support;

/**
 * The words of a policy as people read them: the placeholder `{legal_name}` is the legal name of the company, put in when it
 * is shown (the stored text keeps the placeholder, so a version never holds a name that later changes).
 */
final class PolicyText
{
    /**
     * @param  list<array{key: string, heading: string, text: string, items: list<string>, list_style: string|null}>  $content
     * @return list<array{key: string, heading: string, text: string, items: list<string>, listStyle: string|null}>
     */
    public static function sections(array $content, string $legalName): array
    {
        $put = fn (string $text): string => str_replace('{legal_name}', $legalName, $text);

        return array_map(fn (array $section): array => [
            'key' => $section['key'],
            'heading' => $section['heading'],
            'text' => $put($section['text']),
            'items' => array_map($put, $section['items']),
            'listStyle' => $section['list_style'],
        ], $content);
    }
}

<?php

declare(strict_types=1);

/*
 * The fallback and the React SeoHead print the same tags from the same `seo`. This is PHP's half of the proof: the
 * partial, rendered with a fixed `seo`, must equal the golden file; Node's half (piece 5) renders the bundle with the
 * same `seo` and compares its head with the same file. Change one renderer without the other and one of the two fails.
 */

/**
 * The tags Inertia manages in a head, as [tag, attributes, text]. Sorted by key, so the order of printing does not
 * matter, and without `nonce`, which changes with every request.
 *
 * @return list<array{tag: string, attrs: array<string, string>, text?: string}>
 */
function inertiaHeadTags(string $head): array
{
    $dom = new DOMDocument;
    @$dom->loadHTML('<?xml encoding="utf-8"?><html><head>'.$head.'</head></html>', LIBXML_NOERROR | LIBXML_NOWARNING);
    $tags = [];

    foreach ((new DOMXPath($dom))->query('//head/*[@data-inertia]') ?: [] as $element) {
        $attrs = [];

        foreach ($element->attributes ?? [] as $attribute) {
            if ($attribute->name !== 'nonce') {
                $attrs[$attribute->name] = $attribute->value;
            }
        }

        ksort($attrs);
        $tags[] = ['tag' => $element->nodeName, 'attrs' => $attrs, ...($element->nodeName === 'meta' || $element->nodeName === 'link' ? [] : ['text' => $element->textContent])];
    }

    usort($tags, fn (array $a, array $b): int => strcmp($a['attrs']['data-inertia'], $b['attrs']['data-inertia']));

    return $tags;
}

function seoTagsGolden(): array
{
    return json_decode((string) file_get_contents(base_path('tests/Ssr/fixtures/seo-tags.json')), true, flags: JSON_THROW_ON_ERROR);
}

it('prints in the fallback exactly the tags of the golden file', function (): void {
    $golden = seoTagsGolden();
    $expected = array_map(function (array $tag): array {
        ksort($tag['attrs']);

        return $tag;
    }, $golden['tags']);
    usort($expected, fn (array $a, array $b): int => strcmp($a['attrs']['data-inertia'], $b['attrs']['data-inertia']));

    $html = view('partials.seo-fallback', ['page' => ['props' => ['seo' => $golden['seo']]]])->render();

    expect(inertiaHeadTags($html))->toBe($expected);
});

it('gives every tag of the fallback a distinct data-inertia key', function (): void {
    $html = view('partials.seo-fallback', ['page' => ['props' => ['seo' => seoTagsGolden()['seo']]]])->render();
    $keys = array_map(fn (array $tag): string => $tag['attrs']['data-inertia'], inertiaHeadTags($html));

    expect($keys)->toBe(array_values(array_unique($keys)));
});

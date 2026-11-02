<?php

declare(strict_types=1);

/*
 * A piece of the design system is used, not copied: a copy drifts (another size, another radius) and a fix to the
 * piece never reaches it. Each case names the markup that gives a copy away and the component that replaces it.
 */

/** @return list<string> the files under resources/js, outside components/humetria and the shadcn primitives (components/ui), whose source matches */
function filesCopying(string $pattern): array
{
    $found = [];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__.'/../../resources/js', FilesystemIterator::SKIP_DOTS));

    foreach ($files as $file) {
        if (! str_contains((string) $file, '/components/humetria/')
            && ! str_contains((string) $file, '/components/ui/')
            && preg_match($pattern, (string) file_get_contents((string) $file)) === 1) {
            $found[] = $file->getFilename();
        }
    }

    sort($found);

    return $found;
}

it('draws an icon in its tile with IconTile', function (): void {
    expect(filesCopying('/size-10 (?:shrink-0 )?items-center justify-center rounded-lg bg-accent text-primary/'))->toBe([]);
});

it('draws a card with the card utility of app.css, as pantallas does with .card', function (): void {
    expect(filesCopying('/rounded-xl border bg-card/'))->toBe([]);
});

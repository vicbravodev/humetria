<?php

declare(strict_types=1);

/*
 * Color goes only through the design-system tokens (docs/design-system/): they switch with the dark theme and their
 * contrast is pinned by DesignSystemTest. Raw palette colors, white/black, hex and rgb/hsl/oklch literals bypass all that.
 *
 * Not scanned: generated code (actions, routes, wayfinder) and components/ui (shadcn primitives, adjusted separately
 * and lint-exempt).
 *
 * Arbitrary values only count when the value itself starts with a color function or hex, so the design system's float
 * shadow (`shadow-[0_8px_24px_rgb(...)]`, a shadow geometry that merely contains a color) stays allowed.
 */

const DESIGN_TOKENS_EXCLUDED = [
    'components/ui/',
    'actions/',
    'routes/',
    'wayfinder/',
];

const COLOR_PREFIXES = 'bg|text|border(?:-[xytrbles])?|ring(?:-offset)?|outline|fill|stroke|from|via|to|divide|placeholder|decoration|shadow|accent|caret';
const PALETTE = 'slate|gray|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose';

/** @return list<string> */
function rawColorsIn(string $line): array
{
    $start = '(?<![\w\[-])';
    $patterns = [
        "/{$start}(?:".COLOR_PREFIXES.')-(?:(?:'.PALETTE.')-\d{2,3}|white|black)(?:\/\d+)?(?![\w-])/',
        "/{$start}(?:".COLOR_PREFIXES.')-\[(?:#|rgba?\(|hsla?\(|oklch\()[^\]]*\]/',
        '/\b(?:\w*[cC]olor|background|fill|stroke|border|outline)\s*:\s*[\'"`](?:#|rgba?\(|hsla?\(|oklch\()/',
    ];

    $found = [];
    foreach ($patterns as $pattern) {
        preg_match_all($pattern, $line, $matches);
        array_push($found, ...$matches[0]);
    }

    return $found;
}

it('recognises raw colors and lets design tokens through', function (string $line, bool $isRaw) {
    expect(rawColorsIn($line) !== [])->toBe($isRaw);
})->with([
    'palette text' => ['text-red-600', true],
    'palette in variant' => ['dark:hover:bg-neutral-800/50', true],
    'border side' => ['border-t-zinc-200', true],
    'stroke' => ['stroke-zinc-900', true],
    'white' => ['text-white', true],
    'black with opacity' => ['bg-black/50', true],
    'ring offset' => ['ring-offset-white', true],
    'gradient' => ['from-blue-500 to-indigo-600', true],
    'arbitrary hex' => ['text-[#f53003]', true],
    'arbitrary rgb' => ['bg-[rgb(1,2,3)]', true],
    'arbitrary oklch' => ['border-[oklch(0.5_0.1_20)]', true],
    'style hex' => ["style={{ color: '#fff' }}", true],
    'style background rgb' => ['style={{ backgroundColor: "rgb(0,0,0)" }}', true],
    'destructive token' => ['text-destructive', false],
    'opacity on token' => ['bg-primary/10', false],
    'input token' => ['border-input', false],
    'text size' => ['text-base text-sm', false],
    'stroke width' => ['stroke-2', false],
    'ring width' => ['ring-2 ring-offset-2', false],
    'outline offset' => ['outline-offset-2', false],
    'to inside words' => ['go to-do list, from-scratch', false],
    'float shadow' => ['shadow-[0_8px_24px_rgb(0_0_0/0.08)]', false],
    'style token' => ["style={{ color: 'var(--primary)' }}", false],
    'palette name as word' => ['const red = 3; text-redacted', false],
]);

it('uses no raw colors in the frontend', function () {
    $root = realpath(__DIR__.'/../../resources/js');
    $violations = [];

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        $path = substr($file->getPathname(), strlen($root) + 1);

        if (! preg_match('/\.tsx?$/', $path) || array_filter(DESIGN_TOKENS_EXCLUDED, fn (string $excluded): bool => str_starts_with($path, $excluded))) {
            continue;
        }

        foreach (file($file->getPathname()) ?: [] as $number => $line) {
            foreach (rawColorsIn($line) as $match) {
                $violations[] = "resources/js/{$path}:".($number + 1).": {$match}";
            }
        }
    }

    expect($violations)->toBe([]);
});

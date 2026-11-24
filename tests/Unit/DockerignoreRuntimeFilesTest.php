<?php

declare(strict_types=1);

/**
 * Whether `.dockerignore` keeps `$path` out of the image, the conservative way: a directory that is excluded takes everything
 * under it, whatever a later rule says, so a file is in only when no folder on its way, nor the file, ends up excluded. Within
 * one path the last rule that matches wins, and `!` brings it back. `*` stays inside a name, `**` crosses folders.
 *
 * @param  list<string>  $rules
 */
function dockerIgnores(array $rules, string $path): bool
{
    $parts = explode('/', $path);

    for ($depth = 1; $depth <= count($parts); $depth++) {
        $prefix = implode('/', array_slice($parts, 0, $depth));
        $excluded = false;

        foreach ($rules as $rule) {
            $negated = str_starts_with($rule, '!');
            $pattern = ltrim($negated ? substr($rule, 1) : $rule, '/');
            $regex = '~^'.str_replace(['\*\*', '\*'], ['.*', '[^/]*'], preg_quote($pattern, '~')).'$~';

            if (preg_match($regex, $prefix) === 1) {
                $excluded = ! $negated;
            }
        }

        if ($excluded) {
            return true;
        }
    }

    return false;
}

/** @return list<string> */
function dockerignoreRules(): array
{
    $lines = array_map(trim(...), file(dirname(__DIR__, 2).'/.dockerignore', FILE_IGNORE_NEW_LINES) ?: []);

    return array_values(array_filter($lines, fn (string $line): bool => $line !== '' && ! str_starts_with($line, '#')));
}

describe('.dockerignore', function (): void {
    it('keeps in the image every document the app reads at runtime', function (): void {
        $humi = require dirname(__DIR__, 2).'/config/humi.php';
        $read = [...array_values($humi['regulations']), 'content/blog/ejemplo.md'];

        expect($read)->not->toBeEmpty();

        foreach ($read as $path) {
            expect(dockerIgnores(dockerignoreRules(), $path))->toBeFalse("{$path} is read at runtime but .dockerignore leaves it out of the image");
        }
    });

    it('still keeps out the rest of docs', function (): void {
        foreach (['docs/superpowers/specs/x', 'docs/testing', 'docs/operations/ssr.md', 'docs/design-system/og/og-v1.html'] as $path) {
            expect(dockerIgnores(dockerignoreRules(), $path))->toBeTrue("{$path} should stay out of the image");
        }
    });

    it('understands its own matcher: a folder excluded takes its files whatever a later rule says', function (): void {
        expect(dockerIgnores(['docs', '!docs/normas'], 'docs/normas/a.md'))->toBeTrue()
            ->and(dockerIgnores(['docs/*', '!docs/normas'], 'docs/normas/a.md'))->toBeFalse()
            ->and(dockerIgnores(['docs/*', '!docs/normas'], 'docs/other'))->toBeTrue();
    });
});

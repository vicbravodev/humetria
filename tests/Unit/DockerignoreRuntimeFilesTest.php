<?php

declare(strict_types=1);

/**
 * Whether `.dockerignore` keeps `$path` out of the image, as Docker decides it: the rules are read in order and the last one
 * that matches wins, `!` bringing the path back. A rule matches a path when it matches the path itself or any folder on its
 * way, so `docs` matches `docs/normas/a.md` too, and a later `!docs/normas` still re-includes it. `*` stays inside a name,
 * `**` crosses folders.
 *
 * @param  list<string>  $rules
 */
function dockerIgnores(array $rules, string $path): bool
{
    $parts = explode('/', $path);
    $excluded = false;

    foreach ($rules as $rule) {
        $negated = str_starts_with($rule, '!');
        $pattern = ltrim($negated ? substr($rule, 1) : $rule, '/');
        $regex = '~^'.str_replace(['\*\*', '\*'], ['.*', '[^/]*'], preg_quote($pattern, '~')).'$~';

        for ($depth = 1; $depth <= count($parts); $depth++) {
            if (preg_match($regex, implode('/', array_slice($parts, 0, $depth))) === 1) {
                $excluded = ! $negated;

                break;
            }
        }
    }

    return $excluded;
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

    it('understands its own matcher as Docker does: the last rule that matches wins, and a negation of a nested path re-includes it under an excluded folder', function (): void {
        expect(dockerIgnores(['docs', '!docs/normas'], 'docs/normas/a.md'))->toBeFalse()
            ->and(dockerIgnores(['docs/*', '!docs/normas'], 'docs/normas/a.md'))->toBeFalse()
            ->and(dockerIgnores(['docs/*', '!docs/normas'], 'docs/other'))->toBeTrue()
            ->and(dockerIgnores(['!docs/normas', 'docs'], 'docs/normas/a.md'))->toBeTrue()
            ->and(dockerIgnores(['docs/*', '!docs/normas', '!docs/normas/**'], 'docs/operations/ssr.md'))->toBeTrue();
    });
});

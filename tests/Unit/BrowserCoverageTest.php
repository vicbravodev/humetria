<?php

declare(strict_types=1);

/*
 * Every Inertia page must be opened by a browser test through visitPage() (tests/Pest.php),
 * which checks the rendered component, JavaScript errors, CSP violations and accessibility.
 */

/** @return list<string> */
function filesUnder(string $directory, string $extension): array
{
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));

    return array_values(array_filter(
        array_map(fn (SplFileInfo $file): string => $file->getPathname(), iterator_to_array($files, false)),
        fn (string $path): bool => str_ends_with($path, $extension),
    ));
}

/**
 * The pages no visitPage() call in the PHP files opens, sorted. Comments are left out: a commented-out call opens nothing.
 *
 * @param  list<string>  $pages
 * @param  list<string>  $files  the contents of each PHP file
 * @return list<string>
 */
function uncoveredPages(array $pages, array $files): array
{
    $tokens = array_merge([], ...array_map(token_get_all(...), $files));
    $source = implode('', array_map(
        fn (array|string $token): string => match (true) {
            is_string($token) => $token,
            in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true) => '',
            default => $token[1],
        },
        $tokens,
    ));
    $uncovered = array_values(array_filter($pages, fn (string $page): bool => ! str_contains($source, "visitPage('{$page}'")));
    sort($uncovered);

    return $uncovered;
}

it('counts only the visitPage() calls that run, never a commented-out one', function (): void {
    $source = <<<'PHP'
        <?php
        visitPage('landing', '/');
        // visitPage('auth/login', '/login');
        # visitPage('auth/register', '/register');
        /* visitPage('dashboard', '/admin'); */
        /** visitPage('settings/profile', '/settings'); */
        PHP;

    expect(uncoveredPages(['landing', 'auth/login', 'auth/register', 'dashboard', 'settings/profile'], [$source]))
        ->toBe(['auth/login', 'auth/register', 'dashboard', 'settings/profile']);
});

it('opens every Inertia page in a browser test through visitPage', function (): void {
    $pagesDirectory = __DIR__.'/../../resources/js/pages/';

    $pages = array_map(fn (string $path): string => substr($path, strlen($pagesDirectory), -strlen('.tsx')), filesUnder($pagesDirectory, '.tsx'));
    $uncovered = uncoveredPages($pages, array_map(fn (string $path): string => (string) file_get_contents($path), filesUnder(__DIR__.'/../Browser', '.php')));

    expect($pages)->not->toBeEmpty()
        ->and($uncovered)->toBe([], 'Pages without a visitPage() browser test: '.implode(', ', $uncovered));
});

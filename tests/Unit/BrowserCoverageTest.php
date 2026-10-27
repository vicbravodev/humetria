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

it('opens every Inertia page in a browser test through visitPage', function (): void {
    $pagesDirectory = __DIR__.'/../../resources/js/pages/';

    $pages = array_map(fn (string $path): string => substr($path, strlen($pagesDirectory), -strlen('.tsx')), filesUnder($pagesDirectory, '.tsx'));
    $browserTests = implode("\n", array_map(file_get_contents(...), filesUnder(__DIR__.'/../Browser', '.php')));

    $uncovered = array_values(array_filter($pages, fn (string $page): bool => ! str_contains($browserTests, "visitPage('{$page}'")));
    sort($uncovered);

    expect($pages)->not->toBeEmpty()
        ->and($uncovered)->toBe([], 'Pages without a visitPage() browser test: '.implode(', ', $uncovered));
});

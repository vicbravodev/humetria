<?php

declare(strict_types=1);

/*
 * Prints the classes the weekly mutation run passes to `pest --mutate --class`, one per line, sorted: every class a
 * test names in mutates(), resolved through its use statements. A test under Fixtures/ is another test's input: it is
 * never mutated.
 *
 * Usage: php scripts/mutation-classes.php tests
 */

$testsDirectory = rtrim($argv[1] ?? 'tests', '/');
$classes = [];

foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($testsDirectory, FilesystemIterator::SKIP_DOTS)) as $file) {
    if ($file instanceof SplFileInfo && $file->getExtension() === 'php' && ! str_starts_with($file->getPathname(), $testsDirectory.'/Fixtures/')) {
        $classes = [...$classes, ...mutatedClasses(file_get_contents($file->getPathname()) ?: '')];
    }
}

$classes = array_values(array_unique($classes));
sort($classes);

echo $classes === [] ? '' : implode("\n", $classes)."\n";

/**
 * The fully qualified classes a test file names in mutates(), resolved through its use statements.
 *
 * @return list<string>
 */
function mutatedClasses(string $source): array
{
    preg_match_all('/^use\s+([\w\\\\]+)(?:\s+as\s+(\w+))?;/m', $source, $uses, PREG_SET_ORDER);

    $imports = [];

    foreach ($uses as $use) {
        $imports[$use[2] ?? '' ?: substr((string) strrchr('\\'.$use[1], '\\'), 1)] = $use[1];
    }

    preg_match_all('/\bmutates\(([^)]*)\)/', $source, $calls);
    preg_match_all('/\\\\?([\w\\\\]+)::class/', implode(',', $calls[1]), $names);

    return array_values(array_unique(array_map(fn (string $name): string => $imports[$name] ?? $name, $names[1])));
}

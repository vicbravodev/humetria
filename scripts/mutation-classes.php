<?php

declare(strict_types=1);

/*
 * Prints the classes a CI shard passes to `pest --mutate --class`, one per line, given the changed paths on stdin.
 *
 * Every shard runs all the mutation tests (--group=__pest_mutate_only), as the full run does: any of them can kill a
 * mutant, so a class gets the same result in any shard. Only which classes get mutated is split.
 *
 * A pull request mutates the classes of every test file that mutates a file the diff touched, or the classes of a
 * changed test. Every class is mutated with --all, or when the diff touches what every test depends on.
 *
 * --shard=K/N keeps the K-th of N shards. Test files that make Pest mutate a same file share a shard, so no file is
 * mutated twice, and shards balance by the size of the files they mutate.
 *
 * Usage: git diff --name-only origin/main...HEAD | php scripts/mutation-classes.php [--all] [--shard=K/N] tests app
 */

// Paths every test depends on: a change here can move any mutation's result.
const SHARED = ['composer.lock', 'composer.json', 'package.json', 'package-lock.json', 'phpunit.xml', 'tests/Pest.php', 'tests/TestCase.php'];
const SHARED_DIRECTORIES = ['bootstrap/', 'config/', 'database/', 'lang/', 'resources/views/', 'routes/', 'scripts/'];

$options = getopt('', ['all', 'shard:'], $firstArgument);
$testsDirectory = rtrim($argv[$firstArgument] ?? 'tests', '/');
$sourceDirectory = rtrim($argv[$firstArgument + 1] ?? 'app', '/');
$changed = array_values(array_filter(array_map(trim(...), explode("\n", stream_get_contents(STDIN) ?: ''))));

$sources = phpFiles($sourceDirectory);

/** @var array<string, array{classes: list<string>, sources: list<string>}> $tests test file => what it mutates */
$tests = [];

foreach (phpFiles($testsDirectory) as $path => $contents) {
    // Fixtures are other tests' input: they never get mutated.
    $classes = str_starts_with($path, $testsDirectory.'/Fixtures/') ? [] : mutatedClasses($contents);

    if ($classes !== []) {
        $tests[$path] = ['classes' => $classes, 'sources' => array_keys(array_filter($sources, fn (string $source): bool => pestMutates($source, $classes)))];
    }
}

$everything = isset($options['all']) || array_any($changed, fn (string $path): bool => in_array($path, SHARED, true)
    || array_any(SHARED_DIRECTORIES, fn (string $directory): bool => str_starts_with($path, $directory)));

$touched = array_map(fn (string $path): string => str_starts_with($path, 'app/') ? $sourceDirectory.substr($path, 3) : $path, $changed);

$selected = $everything ? $tests : array_filter(
    $tests,
    fn (array $test, string $path): bool => in_array($path, $touched, true) || array_intersect($test['sources'], $touched) !== [],
    ARRAY_FILTER_USE_BOTH,
);

if (is_string($options['shard'] ?? null)) {
    [$shard, $shards] = array_map(intval(...), explode('/', $options['shard'], 2) + [1 => '1']);
    $selected = array_intersect_key($selected, array_flip(shard($selected, $shards)[$shard - 1] ?? []));
}

$classes = array_values(array_unique(array_merge([], ...array_column($selected, 'classes'))));
sort($classes);

echo $classes === [] ? '' : implode("\n", $classes)."\n";

/**
 * Splits the test files into shards: files that make Pest mutate a same file stay together, and each group goes,
 * heaviest first, to the lightest shard. The result only depends on its input, so every job computes the same split.
 *
 * @param  array<string, array{classes: list<string>, sources: list<string>}>  $tests
 * @return list<list<string>>
 */
function shard(array $tests, int $shards): array
{
    /** @var list<array{files: list<string>, sources: list<string>}> $groups */
    $groups = [];

    foreach ($tests as $file => $test) {
        $group = ['files' => [$file], 'sources' => $test['sources']];

        foreach ($groups as $index => $other) {
            if (array_intersect($other['sources'], $group['sources']) !== []) {
                $group = ['files' => [...$other['files'], ...$group['files']], 'sources' => [...$other['sources'], ...$group['sources']]];
                unset($groups[$index]);
            }
        }

        $groups = [...$groups, $group];
    }

    $weights = [];

    foreach ($groups as $index => $group) {
        $weights[$index] = array_sum(array_map(fn (string $source): int => filesize($source) ?: 0, array_unique($group['sources'])));
    }

    $order = array_keys($groups);
    usort($order, fn (int $a, int $b): int => [$weights[$b], $groups[$a]['files']] <=> [$weights[$a], $groups[$b]['files']]);

    $loads = array_fill(0, max(1, $shards), 0);
    $buckets = array_fill(0, max(1, $shards), []);

    foreach ($order as $index) {
        $lightest = array_keys($loads, min($loads), true)[0];
        $loads[$lightest] += $weights[$index];
        $buckets[$lightest] = [...$buckets[$lightest], ...$groups[$index]['files']];
    }

    return $buckets;
}

/**
 * Whether Pest mutates this source for these classes: a copy of Pest\Mutate\Support\MutationGenerator's
 * doesNotContainClassToMutate(), loose matches included (Invoice also mutates InvoiceLine), so the split matches
 * what Pest mutates. Revisit it when pestphp/pest-plugin-mutate is upgraded.
 *
 * @param  list<string>  $classes
 */
function pestMutates(string $contents, array $classes): bool
{
    foreach ($classes as $classOrNamespace) {
        $parts = explode('\\', $classOrNamespace);
        $class = array_pop($parts);
        $namespace = preg_quote(implode('\\', $parts));
        $classOrNamespace = preg_quote($classOrNamespace);

        if (preg_match("/namespace\\s+$namespace/", $contents) === 1 && preg_match("/(?:class|trait)\\s+$class.*/", $contents) === 1) {
            return true;
        }

        if (preg_match("/(?:class|trait)\\s+$class\[{\\s*\]/", $contents) === 1 || preg_match("/namespace\\s+$classOrNamespace/", $contents) === 1) {
            return true;
        }
    }

    return false;
}

/**
 * Every PHP file under a directory, path => contents, sorted by path.
 *
 * @return array<string, string>
 */
function phpFiles(string $directory): array
{
    $files = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file instanceof SplFileInfo && $file->getExtension() === 'php') {
            $files[$file->getPathname()] = file_get_contents($file->getPathname()) ?: '';
        }
    }

    ksort($files);

    return $files;
}

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

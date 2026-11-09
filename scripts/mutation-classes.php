<?php

declare(strict_types=1);

/*
 * Prints the classes the weekly mutation run passes to `pest --mutate --class`, one per line, sorted: every class a
 * test names in mutates(), resolved through its use statements. A test under Fixtures/ is another test's input: it is
 * never mutated.
 *
 * With --plan it prints instead the run of scripts/mutate-critical.sh, one line per module (the namespace under
 * App\Modules\, or Kernel for the rest), sorted, tab-separated: the module, its classes (comma-separated) and a
 * --filter that runs only the test files that mutate them. Controllers, form requests and notifications are too thin
 * to mutate and a module left with none has no line. The filter matters because Pest measures coverage with every
 * test it runs and gives each mutant's process one alternative per covering test: a line the whole suite touches
 * overflows the OS argument limit (128 KB) and the run dies ("Argument list too long").
 *
 * Usage: php scripts/mutation-classes.php tests [--plan]
 */

$plan = in_array('--plan', $argv ?? [], true);
$testsDirectory = rtrim($argv[1] ?? 'tests', '/');
$testFilesByClass = [];

foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($testsDirectory, FilesystemIterator::SKIP_DOTS)) as $file) {
    if ($file instanceof SplFileInfo && $file->getExtension() === 'php' && ! str_starts_with($file->getPathname(), $testsDirectory.'/Fixtures/')) {
        foreach (mutatedClasses(file_get_contents($file->getPathname()) ?: '') as $class) {
            $testFilesByClass[$class][] = $file->getPathname();
        }
    }
}

$classes = array_keys($testFilesByClass);
sort($classes);

if ($plan) {
    echo planByModule($testFilesByClass);

    return;
}

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

/**
 * One tab-separated line per module: its classes and the --filter of the test files that mutate them.
 *
 * @param  array<string, list<string>>  $testFilesByClass
 */
function planByModule(array $testFilesByClass): string
{
    $modules = [];

    foreach ($testFilesByClass as $class => $files) {
        if (preg_match('/\\\\(Http\\\\(Controllers|Requests)|Notifications)\\\\/', $class) === 1) {
            continue;
        }

        $module = preg_match('/^App\\\\Modules\\\\(\w+)\\\\/', $class, $match) === 1 ? $match[1] : 'Kernel';
        $modules[$module]['classes'][] = $class;
        $modules[$module]['files'] = [...$modules[$module]['files'] ?? [], ...$files];
    }

    ksort($modules);

    $lines = '';

    foreach ($modules as $module => $run) {
        $files = array_unique($run['files']);
        sort($files);
        sort($run['classes']);

        $lines .= implode("\t", [$module, implode(',', $run['classes']), implode('|', array_map(testIdPattern(...), $files))])."\n";
    }

    return $lines;
}

/**
 * The regex that picks a test file's tests out of Pest's ids (P\Tests\Feature\FooTest::name): its class, up to the
 * "::" so FooTest never also picks FooTestExtra, and from the namespace separator so it never picks XFooTest.
 */
function testIdPattern(string $path): string
{
    return preg_quote('Tests\\'.str_replace('/', '\\', substr($path, strlen('tests/'), -strlen('.php'))), '/').'::';
}

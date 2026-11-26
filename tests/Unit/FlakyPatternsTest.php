<?php

declare(strict_types=1);

/*
 * The patterns that made tests pass or fail by chance, the clock or the speed of the runner. Each one failed CI at least
 * once on a commit that had nothing to do with it; the rules are in docs/testing.md, «Pruebas que no fallan por azar».
 */

/** @return list<string> every PHP file under tests/ except this one */
function testFiles(): array
{
    $files = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__), FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->getExtension() === 'php' && ! str_contains($file->getPathname(), '/Fixtures/') && $file->getPathname() !== __FILE__ && ! str_contains($file->getPathname(), '/js/')) {
            $files[] = $file->getPathname();
        }
    }

    sort($files);

    return $files;
}

/** @return list<string> «file:line» of every line that matches the pattern */
function testLinesMatching(string $pattern): array
{
    $found = [];

    foreach (testFiles() as $file) {
        foreach (file($file, FILE_IGNORE_NEW_LINES) ?: [] as $number => $line) {
            if (preg_match($pattern, $line) === 1 && ! str_starts_with(ltrim($line), '//') && ! str_starts_with(ltrim($line), '*')) {
                $found[] = substr($file, strlen(dirname(__DIR__, 2)) + 1).':'.($number + 1);
            }
        }
    }

    return $found;
}

it('never sleeps in a test: it waits for the thing it needs', function (): void {
    expect(testLinesMatching('/\b(?:u?sleep|time_nanosleep)\(/'))->toBe([]);
});

it('reads the clock of the app, never time(), date() or strtotime(): a test that travels in time moves only that one', function (): void {
    expect(testLinesMatching('/(?<![>\w$:])(?:time|date|strtotime|mktime)\(/'))->toBe([]);
});

it('opens a page once with openPath(), never with navigate(): Pest sends navigate() again every second while the page loads', function (): void {
    expect(testLinesMatching('/->navigate\(/'))->toBe([]);
});

it('checks accessibility with accessible(), never assertNoAccessibilityIssues(): Pest gives axe 5 s whatever the timeout of the suite', function (): void {
    expect(testLinesMatching('/->assertNoAccessibilityIssues\(/'))->toBe([]);
});

it('waits for what a browser test asserts, not a fixed number of seconds', function (): void {
    $waits = testLinesMatching('/->wait\(/');

    // Observation windows: «this search never goes out» has nothing to wait for, only a time in which it must not happen.
    // The same for the invitation of Humi: it asks itself every second whether to appear, so «it does not appear» is a
    // window of two ticks after the page clock jumps, not a state the page reaches.
    expect(array_values(array_diff($waits, [
        'tests/Browser/TenantConsoleTest.php:655',
        'tests/Browser/TenantConsoleTest.php:673',
        'tests/Browser/AssistantInvitationTest.php:97',
        'tests/Browser/AssistantInvitationTest.php:104',
        'tests/Browser/AssistantInvitationTest.php:106',
        'tests/Browser/AssistantInvitationTest.php:124',
    ])))->toBe([]);
});

it('does not look for a short number in text that carries ids or dates (a UUID or a timestamp has «50000» by chance)', function (): void {
    $risky = [];

    foreach (testFiles() as $file) {
        foreach (explode(';', (string) file_get_contents($file)) as $statement) {
            $looksForShortNumber = preg_match('/not->toContain\([^)]*[\'"]\d{1,6}[\'"]/', $statement) === 1;
            $inTextWithIds = preg_match('/json_encode\(|getContent\(\)|->toArray\(\)|->toJson\(|serialize\(/', $statement) === 1;

            if ($looksForShortNumber && $inTextWithIds && ! str_contains($statement, 'auditContent(')) {
                $risky[] = substr($file, strlen(dirname(__DIR__, 2)) + 1).': '.trim(preg_replace('/\s+/', ' ', $statement) ?? '');
            }
        }
    }

    expect($risky)->toBe([]);
});

<?php

declare(strict_types=1);

/*
 * The landing's text lives once, in the React page (what Node renders). A `<noscript>` copy in a Blade view would be a
 * second version of it to keep in step, so no view carries one. A guard: it passes from the start.
 */
it('has no noscript block in any Blade view', function (): void {
    $offenders = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/resources/views')) as $file) {
        if ($file->isFile() && stripos((string) file_get_contents($file->getPathname()), '<noscript') !== false) {
            $offenders[] = $file->getPathname();
        }
    }

    expect($offenders)->toBe([]);
});

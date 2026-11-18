<?php

declare(strict_types=1);

/*
 * The person's pages read their session: no address of theirs carries a credential that serves more than once (the
 * WorkerAccess design, goal 1). No text may still send them to a personal link of their own, or warn them not to share it.
 */
it('never sends the person to a personal link of their own, nor tells them not to share it', function (): void {
    // frontendSourceFiles() leaves out the zz-probe-* folders FrontendArchitectureTest creates and deletes in parallel.
    $promising = [];

    foreach (frontendSourceFiles() as $file) {
        if ($file->getExtension() === 'tsx') {
            $text = (string) preg_replace('/\s+/', ' ', (string) file_get_contents($file->getPathname()));

            if (str_contains($text, 'enlace personal que') || str_contains($text, 'comparta su enlace')) {
                $promising[] = $file->getFilename();
            }
        }
    }

    sort($promising);

    expect($promising)->toBe([]);
});

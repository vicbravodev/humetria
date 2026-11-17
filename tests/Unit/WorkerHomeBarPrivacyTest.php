<?php

declare(strict_types=1);

/*
 * The portal's bottom bar and account tab carry the person's name. Anonymous screens (surveys, Ley Silla's notice,
 * complaints, shared reports) must never wear them: only the portal home may.
 */
it('uses the portal tab bar only on the portal home', function (): void {
    $users = [];

    foreach (frontendSourceFiles() as $file) {
        if (in_array($file->getExtension(), ['ts', 'tsx'], true) && str_contains((string) file_get_contents($file->getPathname()), 'portal-tab-bar')) {
            $users[] = str_replace('\\', '/', substr($file->getPathname(), strlen(dirname(__DIR__, 2).'/resources/js/')));
        }
    }

    expect($users)->toBe(['components/worker-access/home-screen.tsx']);
});

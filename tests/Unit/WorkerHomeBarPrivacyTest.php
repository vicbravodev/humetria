<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

/*
 * The portal's bottom bar and account tab carry the person's name. Anonymous screens (surveys, Ley Silla's notice,
 * complaints, shared reports) must never wear them: only the portal home may.
 */
it('uses the portal tab bar only on the portal home', function (): void {
    $users = [];

    $files = Finder::create()->files()->in(__DIR__.'/../../resources/js')->name('/\.tsx?$/')->contains('portal-tab-bar');

    foreach ($files as $file) {
        $users[] = $file->getRelativePathname();
    }

    expect($users)->toBe(['components/worker-access/home-screen.tsx']);
});

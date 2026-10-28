<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Vite;

// Sail's Vite service writes public/hot into the same checkout the tests run in.
it('never reads the hot file of a running Vite dev server, so tests render the build', function (): void {
    expect(Vite::hotFile())->not->toBe(public_path('hot'))
        ->and(Vite::isRunningHot())->toBeFalse();
});

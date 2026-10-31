<?php

declare(strict_types=1);

use Laravel\Fortify\Features;
use PHPUnit\Framework\ExpectationFailedException;

it('fails, never skips, a test that needs a Fortify feature the app turned off', function (): void {
    config(['fortify.features' => []]);

    expect(fn () => $this->assertFortifyHas(Features::resetPasswords()))
        ->toThrow(ExpectationFailedException::class, 'Fortify feature [reset-passwords] is off');
});

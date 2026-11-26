<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\ExpectationFailedException;

// accessible() (tests/Pest.php) checks what Pest's assertNoAccessibilityIssues() checks, with the timeout of the suite.
beforeEach(function (): void {
    Route::get('/zz-accessible/{case}', fn (string $case) => '<!DOCTYPE html><html lang="es"><head><title>Prueba</title></head><body><main><h1>Prueba</h1>'
        .($case === 'broken' ? '<img src="/favicon.svg">' : '<img src="/favicon.svg" alt="Logo">')
        .'</main></body></html>');
});

it('passes a page with no accessibility issues', function (): void {
    expect(accessible(visit('/zz-accessible/clean')->assertSee('Prueba')))->toBeInstanceOf(Pest\Browser\Api\AwaitableWebpage::class);
});

it('fails a page with an image without its text, naming the rule', function (): void {
    expect(fn () => accessible(visit('/zz-accessible/broken')->assertSee('Prueba')))
        ->toThrow(ExpectationFailedException::class, 'image-alt');
});

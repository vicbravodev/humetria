<?php

declare(strict_types=1);

/*
 * What Node runs when it renders the landing must not need a browser. Static checks over the .tsx files, no tool of their own:
 * the real proof is composer test:ssr, which renders the bundle in Node.
 */

function sourceOf(string $path): string
{
    return (string) file_get_contents(__DIR__.'/../../resources/js/'.$path);
}

it('does not read document in app.tsx unless it exists', function (): void {
    $app = sourceOf('app.tsx');
    // Every line that calls document must sit inside the typeof guard, which is the only place the page script is read.
    preg_match_all('/\bdocument\./', $app, $uses);
    preg_match('/if \(typeof document !== \'undefined\'\) \{(.*?)\n\}/s', $app, $guard);

    expect($guard)->not->toBeEmpty()
        ->and(substr_count($guard[1] ?? '', 'document.'))->toBe(count($uses[0]));
});

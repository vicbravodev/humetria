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

it('computes nothing time-dependent at the top level of a landing component', function (): void {
    // A module-level `new Date()` is evaluated once, when Node starts, and prints the same year until it restarts.
    $offenders = collect(glob(__DIR__.'/../../resources/js/components/landing/*.tsx'))
        ->filter(fn (string $file): bool => preg_match('/^(?:const|let|var) .*new Date\(/m', (string) file_get_contents($file)) === 1)
        ->map(fn (string $file): string => basename($file))
        ->values()
        ->all();

    expect($offenders)->toBe([]);
});

it('writes no inline style or motion entrance in a landing component the server renders', function (): void {
    // `initial`, `animate`, `whileInView` (motion) and `style={{` print a style="" in the HTML Node renders; the CSP has no
    // style-src-attr, so the browser blocks it, and an `opacity:0` hid the text from whoever did not run the script. The
    // entrances are CSS (app.css, hooks/use-scroll-reveal.ts). The exceptions mount only in the browser, each with its reason.
    $exceptions = [
        // AnimatePresence swaps the result of the filter: it sits inside WhenMounted, whose fallback is the result without motion.
        'privacy-demo.tsx',
        // The menu «Recursos» mounts only when a click opens it, like the tools menu: the server never renders it.
        'resources-menu.tsx',
        // The pick's answer, the step stage and the bar of the step playing swap with motion inside WhenMounted.
        'tool-page-pick.tsx',
        'tool-page-step-stage.tsx',
        'tool-page-step.tsx',
        // The tools menu mounts only when a click opens it: the server never renders it.
        'tools-menu-panel.tsx',
    ];

    $offenders = collect(glob(__DIR__.'/../../resources/js/components/landing/*.tsx'))
        ->filter(fn (string $file): bool => preg_match('/\binitial=|\bwhileInView=|\banimate=|\bstyle=\{\{/', (string) file_get_contents($file)) === 1)
        ->map(fn (string $file): string => basename($file))
        ->values()
        ->all();

    expect($offenders)->toBe($exceptions);
});

it('keeps the motion exceptions inside WhenMounted', function (string $file): void {
    expect(sourceOf('components/landing/'.$file))->toContain('<WhenMounted');
})->with(['privacy-demo.tsx', 'tool-page-pick.tsx', 'tool-page-step-stage.tsx', 'tool-page-step.tsx']);

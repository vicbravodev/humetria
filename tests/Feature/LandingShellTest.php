<?php

declare(strict_types=1);

/*
 * The document PHP prints around the landing. Its inline script tells the stylesheet that JavaScript runs (`html.js`):
 * the eight tool panels that are not chosen hide only then, so without JavaScript the nine tools read stacked.
 */

it('marks <html> as js-enabled from the inline script that carries the nonce', function (): void {
    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toContain("document.documentElement.classList.add('js')")
        ->and($html)->toMatch('/<script nonce="[^"]+">\s*\(function\(\) \{\s*document\.documentElement\.classList\.add\(\'js\'\)/');
});

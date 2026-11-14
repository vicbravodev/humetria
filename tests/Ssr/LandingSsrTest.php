<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;

/*
 * The landing with a real Node behind it (composer test:ssr): the HTML Node rendered hydrates in the browser without errors
 * and the head keeps one tag of each kind. The app is served in-process by Pest Browser; only the call to Node goes out.
 *
 * Not here yet: «no CSP violations» and «no style attributes». The entrance animations still write `style` in the HTML.
 */

beforeEach(function (): void {
    Http::allowStrayRequests(['http://127.0.0.1:13714/*']);
    config(['inertia.ssr.enabled' => true, 'inertia.ssr.ensure_bundle_exists' => true]);
});

/** How many nodes of the head match a selector, as the browser sees them after the app started. */
function headCount(string $selector): string
{
    return '() => document.head.querySelectorAll('.json_encode($selector, JSON_THROW_ON_ERROR).').length';
}

it('hydrates the landing server-rendered by Node without errors', function (bool $dark, bool $mobile): void {
    $pending = $mobile ? visit('/')->on()->mobile() : visit('/');
    $pending = $dark ? $pending->inDarkMode() : $pending->inLightMode();

    $page = $pending->assertNoJavaScriptErrors();

    $page->assertScript('() => document.querySelector("#app").hasAttribute("data-server-rendered")')
        // The page ends up in the state of the browser, not of the server: the theme the visitor sees.
        ->assertScript('() => document.documentElement.classList.contains("dark")', $dark)
        ->assertNoConsoleLogs()
        ->assertNoJavaScriptErrors();
})->with([
    'light desktop' => [false, false],
    'light mobile' => [false, true],
    'dark desktop' => [true, false],
    'dark mobile' => [true, true],
]);

it('keeps one title, description, canonical and JSON-LD after hydrating', function (): void {
    visit('/')->inLightMode()
        ->assertNoJavaScriptErrors()
        ->assertScript(headCount('title'), 1)
        ->assertScript(headCount('meta[name="description"]'), 1)
        ->assertScript(headCount('link[rel="canonical"]'), 1)
        ->assertScript(headCount('script[type="application/ld+json"]'), 1);
});

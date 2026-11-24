<?php

declare(strict_types=1);

use Pest\Browser\Api\AwaitableWebpage;

/*
 * The installed app's browser tests share these: the shell the service worker keeps, and the worker itself. The loss of the
 * network is simulated inside the page (fetch made to fail), never with the browser's own offline mode.
 */

/**
 * Opens the shell of the installed app (`/me/acme/offline`, with the network) and checks it is healthy as any screen is: no
 * JavaScript errors, no CSP violations, no English left, axe clean. It is not an Inertia page, so it has no component to
 * assert: the root of its own bundle (`#offline-app`) is what must be there.
 */
function visitShell(string $url, bool $dark = false, bool $mobile = false): AwaitableWebpage
{
    $pending = $mobile ? visit($url)->on()->mobile() : visit($url);
    $pending = $dark ? $pending->inDarkMode() : $pending->inLightMode();
    $page = $pending->assertNoJavaScriptErrors();

    $page->assertScript(eventually('() => document.querySelector("#offline-app h1") !== null'), true);

    return pageIsHealthy($page, $mobile);
}

/** A script that waits until a service worker controls the page. */
function workerReady(): string
{
    return eventually('() => navigator.serviceWorker.controller !== null');
}

/**
 * Like `eventually`, for a condition that answers with a promise (the Cache API and the registrations are asynchronous):
 * polls it until it holds, up to 10 s, and resolves with its last answer.
 */
function eventuallyAsync(string $condition): string
{
    $source = json_encode($condition, JSON_THROW_ON_ERROR);

    return <<<JS
        () => new Promise((resolve) => {
            const condition = new Function('return (' + {$source} + ')')();
            const start = performance.now();
            const check = async () => {
                let holds = false;
                try { holds = Boolean(await condition()); } catch (error) { holds = false; }
                holds || performance.now() - start > 10000 ? resolve(holds) : setTimeout(check, 100);
            };
            check();
        })
        JS;
}

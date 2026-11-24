<?php

declare(strict_types=1);

use App\Modules\Attendance\Support\QrImage;
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

/**
 * Makes the phone's camera show `$text` as a QR: the page's `getUserMedia` answers a stream drawn from the image of that
 * code (`img-src` allows `data:`), kept in `window.__camera` so a test can assert its tracks ended when the camera closed.
 * Call it before the camera opens. The address of the page is the origin the code should carry: use `originOf`.
 */
function withCameraShowing(AwaitableWebpage $page, string $text): void
{
    $image = json_encode(QrImage::dataUri($text), JSON_THROW_ON_ERROR);

    $page->script(runOnce(<<<JS
        () => new Promise((resolve) => {
            const picture = new Image();
            picture.onload = () => {
                const canvas = document.createElement('canvas');
                // A phone held upright: a tall frame with the code in the middle, whole inside what the video shows.
                canvas.width = 360;
                canvas.height = 780;
                const context = canvas.getContext('2d');
                const draw = () => {
                    context.fillStyle = '#fff';
                    context.fillRect(0, 0, 360, 780);
                    context.drawImage(picture, 20, 230, 320, 320);
                };
                draw();
                setInterval(draw, 66);
                navigator.mediaDevices.getUserMedia = async () => (window.__camera = canvas.captureStream(15));
                resolve(true);
            };
            picture.src = {$image};
        })
        JS));
}

/** `http://localhost:1234`: the origin the browser has the app at, for the address a QR carries. */
function originOf(AwaitableWebpage $page): string
{
    return parse_url($page->url(), PHP_URL_SCHEME).'://'.parse_url($page->url(), PHP_URL_HOST).':'.parse_url($page->url(), PHP_URL_PORT);
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

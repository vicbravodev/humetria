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

/** Makes the phone's camera show a plain wall, with no code in it: the page's `getUserMedia` answers a stream of a white frame. */
function withCameraShowingNoCode(AwaitableWebpage $page): void
{
    $page->script(runOnce(<<<'JS'
        () => {
            const canvas = document.createElement('canvas');
            canvas.width = 360;
            canvas.height = 780;
            const context = canvas.getContext('2d');
            const draw = () => { context.fillStyle = '#fff'; context.fillRect(0, 0, 360, 780); };
            draw();
            setInterval(draw, 66);
            navigator.mediaDevices.getUserMedia = async () => (window.__camera = canvas.captureStream(15));
            return true;
        }
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

/**
 * Takes the network away from the page (or gives it back): `fetch` is made to fail as it does with no signal, `navigator.onLine`
 * says false, and the page is told with the `offline` event. It holds across a reload of the page (a flag in `sessionStorage`
 * and a script every document runs first), so a test can reload «with the network down» and see what the phone kept. Giving it
 * back restores `fetch`, says online and fires the `online` event, as the phone's browser does on its own; with
 * `$deliveryStatus` the network comes back but the server answers the delivery of the queue with that status (a 500, say), until
 * it is given back again without one. Browser tests never use the browser's own offline mode: the virtual fingerprint reader
 * signs locally either way.
 */
function withoutNetwork(AwaitableWebpage $page, bool $off = true, ?int $deliveryStatus = null): void
{
    $install = <<<'JS'
        if (window.__realFetch === undefined) { window.__realFetch = window.fetch.bind(window); }
        window.fetch = () => Promise.reject(new TypeError('Failed to fetch'));
        Object.defineProperty(navigator, 'onLine', { get: () => false, configurable: true });
        JS;
    // The script every document of this page runs first. It is idempotent (the real `fetch` is kept once), so asking for it on every
    // call is harmless; keeping a record of which pages have it would trust an object id that PHP reuses.
    if ($off) {
        pestInternal($page, 'page')->context()->addInitScript("try { if (sessionStorage.getItem('pwa-test-offline') === '1') { {$install} } } catch (error) {}");
    }

    if ($off) {
        $page->script("() => { sessionStorage.setItem('pwa-test-offline', '1'); {$install} window.dispatchEvent(new Event('offline')); }");

        return;
    }

    $answering = $deliveryStatus === null
        ? 'window.fetch = window.__realFetch;'
        : "window.fetch = (url, init) => String(url).endsWith('/punch/offline') ? Promise.resolve(new Response('{}', { status: {$deliveryStatus} })) : window.__realFetch(url, init);";

    $page->script("() => { sessionStorage.removeItem('pwa-test-offline'); if (window.__realFetch !== undefined) { {$answering} } delete navigator.onLine; window.dispatchEvent(new Event('online')); }");
}

/** What the phone keeps for the company `acme`, as the names of the records in its IndexedDB (marks are `mark/{seq}`). */
function keptInPhone(): string
{
    return <<<'JS'
        () => new Promise((resolve) => {
            const open = indexedDB.open('humetria-me-acme', 1);
            open.onerror = () => resolve('none');
            open.onsuccess = () => {
                const request = open.result.transaction('kv').objectStore('kv').getAllKeys();
                request.onsuccess = () => resolve(request.result.sort().join(','));
            };
        })
        JS;
}

/** Runs `$change(store, done)` in the phone's IndexedDB of the company `acme`, inside one read-write transaction. */
function changeInPhone(AwaitableWebpage $page, string $change): void
{
    $page->script("() => new Promise((resolve) => {
        const open = indexedDB.open('humetria-me-acme', 1);
        open.onsuccess = () => {
            const transaction = open.result.transaction('kv', 'readwrite');
            ({$change})(transaction.objectStore('kv'));
            transaction.oncomplete = () => resolve(true);
        };
    })");
}

/** The phone keeps only this many of its tickets (the newest first): to see «Pocos boletos» or «Aún no está listo». */
function keepTickets(AwaitableWebpage $page, int $count): void
{
    changeInPhone($page, "(store) => {
        const read = store.get('tickets');
        read.onsuccess = () => store.put(read.result.slice(0, {$count}), 'tickets');
    }");
}

/**
 * Puts a mark of `$markOf` waiting in the phone's queue (one the server would not take: it is only for the screen to count),
 * with the phone's context naming `$contextOf` as the person on the screen, and tells the page the storage changed. The
 * database of the phone is created if the page has not opened it yet.
 */
function queueMarkInPhone(AwaitableWebpage $page, string $contextOf, string $markOf): void
{
    $context = json_encode(['tenantId' => 't', 'employeeId' => $contextOf, 'centerId' => 'c', 'agreementVersion' => 1, 'rpId' => 'localhost', 'offline' => true], JSON_THROW_ON_ERROR);
    $mark = json_encode([
        'schema' => 1, 'seq' => 1, 'employeeId' => $markOf, 'createdAt' => 1, 'state' => 'queued', 'reason' => null,
        'item' => ['seq' => 1, 'type' => 'start', 'ticket' => 'v1.fake.mac', 'center_id' => 'c', 'declared_at' => 1, 'agreement_version' => 1, 'signer' => 'device_key'],
    ], JSON_THROW_ON_ERROR);

    changeInPhone($page, "(store) => { store.put({$context}, 'context'); store.put({$mark}, 'mark/1'); }");
    $page->script("() => window.dispatchEvent(new Event('humetria:offline-store'))");
}

/** Damages a mark that waits in the phone so the server cannot read it: a latitude out of the world. */
function damageMark(AwaitableWebpage $page, int $seq): void
{
    changeInPhone($page, "(store) => {
        const read = store.get('mark/{$seq}');
        read.onsuccess = () => { read.result.item.lat_e5 = 99999999; store.put(read.result, 'mark/{$seq}'); };
    }");
}

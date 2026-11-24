<?php

declare(strict_types=1);

use App\Modules\Tenancy\Contracts\CompanyModules;
use App\Modules\Tenancy\Models\Tenant;
use Carbon\CarbonImmutable;
use Pest\Browser\Playwright\Playwright;

/*
 * The installed app of a company: its shell and its service worker. The cold start with no network at all (the worker
 * answering the shell) is checked in real phones, not here: these tests cover what can be seen with the network up.
 */

beforeEach(function (): void {
    // Service workers need a secure origin: localhost is one, an address like 127.0.0.1 is not for the browser's checks.
    Playwright::setHost('localhost');
    $this->tenant = Tenant::factory()->create(['trade_name' => 'Acme', 'slug' => 'acme']);
    workIn($this->tenant);
    resolve(CompanyModules::class)->syncPlan($this->tenant->id, ['attendance'], CarbonImmutable::now(config('app.display_timezone')));
});

afterEach(function (): void {
    Playwright::setHost(null);
});

/** A condition on the cache of the company `acme`, whatever its revision. */
function acmeCache(string $then): string
{
    return "async () => {
        const name = (await caches.keys()).find((key) => key.startsWith('humetria-pwa-acme-'));
        if (name === undefined) { return false; }
        const urls = (await (await caches.open(name)).keys()).map((request) => new URL(request.url).pathname);
        return {$then};
    }";
}

it('opens the shell healthy, light and dark, on a desktop and on a phone', function (bool $dark, bool $mobile): void {
    visitShell('/me/acme/offline', $dark, $mobile)
        ->assertSee('No hay internet')
        ->assertSee('Reintentar')
        ->assertPresent('a[href="/me/acme"]');
})->with([
    'light, desktop' => [false, false],
    'dark, desktop' => [true, false],
    'light, phone' => [false, true],
    'dark, phone' => [true, true],
]);

it('puts a worker in charge of the company\'s pages that keeps the shell and its files', function (): void {
    $page = visit('/me/acme/punch')->assertNoJavaScriptErrors();

    $page->assertScript(workerReady(), true)
        ->assertScript(eventuallyAsync(acmeCache("urls.includes('/me/acme/offline') && urls.some((url) => url.startsWith('/build/assets/'))")), true)
        ->assertScript(eventuallyAsync("async () => (await navigator.serviceWorker.getRegistration('/me/acme'))?.scope === location.origin + '/me/acme'"), true);
});

it('keeps answering what the server says: a 404 with the network up is still a 404, never the shell', function (): void {
    $page = visit('/me/acme/punch');
    $page->assertScript(workerReady(), true);

    $page->navigate('/me/acme/no-existe')
        ->assertScript(eventually('() => performance.getEntriesByType("navigation")[0].responseStatus === 404'), true)
        ->assertScript('() => document.querySelector("#offline-app") === null', true);
});

it('switched off, takes the worker and the caches out of the phone and does not come back', function (): void {
    $page = visit('/me/acme/punch');
    $page->assertScript(workerReady(), true)
        ->assertScript(eventuallyAsync(acmeCache('true')), true);

    config(['worker_access.pwa_sw_kill' => true]);

    $page->navigate('/me/acme/punch')
        ->assertScript(eventuallyAsync('async () => (await navigator.serviceWorker.getRegistrations()).length === 0'), true)
        ->assertScript(eventuallyAsync("async () => !(await caches.keys()).some((key) => key.startsWith('humetria-pwa-acme-'))"), true);

    // Another visit does not register it again.
    $page->navigate('/me/acme')
        ->assertScript('() => new Promise((resolve) => setTimeout(resolve, 1500)).then(() => navigator.serviceWorker.getRegistrations()).then((registrations) => registrations.length)', 0);
});

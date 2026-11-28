<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;

/*
 * What Node receives is a contract: the page object of a visitor, nothing else. A shared prop added tomorrow reaches Node
 * (a long-lived process, outside the app) unnoticed unless this test breaks and forces a decision; the golden file
 * tests/Ssr/fixtures/page-keys.json is where the decision is written down.
 */

beforeEach(function (): void {
    config(['inertia.ssr.enabled' => true, 'inertia.ssr.ensure_bundle_exists' => false]);
    Http::fake(['http://127.0.0.1:13714/render' => Http::response(['head' => [], 'body' => '<div></div>'])]);
});

function pageKeysGolden(): array
{
    return json_decode((string) file_get_contents(base_path('tests/Ssr/fixtures/page-keys.json')), true, flags: JSON_THROW_ON_ERROR);
}

/** The one request that went to Node. */
function requestToNode(): ClientRequest
{
    $sent = Http::recorded(fn (ClientRequest $request): bool => str_ends_with($request->url(), '/render'));

    expect($sent)->toHaveCount(1);

    return $sent->first()[0];
}

/** @return list<string> */
function sortedKeys(mixed $value): array
{
    $keys = array_map('strval', array_keys(is_array($value) ? $value : []));
    sort($keys);

    return $keys;
}

it('sends Node exactly the keys of the golden file, for each page', function (string $url, string $component): void {
    $this->get($url)->assertOk();

    $page = requestToNode()->data();
    $golden = pageKeysGolden();
    $expectedProps = [...$golden['props'], ...$golden['pageProps'][$component]];
    sort($expectedProps);

    expect($page['component'])->toBe($component)
        ->and(sortedKeys($page))->toBe(collect($golden['page'])->sort()->values()->all())
        ->and(sortedKeys($page['props']))->toBe($expectedProps);
})->with([['/', 'landing'], ['/privacy-notice', 'privacy-notice']]);

it('sends nobody: the user is null and nothing identifies a person', function (): void {
    $this->get('/')->assertOk();

    $props = requestToNode()->data()['props'];

    expect($props['auth'])->toBe(['user' => null, 'isSuperadmin' => false, 'isStaff' => false])
        ->and($props['impersonator'])->toBeNull()
        ->and($props['demoGuide'])->toBeNull()
        ->and($props['tenant'])->toBeNull()
        ->and($props['tenants'])->toBe([]);
});

it('forwards no cookie, token or credential to Node', function (): void {
    $this->withUnencryptedCookie('sidebar_state', 'false')
        ->withHeaders(['Authorization' => 'Bearer secret-token', 'X-XSRF-TOKEN' => 'secret-xsrf', 'X-CSRF-TOKEN' => 'secret-csrf'])
        ->get('/')
        ->assertOk();

    $headers = array_map('strtolower', array_keys(requestToNode()->headers()));

    expect($headers)->not->toContain('cookie')
        ->not->toContain('authorization')
        ->not->toContain('x-xsrf-token')
        ->not->toContain('x-csrf-token');
});

it('sends the path and query of the request as the page url', function (): void {
    $this->get('/?utm_source=newsletter')->assertOk();

    expect(requestToNode()->data()['url'])->toBe('/?utm_source=newsletter');
});

it('sends Node nothing of the analytics configuration: no token, no consent, no script', function (): void {
    config(['services.posthog.project_token' => 'phc_AbCdEfGhIjKlMnOpQrStUvWxYz0123456789ABCDEFG', 'services.posthog.consent_default' => 'granted']);

    $this->get('/')->assertOk();

    $sent = json_encode(requestToNode()->data());

    expect($sent)->not->toContain('phc_AbCdEfGhIjKlMnOpQrStUvWxYz0123456789ABCDEFG')
        ->not->toContain('posthog')
        ->not->toContain('analytics-config')
        ->not->toContain('consent');
});

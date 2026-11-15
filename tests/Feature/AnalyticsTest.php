<?php

declare(strict_types=1);

use App\Http\Analytics;
use App\Models\User;
use Illuminate\Http\Request;

mutates(Analytics::class);

const GA_ID = 'G-ABC123XYZ9';

/**
 * A request the router has already matched, as the middleware and the views see it.
 *
 * @param  array<string, string>  $server
 */
function matchedRequest(string $uri = '/', ?User $user = null, array $server = []): Request
{
    $request = Request::create($uri, server: $server);
    $route = app('router')->getRoutes()->match($request);
    $request->setRouteResolver(fn () => $route);
    $request->setUserResolver(fn (): ?User => $user);

    return $request;
}

function measuring(string $id = GA_ID, string $consent = 'granted'): void
{
    config(['services.google_analytics.measurement_id' => $id, 'services.google_analytics.consent_default' => $consent]);
}

it('measures the landing for a visitor without a session when there is an id and consent', function (): void {
    measuring();

    expect(Analytics::measurementId(matchedRequest()))->toBe(GA_ID);
});

it('measures nothing without an id, without consent, outside the landing or for someone signed in', function (Closure $setUp, string $uri, bool $signedIn): void {
    measuring();
    $setUp();

    expect(Analytics::measurementId(matchedRequest($uri, $signedIn ? User::factory()->create() : null)))->toBeNull();
})->with([
    'no id' => [fn () => measuring(''), '/', false],
    'consent denied' => [fn () => measuring(consent: 'denied'), '/', false],
    'another page' => [fn () => null, '/privacy-notice', false],
    'the login' => [fn () => null, '/admin/login', false],
    'a session' => [fn () => null, '/', true],
]);

it('accepts only a well-formed GA4 id', function (string $id, bool $valid): void {
    measuring($id);

    expect(Analytics::measurementId(matchedRequest()))->toBe($valid ? $id : null);
})->with([
    'ten characters' => ['G-ABC123XYZ9', true],
    'short' => ['G-A', true],
    'lowercase' => ['G-abc123', false],
    'universal analytics' => ['UA-123456-1', false],
    'only the prefix' => ['G-', false],
    'a space around' => [' G-ABC123', false],
    'a trailing newline' => ["G-ABC123\n", false],
    'markup' => ['G-ABC"></script><script>x', false],
    'a query' => ['G-ABC123&x=1', false],
]);

it('gives Google only the origin of a referrer from this very site, and nothing it can read by itself', function (?string $referer, ?string $expected): void {
    config(['app.url' => 'https://humetria.com.mx']);
    $server = $referer === null ? [] : ['HTTP_REFERER' => $referer];

    expect(Analytics::referrer(matchedRequest('/', server: $server + ['HTTP_HOST' => 'humetria.com.mx'])))->toBe($expected);
})->with([
    'none' => [null, null],
    'empty' => ['', null],
    'external' => ['https://www.google.com/search?q=humetria', null],
    'own page with a token' => ['https://humetria.com.mx/encuesta/abc123token?x=1#y', 'https://humetria.com.mx/'],
    'own www variant' => ['https://www.humetria.com.mx/admin/secret', 'https://www.humetria.com.mx/'],
    'own host in capitals' => ['https://HUMETRIA.com.mx/reporte/token', 'https://humetria.com.mx/'],
    'own host with a port' => ['https://humetria.com.mx:8443/x', 'https://humetria.com.mx:8443/'],
    'another subdomain' => ['https://app.humetria.com.mx/x', null],
    'a lookalike host' => ['https://otrohumetria.com.mx/x', null],
    'a host that ends in ours' => ['https://humetria.com.mx.evil.example/x', null],
    'not a url' => ['humetria', null],
    'a script scheme' => ['javascript:alert(1)', null],
]);

it('tells Google the page without its personal query: only the utm parameters stay', function (string $uri, string $expected): void {
    expect(Analytics::pageLocation(matchedRequest($uri)))->toBe($expected);
})->with([
    'plain' => ['/', 'http://localhost/'],
    'a token that is not a campaign' => ['/?token=secret&email=a@b.mx', 'http://localhost/'],
    'campaign parameters' => ['/?utm_source=google&utm_medium=cpc&utm_campaign=nom-035&fbclid=x', 'http://localhost/?utm_source=google&utm_medium=cpc&utm_campaign=nom-035'],
    'an array value' => ['/?utm_source[]=a', 'http://localhost/'],
    'an empty value' => ['/?utm_source=', 'http://localhost/'],
    'a value of 101 characters' => ['/?utm_source='.str_repeat('a', 101), 'http://localhost/'],
    'a value of 100 characters' => ['/?utm_source='.str_repeat('a', 100), 'http://localhost/?utm_source='.str_repeat('a', 100)],
    'the click id of an ad' => ['/?gclid=abc&utm_term=empresa', 'http://localhost/?utm_term=empresa'],
]);

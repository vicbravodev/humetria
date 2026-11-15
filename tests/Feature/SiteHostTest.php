<?php

declare(strict_types=1);

use App\Http\SiteHost;
use Illuminate\Http\Request;

mutates(SiteHost::class);

function siteRequest(string $host = 'humetria.com.mx'): Request
{
    config(['app.url' => 'https://humetria.com.mx']);

    return Request::create("https://{$host}/");
}

it('says whether a url belongs to this site, with or without www', function (?string $url, bool $owned): void {
    expect(SiteHost::owns(siteRequest(), $url))->toBe($owned);
})->with([
    'own page' => ['https://humetria.com.mx/privacy-notice', true],
    'own page with a token' => ['https://humetria.com.mx/encuesta/abc123token?x=1#y', true],
    'www variant' => ['https://www.humetria.com.mx/admin', true],
    'capitals' => ['https://HUMETRIA.com.mx/x', true],
    'a port' => ['https://humetria.com.mx:8443/x', true],
    'plain http' => ['http://humetria.com.mx/', true],
    'another subdomain' => ['https://app.humetria.com.mx/x', false],
    'a lookalike' => ['https://otrohumetria.com.mx/x', false],
    'ours as a prefix of theirs' => ['https://humetria.com.mx.evil.example/x', false],
    'external' => ['https://www.google.com/search?q=x', false],
    'null' => [null, false],
    'empty' => ['', false],
    'not a url' => ['humetria', false],
    'a script scheme' => ['javascript://humetria.com.mx/%0Aalert(1)', false],
]);

it('knows the host of the request and the configured one as its own', function (): void {
    $request = siteRequest('localhost');

    expect(SiteHost::owns($request, 'http://localhost/x'))->toBeTrue()
        ->and(SiteHost::owns($request, 'https://www.humetria.com.mx/x'))->toBeTrue()
        ->and(SiteHost::owns($request, 'https://example.org/'))->toBeFalse();
});

it('reads the lowercase host of an http or https url', function (?string $url, ?string $host): void {
    expect(SiteHost::hostOf($url))->toBe($host);
})->with([
    'https' => ['https://Www.Google.com/search?q=x', 'www.google.com'],
    'with a port' => ['https://example.org:8443/x', 'example.org'],
    'with credentials' => ['https://user:pass@example.org/x', 'example.org'],
    'null' => [null, null],
    'empty' => ['', null],
    'not a url' => ['example.org', null],
    'a mailto' => ['mailto:a@example.org', null],
    'ftp' => ['ftp://example.org/', null],
    'no host' => ['https:///path', null],
]);

it('writes a url as its origin: scheme, lowercase host and port, nothing of the path, the query or the token', function (?string $url, ?string $origin): void {
    expect(SiteHost::originOf($url))->toBe($origin);
})->with([
    'a page with a token' => ['https://humetria.com.mx/encuesta/abc123token?x=1#y', 'https://humetria.com.mx/'],
    'capitals' => ['https://HUMETRIA.com.mx/x', 'https://humetria.com.mx/'],
    'a port' => ['http://humetria.com.mx:8443/x', 'http://humetria.com.mx:8443/'],
    'not a url' => ['humetria', null],
    'null' => [null, null],
]);

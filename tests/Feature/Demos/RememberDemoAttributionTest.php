<?php

declare(strict_types=1);

use App\Modules\Demos\Http\Middleware\RememberDemoAttribution;

mutates(RememberDemoAttribution::class);

/** What the session remembers of the first visit with a signal; null when there is none. */
function rememberedTouch(): mixed
{
    return session('demo_attribution');
}

it('asks for no consent by default: the owner has to grant it', function (): void {
    expect(config('services.google_analytics.consent_default'))->toBe('denied');
});

it('remembers in the session the utm of the first visit to the landing that has any', function (): void {
    $this->get('/?utm_source=anuncios&utm_medium=pago&utm_campaign=nom-035')->assertOk();

    expect(rememberedTouch())->toBe(['source' => 'anuncios', 'medium' => 'pago', 'campaign' => 'nom-035', 'referrer_host' => null]);
});

it('keeps the first touch: a later visit with other utm does not replace it', function (): void {
    $this->get('/?utm_source=primero')->assertOk();
    $this->get('/?utm_source=segundo&utm_medium=otro')->assertOk();

    expect(rememberedTouch())->toBe(['source' => 'primero', 'medium' => null, 'campaign' => null, 'referrer_host' => null]);
});

it('remembers only the host of an external referrer, in lowercase, never the URL', function (): void {
    $this->withHeaders(['Referer' => 'https://www.Google.com/search?q=evaluacion+nom-035&token=secreto'])->get('/')->assertOk();

    expect(rememberedTouch())->toBe(['source' => null, 'medium' => null, 'campaign' => null, 'referrer_host' => 'www.google.com'])
        ->and(json_encode(rememberedTouch()))->not->toContain('secreto')->not->toContain('search');
});

it('keeps a utm and the referrer host of the same visit together', function (): void {
    $this->withHeaders(['Referer' => 'https://www.linkedin.com/feed/'])->get('/?utm_source=boletin')->assertOk();

    expect(rememberedTouch())->toBe(['source' => 'boletin', 'medium' => null, 'campaign' => null, 'referrer_host' => 'www.linkedin.com']);
});

it('remembers nothing from a referrer that is this very site, with or without www, or a link with a token', function (string $referer): void {
    config(['app.url' => 'https://humetria.com.mx']);

    $this->withHeaders(['Referer' => $referer])->get('/')->assertOk();

    expect(rememberedTouch())->toBeNull();
})->with([
    'the request host' => ['http://localhost/privacy-notice'],
    'the request host with www' => ['http://www.localhost/'],
    'the configured host' => ['https://humetria.com.mx/'],
    'the configured host with www' => ['https://www.humetria.com.mx/'],
    'capitals' => ['https://HUMETRIA.com.mx/x'],
    'a link with a token' => ['https://humetria.com.mx/encuesta/abc123token?x=1#y'],
]);

it('remembers nothing from a referrer that is not an http URL, or has a host that cannot be one', function (string $referer): void {
    $this->withHeaders(['Referer' => $referer])->get('/')->assertOk();

    expect(rememberedTouch())->toBeNull();
})->with([
    'plain text' => ['humetria'],
    'a script' => ['javascript:alert(1)'],
    'an app link' => ['android-app://com.google.android.gm'],
    'a host with a space' => ['https://exa mple.com/x'],
    'a host with markup' => ['https://exa<script>mple.com/x'],
    'no host' => ['https:///path'],
]);

it('remembers nothing of a visit with no signal: that is not direct traffic, it is no data', function (): void {
    $this->get('/')->assertOk();
    $this->get('/?q=nada&fbclid=abc')->assertOk();

    expect(session()->has('demo_attribution'))->toBeFalse();
});

it('accepts a utm of 100 characters and drops one of 101', function (string $value, ?string $kept): void {
    $this->get('/?'.http_build_query(['utm_source' => $value, 'utm_medium' => 'ok']))->assertOk();

    expect(rememberedTouch())->toMatchArray(['source' => $kept, 'medium' => 'ok']);
})->with([
    '100 characters' => [str_repeat('a', 100), str_repeat('a', 100)],
    '101 characters' => [str_repeat('a', 101), null],
    '100 multibyte characters' => [str_repeat('ñ', 100), str_repeat('ñ', 100)],
    '101 multibyte characters' => [str_repeat('ñ', 101), null],
    'padded with spaces' => ['  anuncios  ', 'anuncios'],
    '100 characters once trimmed' => [' '.str_repeat('a', 100).' ', str_repeat('a', 100)],
    'empty' => ['', null],
    'only spaces' => ['    ', null],
    'a line break in the middle' => ["a\nb", null],
    'a tab in the middle' => ["a\tb", null],
    'a control character' => ["a\x07b", null],
    'a null byte' => ["a\0b", null],
    'a trailing line break is trimmed' => ["anuncios\n", 'anuncios'],
]);

it('drops a utm that is not valid UTF-8, or arrives as an array', function (string $query): void {
    $this->get("/?{$query}&utm_medium=ok")->assertOk();

    expect(rememberedTouch())->toBe(['source' => null, 'medium' => 'ok', 'campaign' => null, 'referrer_host' => null]);
})->with([
    'invalid UTF-8' => ['utm_source=%FF%FE'],
    'an array' => ['utm_source[]=a&utm_source[]=b'],
    'a keyed array' => ['utm_source[x]=a'],
]);

it('accepts a referrer host of 255 characters and drops one of 256', function (): void {
    $host = fn (int $last): string => implode('.', [str_repeat('a', 63), str_repeat('b', 63), str_repeat('c', 63), str_repeat('d', $last)]);

    $this->withHeaders(['Referer' => 'https://'.$host(63).'/x'])->get('/')->assertOk();
    expect(strlen($host(63)))->toBe(255)
        ->and(rememberedTouch())->toMatchArray(['referrer_host' => $host(63)]);

    $this->flushSession();
    $this->withHeaders(['Referer' => 'https://'.$host(64).'/x'])->get('/')->assertOk();
    expect(strlen($host(64)))->toBe(256)
        ->and(session()->has('demo_attribution'))->toBeFalse();
});

it('never keeps the click ids of Google Ads, in the session or anywhere else', function (): void {
    $this->get('/?utm_source=anuncios&gclid=CLICK123&gbraid=BRAID456&wbraid=WBRAID789')->assertOk();

    expect(json_encode(rememberedTouch()))->not->toContain('CLICK123')->not->toContain('BRAID456')->not->toContain('WBRAID789')
        ->and(array_keys((array) rememberedTouch()))->toBe(['source', 'medium', 'campaign', 'referrer_host']);

    $this->flushSession();
    $this->get('/?gclid=CLICK123')->assertOk();

    expect(session()->has('demo_attribution'))->toBeFalse();
});

it('only looks at GET requests: a HEAD leaves nothing behind', function (): void {
    $this->call('HEAD', '/?utm_source=anuncios')->assertOk();

    expect(session()->has('demo_attribution'))->toBeFalse();
});

it('only runs on the landing, never on another page or one with a token', function (string $uri): void {
    $this->get("{$uri}?utm_source=anuncios");

    expect(session()->has('demo_attribution'))->toBeFalse();
})->with([
    'the notice of privacy' => ['/privacy-notice'],
    'the login' => ['/login'],
    'a page with a token' => ['/encuesta/token-que-no-existe'],
]);

it('sets no cookie of its own while there is no consent', function (): void {
    $this->get('/?utm_source=anuncios')->assertCookieMissing('humetria_acq');
});

<?php

declare(strict_types=1);

use App\Modules\Demos\Enums\AdClickType;
use App\Modules\Demos\Http\Middleware\RememberDemoAttribution;
use App\Modules\Demos\Models\DemoRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

mutates(RememberDemoAttribution::class);

beforeEach(function (): void {
    $this->logPath = storage_path('logs/demo-attribution-'.Str::uuid().'.log');
    config(['logging.channels.single.path' => $this->logPath]);
    Log::forgetChannel('single');
    Log::forgetChannel('stack');
});

afterEach(function (): void {
    File::delete($this->logPath);
    Carbon::setTestNow();
});

/** What the session remembers of the first visit with a signal; null when there is none. */
function rememberedTouch(): mixed
{
    return session('demo_attribution');
}

it('asks for no consent by default: the owner has to grant it', function (): void {
    expect(config('services.posthog.consent_default'))->toBe('denied');
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

it('keeps no click id of Google Ads while consent is denied, and keeps the touch as before', function (): void {
    $this->get('/?utm_source=anuncios&gclid=CLICK123&gbraid=BRAID456&wbraid=WBRAID789')->assertOk();

    expect(session()->has(RememberDemoAttribution::CLICK_KEY))->toBeFalse()
        ->and(rememberedTouch())->toBe(touchOf('anuncios'));
});

it('remembers in the session the click id of the last ad click, once consent is granted', function (): void {
    consentGranted();

    $this->get('/?utm_source=google&gclid=PRIMERO')->assertOk();
    $this->get('/?utm_source=google&gclid=Cj0KCQ_ultimo-1')->assertOk();

    expect(session(RememberDemoAttribution::CLICK_KEY))->toBe(['type' => 'gclid', 'id' => 'Cj0KCQ_ultimo-1']);
});

it('keeps the last click when a later visit brings none', function (): void {
    consentGranted();

    $this->get('/?gclid=CLICK123')->assertOk();
    $this->get('/?utm_source=boletin')->assertOk();

    expect(session(RememberDemoAttribution::CLICK_KEY))->toBe(['type' => 'gclid', 'id' => 'CLICK123']);
});

it('prefers the gclid, then the gbraid, then the wbraid of the same visit', function (string $query, array $click): void {
    consentGranted();

    $this->get("/?{$query}")->assertOk();

    expect(session(RememberDemoAttribution::CLICK_KEY))->toBe($click);
})->with([
    'all three' => ['gclid=G1&gbraid=B1&wbraid=W1', ['type' => 'gclid', 'id' => 'G1']],
    'gbraid and wbraid' => ['gbraid=B1&wbraid=W1', ['type' => 'gbraid', 'id' => 'B1']],
    'only wbraid' => ['wbraid=W1', ['type' => 'wbraid', 'id' => 'W1']],
    'a malformed gclid falls to the gbraid' => ['gclid=no%20vale&gbraid=B1', ['type' => 'gbraid', 'id' => 'B1']],
]);

it('accepts a click id of 255 characters and drops one of 256, an empty one or one with other characters', function (string $id, bool $kept): void {
    consentGranted();

    $this->get('/?gclid='.urlencode($id))->assertOk();

    expect(session()->has(RememberDemoAttribution::CLICK_KEY))->toBe($kept);
})->with([
    '255 characters' => [str_repeat('a', 255), true],
    '256 characters' => [str_repeat('a', 256), false],
    'empty' => ['', false],
    'a space' => ['abc def', false],
    'a slash' => ['abc/def', false],
    'letters, digits, dash and underscore' => ['Cj0KCQiA-_09', true],
]);

it('drops a click id that arrives as an array', function (): void {
    consentGranted();

    $this->get('/?gclid[]=CLICK123')->assertOk();

    expect(session()->has(RememberDemoAttribution::CLICK_KEY))->toBeFalse();
});

it('remembers the keyword of the last visit that brings one: term, content and match type', function (): void {
    $this->get('/?utm_source=google&utm_term=checador&utm_content=111&matchtype=e')->assertOk();
    $this->get('/?utm_source=google&utm_term=reloj+checador&matchtype=p')->assertOk();
    $this->get('/?utm_source=boletin')->assertOk();

    expect(session(RememberDemoAttribution::KEYWORD_KEY))->toBe(['term' => 'reloj checador', 'content' => null, 'match_type' => 'p']);
});

it('drops a keyword value that is not a short line of text', function (string $query, ?array $kept): void {
    $this->get("/?{$query}")->assertOk();

    expect(session(RememberDemoAttribution::KEYWORD_KEY))->toBe($kept);
})->with([
    'a term of 100 characters' => ['utm_term='.str_repeat('a', 100), ['term' => str_repeat('a', 100), 'content' => null, 'match_type' => null]],
    'a term of 101 characters' => ['utm_term='.str_repeat('a', 101), null],
    'a match type of 20 characters' => ['matchtype='.str_repeat('b', 20), ['term' => null, 'content' => null, 'match_type' => str_repeat('b', 20)]],
    'a match type of 21 characters' => ['matchtype='.str_repeat('b', 21), null],
    'a term as an array' => ['utm_term[]=a', null],
    'a control character' => ['utm_content=a%0Ab', null],
]);

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

const NINETY_DAYS = 90 * 86_400;

/** The owner granted consent: only then the long cookie is written. */
function consentGranted(): void
{
    config(['services.posthog.consent_default' => 'granted']);
}

/** @return array<string, mixed> */
function acquisition(TestResponse $response): array
{
    $value = $response->getCookie('humetria_acq');
    expect($value)->not->toBeNull();

    return (array) json_decode((string) $value->getValue(), true, flags: JSON_THROW_ON_ERROR);
}

/**
 * @param  array<string, ?string>  $first
 * @param  array<string, ?string>  $last
 */
function acquisitionCookie(array $first, array $last, int $seenAt): string
{
    return json_encode(['first' => $first, 'last' => $last, 'first_seen_at' => $seenAt], JSON_THROW_ON_ERROR);
}

/** @return array<string, ?string> */
function touchOf(?string $source = null, ?string $medium = null, ?string $campaign = null, ?string $host = null): array
{
    return ['source' => $source, 'medium' => $medium, 'campaign' => $campaign, 'referrer_host' => $host];
}

it('writes the 90-day cookie with the first and the last touch once consent is granted', function (): void {
    consentGranted();
    Carbon::setTestNow('2026-11-14 12:00:00');

    $response = $this->get('/?utm_source=anuncios&utm_medium=pago&utm_campaign=nom-035')->assertOk();

    expect(acquisition($response))->toBe([
        'first' => touchOf('anuncios', 'pago', 'nom-035'),
        'last' => touchOf('anuncios', 'pago', 'nom-035'),
        'first_seen_at' => Carbon::now()->getTimestamp(),
    ]);
});

it('writes it encrypted, HttpOnly, Secure, SameSite=Lax and for exactly 90 days', function (): void {
    consentGranted();
    Carbon::setTestNow('2026-11-14 12:00:00');

    $response = $this->get('/?utm_source=anuncios');
    $cookie = $response->getCookie('humetria_acq', false);

    expect($cookie)->not->toBeNull()
        ->and($cookie->getValue())->not->toContain('anuncios')
        ->and($cookie->isHttpOnly())->toBeTrue()
        ->and($cookie->isSecure())->toBeTrue()
        ->and($cookie->getSameSite())->toBe('lax')
        ->and($cookie->getPath())->toBe('/')
        ->and($cookie->getExpiresTime())->toBe(Carbon::now()->getTimestamp() + NINETY_DAYS);
});

it('still remembers the session when the cookie is written', function (): void {
    consentGranted();

    $this->get('/?utm_source=anuncios')->assertOk();

    expect(rememberedTouch())->toMatchArray(['source' => 'anuncios']);
});

it('writes no cookie with consent denied, even for a visit with a signal', function (): void {
    $this->get('/?utm_source=anuncios&utm_medium=pago')->assertCookieMissing('humetria_acq');
    $this->withHeaders(['Referer' => 'https://www.google.com/'])->get('/')->assertCookieMissing('humetria_acq');
});

it('writes no cookie for a visit with no signal, with or without one before', function (): void {
    consentGranted();
    Carbon::setTestNow('2026-11-14 12:00:00');

    $this->get('/')->assertCookieMissing('humetria_acq');

    $existing = acquisitionCookie(touchOf('anuncios'), touchOf('anuncios'), Carbon::now()->subDays(3)->getTimestamp());
    $this->withCookie('humetria_acq', $existing)->get('/')->assertCookieMissing('humetria_acq');
});

it('keeps the first touch and the day it was first seen, and overwrites the last one only with a signal', function (): void {
    consentGranted();
    Carbon::setTestNow('2026-11-14 12:00:00');
    $seen = Carbon::now()->subDays(10)->getTimestamp();
    $before = acquisitionCookie(touchOf('anuncios', 'pago', 'nom-035'), touchOf('anuncios', 'pago', 'nom-035'), $seen);

    $response = $this->withCookie('humetria_acq', $before)->withHeaders(['Referer' => 'https://www.bing.com/search?q=x'])->get('/?utm_source=boletin');

    expect(acquisition($response))->toBe([
        'first' => touchOf('anuncios', 'pago', 'nom-035'),
        'last' => touchOf('boletin', host: 'www.bing.com'),
        'first_seen_at' => $seen,
    ]);
});

it('treats a cookie that is not what it wrote as no cookie at all', function (string $stored): void {
    consentGranted();
    Carbon::setTestNow('2026-11-14 12:00:00');

    $response = $this->withCookie('humetria_acq', $stored)->get('/?utm_source=nuevo');

    expect(acquisition($response))->toBe([
        'first' => touchOf('nuevo'),
        'last' => touchOf('nuevo'),
        'first_seen_at' => Carbon::now()->getTimestamp(),
    ]);
})->with(fn (): array => [
    'not JSON' => ['esto no es json'],
    'an empty string' => [''],
    'a JSON string' => ['"first"'],
    'a JSON number' => ['42'],
    'an empty list' => ['[]'],
    'no first_seen_at' => [json_encode(['first' => touchOf('viejo'), 'last' => touchOf('viejo')])],
    'first_seen_at as text' => [json_encode(['first' => touchOf('viejo'), 'last' => touchOf('viejo'), 'first_seen_at' => 'ayer'])],
    'first_seen_at as a float' => [json_encode(['first' => touchOf('viejo'), 'last' => touchOf('viejo'), 'first_seen_at' => 1.5])],
    'first_seen_at in the future' => [acquisitionCookie(touchOf('viejo'), touchOf('viejo'), Carbon::parse('2026-11-14 12:00:01')->getTimestamp())],
    'first as text' => [json_encode(['first' => 'viejo', 'last' => touchOf('viejo'), 'first_seen_at' => Carbon::parse('2026-11-10')->getTimestamp()])],
    'an empty first' => [acquisitionCookie(touchOf(), touchOf('viejo'), Carbon::parse('2026-11-10')->getTimestamp())],
    'a first of arrays' => [json_encode(['first' => ['source' => ['a']], 'last' => touchOf('viejo'), 'first_seen_at' => Carbon::parse('2026-11-10')->getTimestamp()])],
]);

it('ignores a cookie that was not encrypted by the application', function (): void {
    consentGranted();

    $response = $this->withUnencryptedCookie('humetria_acq', acquisitionCookie(touchOf('forjada'), touchOf('forjada'), now()->timestamp))->get('/?utm_source=nuevo');

    expect(acquisition($response)['first'])->toBe(touchOf('nuevo'));
});

it('writes no cookie when a broken one comes with a visit that has no signal', function (): void {
    consentGranted();

    $this->withCookie('humetria_acq', 'esto no es json')->get('/')->assertCookieMissing('humetria_acq');
});

it('keeps a cookie of exactly 90 days and starts again after one second more', function (int $age, string $first): void {
    consentGranted();
    Carbon::setTestNow('2026-11-14 12:00:00');
    $old = acquisitionCookie(touchOf('viejo'), touchOf('viejo'), Carbon::now()->getTimestamp() - $age);

    $response = $this->withCookie('humetria_acq', $old)->get('/?utm_source=nuevo');

    expect(acquisition($response)['first'])->toBe(touchOf($first))
        ->and(acquisition($response)['last'])->toBe(touchOf('nuevo'));
})->with([
    'a day short of expiring' => [NINETY_DAYS - 86_400, 'viejo'],
    'exactly 90 days' => [NINETY_DAYS, 'viejo'],
    'one second more' => [NINETY_DAYS + 1, 'nuevo'],
    'a year' => [365 * 86_400, 'nuevo'],
]);

it('starts a new first touch, with a new date, when the cookie expired', function (): void {
    consentGranted();
    Carbon::setTestNow('2026-11-14 12:00:00');
    $old = acquisitionCookie(touchOf('viejo'), touchOf('viejo'), Carbon::now()->getTimestamp() - NINETY_DAYS - 1);

    $response = $this->withCookie('humetria_acq', $old)->get('/?utm_source=nuevo');

    expect(acquisition($response)['first_seen_at'])->toBe(Carbon::now()->getTimestamp());
});

it('cleans every value of the cookie again before writing it back', function (): void {
    consentGranted();
    Carbon::setTestNow('2026-11-14 12:00:00');
    $dirty = acquisitionCookie(
        touchOf(str_repeat('a', 101), 'correo', "a\nb", 'Host Con Espacio'),
        touchOf('ok'),
        Carbon::now()->subDay()->getTimestamp(),
    );

    $response = $this->withCookie('humetria_acq', $dirty)->get('/?utm_source=nuevo');

    expect(acquisition($response)['first'])->toBe(touchOf(medium: 'correo'));
});

it('keeps the click id in the cookie too, and the last one wins', function (): void {
    consentGranted();
    $cookie = json_encode([...json_decode(acquisitionCookie(touchOf('boletin'), touchOf('boletin'), now()->subDay()->getTimestamp()), true), 'ad_click' => ['type' => 'gclid', 'id' => 'VIEJO']]);

    $response = $this->withCookie('humetria_acq', (string) $cookie)->get('/?utm_source=google&gbraid=NUEVO');

    expect(acquisition($response)['ad_click'])->toBe(['type' => 'gbraid', 'id' => 'NUEVO']);
});

it('carries the click id of the cookie forward when a visit brings none', function (): void {
    consentGranted();
    $cookie = json_encode([...json_decode(acquisitionCookie(touchOf('boletin'), touchOf('boletin'), now()->subDay()->getTimestamp()), true), 'ad_click' => ['type' => 'gclid', 'id' => 'VIEJO']]);

    $response = $this->withCookie('humetria_acq', (string) $cookie)->get('/?utm_source=otra');

    expect(acquisition($response)['ad_click'])->toBe(['type' => 'gclid', 'id' => 'VIEJO']);
});

it('sets the cookie only on the landing', function (): void {
    consentGranted();

    $this->get('/privacy-notice?utm_source=anuncios')->assertCookieMissing('humetria_acq');
    $this->call('HEAD', '/?utm_source=anuncios')->assertCookieMissing('humetria_acq');
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function attributedSubmission(array $overrides = []): array
{
    return [
        'name' => 'Ana Lopez Prueba',
        'email' => 'ana.prueba@empresa.mx',
        'whatsapp' => '55 1234 5678',
        'company' => 'Empresa Ejemplo',
        'profile' => 'company',
        'workforce' => '51_100',
        'tools' => ['nom_035'],
        'message' => null,
        'form_extra' => '',
        ...$overrides,
    ];
}

function storedRequest(): DemoRequest
{
    return DemoRequest::query()->sole();
}

it('stores on the request what the session remembered, as the campaign and as the first contact', function (): void {
    $this->get('/?utm_source=anuncios&utm_medium=pago&utm_campaign=nom-035')->assertOk();

    $this->post(route('demo-requests.store'), attributedSubmission())->assertRedirect();

    $demoRequest = storedRequest();
    expect($demoRequest->arrivedBy())->toBe('anuncios / pago · nom-035')
        ->and($demoRequest->first_utm_source)->toBe('anuncios')
        ->and($demoRequest->firstContact())->toBeNull();
});

it('stores the referrer host the session remembered, and nothing else of the visit', function (): void {
    $this->withHeaders(['Referer' => 'https://www.google.com/search?q=nom-035'])->get('/')->assertOk();

    $this->post(route('demo-requests.store'), attributedSubmission())->assertRedirect();

    expect(storedRequest()->arrivedBy())->toBe('www.google.com');
});

it('stores a request with no previous visit, or a visit with no signal, with no attribution', function (): void {
    $this->get('/')->assertOk();

    $this->post(route('demo-requests.store'), attributedSubmission())->assertRedirect();

    expect(storedRequest()->arrivedBy())->toBeNull()
        ->and(storedRequest()->firstContact())->toBeNull();
});

it('credits the last touch of the cookie and keeps its first one as the first contact, with consent granted', function (): void {
    consentGranted();
    $cookie = acquisitionCookie(touchOf('boletin', 'correo'), touchOf('anuncios', 'pago', 'nom-035'), now()->subDays(20)->getTimestamp());

    $this->withCookie('humetria_acq', $cookie)->post(route('demo-requests.store'), attributedSubmission())->assertRedirect();

    expect(storedRequest()->arrivedBy())->toBe('anuncios / pago · nom-035')
        ->and(storedRequest()->firstContact())->toBe('boletin / correo');
});

it('prefers the cookie to the session when both exist', function (): void {
    consentGranted();
    $cookie = acquisitionCookie(touchOf('desde-cookie'), touchOf('desde-cookie'), now()->subDay()->getTimestamp());

    $this->withSession(['demo_attribution' => touchOf('desde-sesion')])
        ->withCookie('humetria_acq', $cookie)
        ->post(route('demo-requests.store'), attributedSubmission())->assertRedirect();

    expect(storedRequest()->arrivedBy())->toBe('desde-cookie');
});

it('falls back to the session when the cookie says nothing usable', function (string $stored): void {
    consentGranted();

    $this->withSession(['demo_attribution' => touchOf('desde-sesion')])
        ->withCookie('humetria_acq', $stored)
        ->post(route('demo-requests.store'), attributedSubmission())->assertRedirect();

    expect(storedRequest()->arrivedBy())->toBe('desde-sesion');
})->with(fn (): array => [
    'broken' => ['esto no es json'],
    'expired' => [acquisitionCookie(touchOf('vencida'), touchOf('vencida'), now()->subDays(91)->getTimestamp())],
    'empty' => ['[]'],
]);

it('ignores a cookie it did not encrypt', function (): void {
    consentGranted();

    $this->withUnencryptedCookie('humetria_acq', acquisitionCookie(touchOf('forjada'), touchOf('forjada'), now()->getTimestamp()))
        ->post(route('demo-requests.store'), attributedSubmission())->assertRedirect();

    expect(storedRequest()->arrivedBy())->toBeNull();
});

it('does not read the cookie while consent is denied: only the session counts', function (): void {
    $cookie = acquisitionCookie(touchOf('desde-cookie'), touchOf('desde-cookie'), now()->subDay()->getTimestamp());

    $this->withCookie('humetria_acq', $cookie)->post(route('demo-requests.store'), attributedSubmission())->assertRedirect();

    expect(storedRequest()->arrivedBy())->toBeNull();
});

it('survives a session value that is not what was written, keeping only what is clean', function (mixed $stored, ?string $label): void {
    $this->withSession(['demo_attribution' => $stored])
        ->post(route('demo-requests.store'), attributedSubmission())->assertRedirect();

    expect(storedRequest()->arrivedBy())->toBe($label);
})->with([
    'a string' => ['anuncios', null],
    'a number' => [42, null],
    'an empty array' => [[], null],
    'a source of 101 characters' => [touchOf(str_repeat('a', 101), 'pago'), 'pago'],
    'a source of 100 characters' => [touchOf(str_repeat('a', 100)), str_repeat('a', 100)],
    'arrays as values' => [['source' => ['a'], 'medium' => ['b']], null],
    'a control character' => [touchOf("a\nb", 'pago'), 'pago'],
    'blank values' => [touchOf('   ', '', '  '), null],
    'a host with a space' => [touchOf(host: 'Host Con Espacio'), null],
    'a host in capitals' => [touchOf(host: 'WWW.Google.COM'), 'www.google.com'],
    'extra keys' => [[...touchOf('anuncios'), 'gclid' => 'CLICK123'], 'anuncios'],
]);

it('stores the click id of the last ad click on the request, with consent granted', function (): void {
    consentGranted();
    $this->get('/?utm_source=google&utm_medium=cpc&utm_campaign=checador&gclid=CLICK123')->assertOk();

    $this->post(route('demo-requests.store'), attributedSubmission())->assertRedirect();

    expect(storedRequest()->ad_click_type)->toBe(AdClickType::Gclid)
        ->and(storedRequest()->ad_click_id)->toBe('CLICK123');
});

it('stores the click id the cookie remembered when the session forgot it', function (): void {
    consentGranted();
    $cookie = json_encode([...json_decode(acquisitionCookie(touchOf('google'), touchOf('google'), now()->subDays(10)->getTimestamp()), true), 'ad_click' => ['type' => 'wbraid', 'id' => 'W1']]);

    $this->withCookie('humetria_acq', (string) $cookie)->post(route('demo-requests.store'), attributedSubmission())->assertRedirect();

    expect(storedRequest()->ad_click_type)->toBe(AdClickType::Wbraid)
        ->and(storedRequest()->ad_click_id)->toBe('W1');
});

it('stores no click id while consent is denied, even one left in the session', function (): void {
    $this->withSession([RememberDemoAttribution::CLICK_KEY => ['type' => 'gclid', 'id' => 'CLICK123']])
        ->post(route('demo-requests.store'), attributedSubmission())->assertRedirect();

    expect(storedRequest()->ad_click_id)->toBeNull()
        ->and(storedRequest()->ad_click_type)->toBeNull();
});

it('stores no click id from a session or cookie value that is not what was written', function (mixed $click): void {
    consentGranted();

    $this->withSession([RememberDemoAttribution::CLICK_KEY => $click])
        ->post(route('demo-requests.store'), attributedSubmission())->assertRedirect();

    expect(storedRequest()->ad_click_id)->toBeNull();
})->with([
    'a string' => ['CLICK123'],
    'an unknown type' => [['type' => 'fbclid', 'id' => 'X1']],
    'a bad id' => [['type' => 'gclid', 'id' => 'no vale']],
    'no id' => [['type' => 'gclid']],
]);

it('stores the keyword on the request, days later and from another page, through the cookie', function (): void {
    consentGranted();
    $this->get('/?utm_source=google&utm_medium=cpc&utm_campaign=checador&utm_term=reloj+checador&utm_content=789&matchtype=p&gclid=CLICK123');
    $cookie = acquisition($this->get('/?utm_source=google&utm_medium=cpc&utm_campaign=checador&utm_term=reloj+checador&utm_content=789&matchtype=p&gclid=CLICK123'));

    $this->flushSession();
    $this->withCookie('humetria_acq', (string) json_encode($cookie))->post(route('demo-requests.store'), attributedSubmission())->assertRedirect();

    expect(storedRequest()->utm_term)->toBe('reloj checador')
        ->and(storedRequest()->utm_content)->toBe('789')
        ->and(storedRequest()->match_type)->toBe('p')
        ->and(storedRequest()->ad_click_id)->toBe('CLICK123')
        ->and(storedRequest()->utm_campaign)->toBe('checador');
});

it('stores the keyword the session remembered when there is no cookie', function (): void {
    $this->get('/?utm_source=google&utm_term=checador')->assertOk();

    $this->post(route('demo-requests.store'), attributedSubmission())->assertRedirect();

    expect(storedRequest()->utm_term)->toBe('checador')
        ->and(storedRequest()->utm_content)->toBeNull()
        ->and(storedRequest()->match_type)->toBeNull();
});

it('keeps the attribution for a second try after a submit the form refused', function (): void {
    $this->get('/?utm_source=anuncios')->assertOk();

    $this->post(route('demo-requests.store'), attributedSubmission(['email' => 'no-es-correo']))->assertSessionHasErrors('email');
    expect(DemoRequest::query()->count())->toBe(0);

    $this->post(route('demo-requests.store'), attributedSubmission())->assertRedirect();

    expect(storedRequest()->arrivedBy())->toBe('anuncios');
});

it('stores nothing when a bot fills the trap field, whatever the attribution', function (): void {
    consentGranted();
    $cookie = acquisitionCookie(touchOf('anuncios'), touchOf('anuncios'), now()->getTimestamp());

    $this->withCookie('humetria_acq', $cookie)->post(route('demo-requests.store'), attributedSubmission(['form_extra' => 'http://spam.example']))->assertRedirect();

    expect(DemoRequest::query()->count())->toBe(0);
});

it('writes no value of where the visitor came from in any log line', function (): void {
    consentGranted();

    $this->withHeaders(['Referer' => 'https://secretreferrer.example/page?token=secret'])
        ->get('/?utm_source=secretsource&utm_medium=secretmedium&utm_campaign=secretcampaign')->assertOk();
    $this->post(route('demo-requests.store'), attributedSubmission())->assertRedirect();
    $this->post(route('demo-requests.store'), attributedSubmission(['form_extra' => 'bot']))->assertRedirect();

    $log = File::exists($this->logPath) ? File::get($this->logPath) : '';

    expect($log)->toContain('demo_request.submitted');

    foreach (['secretsource', 'secretmedium', 'secretcampaign', 'secretreferrer', 'secret'] as $value) {
        expect($log)->not->toContain($value);
    }
});

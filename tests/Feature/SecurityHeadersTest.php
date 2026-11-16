<?php

declare(strict_types=1);

use App\Http\Middleware\SecurityHeaders;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Vite;
use Illuminate\Testing\TestResponse;

mutates(SecurityHeaders::class);

function cspFor(string $nonce, string $devServer = '', string $devSocket = '', bool $map = false, bool $analytics = false): string
{
    $hot = $devServer === '' ? '' : " {$devServer}";
    $socket = $devSocket === '' ? '' : " {$devSocket}";

    // Sonner injects its stylesheet without a nonce: an empty <style> first, then its CSS.
    $sonner = "'sha256-47DEQpj8HBSa+/TImW+5JCeuQeRkm5NMpJWZG3hSuFU=' 'sha256-StEaX+se6YS7pqjzrzMIA0KaX9zF/8zAhvQXZAe5epY='";

    // The signed-in panel also paints OpenStreetMap's tiles, the map of a zone (Asistencia): images only, from that one origin.
    $tiles = $map ? ' https://tile.openstreetmap.org' : '';

    // PostHog, only on the landing for a visitor without a session, with a token and consent: one origin, to send events (connect-src).
    // posthog-js comes in our own bundle (no-external build), so no script or image origin opens.
    $connect = $analytics ? ' https://us.i.posthog.com' : '';

    return "default-src 'self'; script-src 'self' 'nonce-{$nonce}'{$hot}; style-src 'self' 'nonce-{$nonce}' {$sonner}{$hot}; "
        ."img-src 'self' data:{$tiles}{$hot}; font-src 'self'{$hot}; connect-src 'self'{$hot}{$socket}{$connect}; object-src 'none'; "
        ."base-uri 'self'; form-action 'self'; frame-ancestors 'none'";
}

function nonceOf(TestResponse $response): string
{
    preg_match("/'nonce-([^']+)'/", (string) $response->headers->get('Content-Security-Policy'), $match);

    return $match[1] ?? '';
}

dataset('pages', [
    'guest page' => [fn (): TestResponse => test()->get(route('login')), false],
    'authenticated page' => [fn (): TestResponse => test()->actingAs(User::factory()->create())->get(route('dashboard')), true],
]);

it('sends the standard security headers', function (Closure $visit, bool $map): void {
    $response = $visit();

    $response->assertOk()
        ->assertHeader('Content-Security-Policy', cspFor(nonceOf($response), map: $map))
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()')
        ->assertHeaderMissing('Strict-Transport-Security');
})->with('pages');

it('signs the inline script, the inline style and the vite tags with the nonce of the policy', function (Closure $visit, bool $map): void {
    $response = $visit();
    $nonce = nonceOf($response);
    $html = (string) $response->getContent();

    expect($nonce)->toMatch('/^[A-Za-z0-9]{40}$/')
        ->and(preg_match_all('/<script(?![^>]*type="application\/json")[^>]*>/', $html, $scripts))->toBeGreaterThan(1)
        ->and($scripts[0])->each->toContain("nonce=\"{$nonce}\"")
        ->and(preg_match_all('/<style[^>]*>/', $html, $styles))->toBeGreaterThan(1)
        ->and($styles[0])->each->toContain("nonce=\"{$nonce}\"");
})->with('pages');

it('lets only the signed-in panel paint the map tiles: a signed-in person outside the panel gets none', function (): void {
    Route::middleware('web')->get('/test/plain', fn (): string => 'ok');

    $response = $this->actingAs(User::factory()->create())->get('/test/plain');

    $response->assertOk()->assertHeader('Content-Security-Policy', cspFor(nonceOf($response)));
});

it('uses a fresh nonce on every request', function (): void {
    $first = nonceOf($this->get(route('login')));
    $second = nonceOf($this->get(route('login')));

    expect($first)->not->toBe('')
        ->and($second)->not->toBe('')
        ->and($first)->not->toBe($second);
});

it('sends HSTS only in production', function (): void {
    app()->detectEnvironment(fn (): string => 'production');

    $this->get(route('login'))
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});

function useHotFile(string $origin): void
{
    $hotFile = tempnam(sys_get_temp_dir(), 'hot');
    file_put_contents($hotFile, "{$origin}\n");
    Vite::useHotFile($hotFile);
    register_shutdown_function(fn (): bool => ! is_file($hotFile) || unlink($hotFile));
}

function assertStandardHeaders(TestResponse $response): void
{
    $response->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');
}

it('lets the local vite dev server serve scripts, styles, assets and hot reload while it runs', function (string $origin, string $socket): void {
    app()->detectEnvironment(fn (): string => 'local');
    useHotFile($origin);

    // A plain route: in hot mode Inertia would try to reach the dev server for SSR.
    Route::get('/test/plain', fn (): string => 'ok');

    $response = $this->get('/test/plain');

    $response->assertOk()->assertHeader('Content-Security-Policy', cspFor(nonceOf($response), $origin, $socket));
})->with([
    'http' => ['http://localhost:5173', 'ws://localhost:5173'],
    'https' => ['https://humetria.test:5173', 'wss://humetria.test:5173'],
]);

it('ignores a stray hot file outside local development', function (string $environment): void {
    app()->detectEnvironment(fn (): string => $environment);
    useHotFile('http://localhost:5173');
    Route::get('/test/plain', fn (): string => 'ok');

    $response = $this->get('/test/plain');

    $response->assertOk()->assertHeader('Content-Security-Policy', cspFor(nonceOf($response)));
})->with(['production', 'testing']);

it('sends the headers on every web response, also the ones the framework answers by itself', function (Closure $request, int $status): void {
    Route::middleware('web')->get('/test/users/{user}', fn (User $user): string => $user->name);
    Route::middleware('web')->post('/test/form', fn (): string => 'ok');

    $response = $request();

    $response->assertStatus($status)
        ->assertHeader('Content-Security-Policy', cspFor(nonceOf($response)));
    assertStandardHeaders($response);
})->with([
    'unknown url' => [fn (): TestResponse => test()->get('/no-such-page'), 404],
    'missing bound model' => [fn (): TestResponse => test()->get('/test/users/999999'), 404],
    'inertia version mismatch' => [fn (): TestResponse => test()->get(route('login'), ['X-Inertia' => 'true', 'X-Inertia-Version' => 'stale']), 409],
    'expired csrf token' => [function (): TestResponse {
        // CSRF is only verified outside unit tests.
        app()->detectEnvironment(fn (): string => 'local');

        return test()->post('/test/form');
    }, 419],
]);

it('renders error pages styled under the policy', function (): void {
    config(['app.debug' => false]);
    Route::middleware('web')->get('/test/boom', fn () => throw new RuntimeException('boom'));

    $response = $this->get('/test/boom');
    $nonce = nonceOf($response);

    expect($response->getStatusCode())->toBe(500)
        ->and($nonce)->not->toBe('')
        ->and(preg_match_all('/<style[^>]*>/', (string) $response->getContent(), $styles))->toBeGreaterThan(0)
        ->and($styles[0])->each->toBe("<style nonce=\"{$nonce}\">");
});

it('leaves the debug exception page without a policy but with the other headers', function (): void {
    config(['app.debug' => true]);
    Route::middleware('web')->get('/test/boom', fn () => throw new RuntimeException('boom'));

    $response = $this->get('/test/boom');

    $response->assertStatus(500)->assertHeaderMissing('Content-Security-Policy');
    assertStandardHeaders($response);
});

it('keeps the policy on a server error that the exception handler did not render', function (string $kind): void {
    config(['app.debug' => true]);
    Route::middleware('web')->get('/test/unavailable', fn () => $kind === 'symfony'
        ? new Symfony\Component\HttpFoundation\Response('down', 503)
        : response('down', 503));

    $this->get('/test/unavailable')->assertStatus(503)->assertHeader('Content-Security-Policy');
})->with(['laravel', 'symfony']);

it('keeps a header that the route set on purpose', function (): void {
    Route::middleware('web')->get('/test/embeddable', fn () => response('ok')->header('X-Frame-Options', 'SAMEORIGIN'));

    $this->get('/test/embeddable')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});

it('lets search engines index the home page only in production', function (): void {
    app()->detectEnvironment(fn (): string => 'production');

    $this->get('/')->assertOk()->assertHeaderMissing('X-Robots-Tag');
});

it('keeps the home page out of search engines outside production', function (string $environment): void {
    app()->detectEnvironment(fn (): string => $environment);

    $this->get('/')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
})->with(['testing', 'local', 'staging']);

it('hides everything that is not an indexable page, in production too', function (Closure $request): void {
    app()->detectEnvironment(fn (): string => 'production');

    $request()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
})->with([
    'login' => [fn (): TestResponse => test()->get(route('login'))],
    'unknown url' => [fn (): TestResponse => test()->get('/no-such-page')],
    'survey with an invented token' => [fn (): TestResponse => test()->get(route('survey.show', 'invented-token'))],
    'passkey endpoints (json)' => [fn (): TestResponse => test()->get(route('well-known.passkeys'))],
]);

it('hides the home page when it answers with a server error', function (): void {
    app()->detectEnvironment(fn (): string => 'production');
    config(['app.debug' => false]);
    Route::middleware('web')->get('/', fn () => throw new RuntimeException('boom'))->name('home');

    $this->get('/')->assertStatus(500)->assertHeader('X-Robots-Tag', 'noindex, nofollow');
});

it('is safe by default: a public route added later is hidden from search engines', function (): void {
    app()->detectEnvironment(fn (): string => 'production');
    Route::middleware('web')->get('/test/new-public', fn (): string => 'ok')->name('test.new-public');

    $this->get('/test/new-public')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
});

it('keeps an X-Robots-Tag that the route set on purpose', function (): void {
    Route::middleware('web')->get('/test/custom-robots', fn () => response('ok')->header('X-Robots-Tag', 'noindex'));

    $this->get('/test/custom-robots')->assertHeader('X-Robots-Tag', 'noindex');
});

it('sends the landing the same policy when Node renders it: no unsafe-inline, no unsafe-eval, no style-src-attr', function (): void {
    config(['inertia.ssr.enabled' => true, 'inertia.ssr.ensure_bundle_exists' => false]);
    Http::fake(['http://127.0.0.1:13714/render' => Http::response(['head' => [], 'body' => '<div>rendered</div>'])]);

    $response = $this->get('/');
    $policy = (string) $response->headers->get('Content-Security-Policy');

    $response->assertOk()->assertHeader('X-Rendered-By', 'ssr')->assertHeader('Content-Security-Policy', cspFor(nonceOf($response)));
    expect($policy)->not->toContain('unsafe-inline')->not->toContain('unsafe-eval')->not->toContain('style-src-attr');
});

function measureLandingWith(string $token = 'phc_AbCdEfGhIjKlMnOpQrStUvWxYz0123456789ABCDEFG', string $consent = 'granted'): void
{
    config(['services.posthog.project_token' => $token, 'services.posthog.consent_default' => $consent]);
}

it('opens the PostHog origin on the landing for a visitor without a session, with a token and consent, and nowhere else', function (): void {
    measureLandingWith();

    $response = $this->get(route('home'));

    $response->assertOk()->assertHeader('Content-Security-Policy', cspFor(nonceOf($response), analytics: true));
    expect((string) $response->headers->get('Content-Security-Policy'))
        ->not->toContain('unsafe-inline')
        ->not->toContain('unsafe-eval')
        ->not->toContain('style-src-attr');
});

it('keeps the policy of the landing exactly as it was without a token, without consent, with a session or on another page', function (Closure $visit, string $token, string $consent): void {
    measureLandingWith($token, $consent);

    $response = $visit();

    $response->assertHeader('Content-Security-Policy', cspFor(nonceOf($response), map: false));
    expect((string) $response->headers->get('Content-Security-Policy'))->not->toContain('posthog');
})->with([
    'no token' => [fn (): TestResponse => test()->get(route('home')), '', 'granted'],
    'consent denied' => [fn (): TestResponse => test()->get(route('home')), 'phc_AbCdEfGhIjKlMnOpQrStUvWxYz0123456789ABCDEFG', 'denied'],
    'a malformed token' => [fn (): TestResponse => test()->get(route('home')), 'phc_abc', 'granted'],
]);

it('opens the PostHog origin for error reports on every page but the landing when there is a token, consent or not', function (Closure $visit, string $consent): void {
    measureLandingWith(consent: $consent);

    $response = $visit();

    $response->assertHeader('Content-Security-Policy', cspFor(nonceOf($response), map: false, analytics: true));
})->with([
    'another page' => [fn (): TestResponse => test()->get(route('privacy-notice')), 'granted'],
    'the login' => [fn (): TestResponse => test()->get(route('login')), 'denied'],
    'a survey link' => [fn (): TestResponse => test()->get('/survey/NO-SUCH-TOKEN'), 'denied'],
]);

it('opens no PostHog origin for error reports without a token', function (): void {
    measureLandingWith('');

    expect((string) $this->get(route('login'))->headers->get('Content-Security-Policy'))->not->toContain('posthog');
});

it('keeps the policy of the landing as it was for someone with a session', function (): void {
    measureLandingWith();

    $response = $this->actingAs(User::factory()->create())->get(route('home'));

    $response->assertHeader('Content-Security-Policy', cspFor(nonceOf($response)));
});

it('sends the policy of the landing of today when nothing is configured', function (): void {
    $response = $this->get(route('home'));

    $response->assertOk()->assertHeader('Content-Security-Policy', cspFor(nonceOf($response)));
});

<?php

declare(strict_types=1);

use App\Http\Middleware\SecurityHeaders;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Vite;
use Illuminate\Testing\TestResponse;

mutates(SecurityHeaders::class);

function cspFor(string $nonce, string $devServer = '', string $devSocket = ''): string
{
    $hot = $devServer === '' ? '' : " {$devServer}";
    $socket = $devSocket === '' ? '' : " {$devSocket}";

    // Sonner injects its stylesheet without a nonce: an empty <style> first, then its CSS.
    $sonner = "'sha256-47DEQpj8HBSa+/TImW+5JCeuQeRkm5NMpJWZG3hSuFU=' 'sha256-StEaX+se6YS7pqjzrzMIA0KaX9zF/8zAhvQXZAe5epY='";

    return "default-src 'self'; script-src 'self' 'nonce-{$nonce}'{$hot}; style-src 'self' 'nonce-{$nonce}' {$sonner}{$hot}; "
        ."img-src 'self' data:{$hot}; font-src 'self'{$hot}; connect-src 'self'{$hot}{$socket}; object-src 'none'; "
        ."base-uri 'self'; form-action 'self'; frame-ancestors 'none'";
}

function nonceOf(TestResponse $response): string
{
    preg_match("/'nonce-([^']+)'/", (string) $response->headers->get('Content-Security-Policy'), $match);

    return $match[1] ?? '';
}

dataset('pages', [
    'guest page' => [fn (): TestResponse => test()->get(route('login'))],
    'authenticated page' => [fn (): TestResponse => test()->actingAs(User::factory()->create())->get(route('dashboard'))],
]);

it('sends the standard security headers', function (Closure $visit): void {
    $response = $visit();

    $response->assertOk()
        ->assertHeader('Content-Security-Policy', cspFor(nonceOf($response)))
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()')
        ->assertHeaderMissing('Strict-Transport-Security');
})->with('pages');

it('signs the inline script, the inline style and the vite tags with the nonce of the policy', function (Closure $visit): void {
    $response = $visit();
    $nonce = nonceOf($response);
    $html = (string) $response->getContent();

    expect($nonce)->toMatch('/^[A-Za-z0-9]{40}$/')
        ->and(preg_match_all('/<script(?![^>]*type="application\/json")[^>]*>/', $html, $scripts))->toBeGreaterThan(1)
        ->and($scripts[0])->each->toContain("nonce=\"{$nonce}\"")
        ->and(preg_match_all('/<style[^>]*>/', $html, $styles))->toBeGreaterThan(1)
        ->and($styles[0])->each->toContain("nonce=\"{$nonce}\"");
})->with('pages');

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

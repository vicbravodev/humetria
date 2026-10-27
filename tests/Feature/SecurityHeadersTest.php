<?php

declare(strict_types=1);

use App\Http\Middleware\SecurityHeaders;
use App\Models\User;
use Illuminate\Support\Facades\Vite;
use Illuminate\Testing\TestResponse;

mutates(SecurityHeaders::class);

function cspFor(string $nonce, string $devServer = ''): string
{
    $hot = $devServer === '' ? '' : " {$devServer}";
    $socket = $devServer === '' ? '' : ' '.str_replace('http', 'ws', $devServer);

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

it('lets the vite dev server serve scripts, styles, assets and hot reload while it runs', function (): void {
    $hotFile = tempnam(sys_get_temp_dir(), 'hot');
    file_put_contents($hotFile, "http://localhost:5173\n");
    Vite::useHotFile($hotFile);

    $response = $this->get(route('login'));

    unlink($hotFile);
    $response->assertHeader('Content-Security-Policy', cspFor(nonceOf($response), 'http://localhost:5173'));
});

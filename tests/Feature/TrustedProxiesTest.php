<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    Route::get('/__proxy-probe', fn (Request $request): array => [
        'secure' => $request->isSecure(),
        'url' => url('/admin'),
    ]);
});

it('trusts no proxy by default, so a forged X-Forwarded-Proto changes nothing', function (): void {
    expect(config()->has('trustedproxy.proxies'))->toBeTrue()
        ->and(config('trustedproxy.proxies'))->toBeNull();

    $this->get('/__proxy-probe', ['X-Forwarded-Proto' => 'https'])
        ->assertJson(['secure' => false, 'url' => 'http://localhost/admin']);
});

it('honours X-Forwarded-Proto from the configured proxies', function (string $proxies): void {
    config(['trustedproxy.proxies' => $proxies]);

    $this->get('/__proxy-probe', ['X-Forwarded-Proto' => 'https'])
        ->assertJson(['secure' => true, 'url' => 'https://localhost/admin']);
})->with([
    'every proxy' => '*',
    'a list that includes the caller' => '10.0.0.1, 127.0.0.1',
]);

it('ignores X-Forwarded-Proto from a proxy that is not in the list', function (): void {
    config(['trustedproxy.proxies' => '10.0.0.1']);

    $this->get('/__proxy-probe', ['X-Forwarded-Proto' => 'https'])
        ->assertJson(['secure' => false, 'url' => 'http://localhost/admin']);
});

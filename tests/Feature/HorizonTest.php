<?php

declare(strict_types=1);

use App\Models\User;
use App\Providers\HorizonServiceProvider;

mutates(HorizonServiceProvider::class);

it('shows the queue dashboard to the superadmin', function (): void {
    $this->actingAs(superadmin())->get('/horizon')->assertOk();
});

it('hides the queue dashboard from a company user', function (string $environment): void {
    app()->detectEnvironment(fn (): string => $environment);

    $this->actingAs(User::factory()->create())->get('/horizon')->assertForbidden();
    $this->actingAs(User::factory()->create())->getJson('/horizon/api/stats')->assertForbidden();
})->with(['testing', 'local']);

it('sends a guest to the login page', function (): void {
    $this->get('/horizon')->assertRedirect(route('login'));
    $this->getJson('/horizon/api/stats')->assertUnauthorized();
});

it('signs the dashboard inline styles and script with the nonce of the policy', function (): void {
    $response = $this->actingAs(superadmin())->get('/horizon')->assertOk();

    preg_match("/'nonce-([^']+)'/", (string) $response->headers->get('Content-Security-Policy'), $match);

    expect(substr_count((string) $response->getContent(), 'nonce="'.$match[1].'"'))->toBe(4)
        ->and((string) $response->getContent())->not->toMatch('/<(script|style)(?![^>]*nonce=)[^>]*>/');
});

it('lets Horizon compile its templates', function (): void {
    $policy = (string) $this->actingAs(superadmin())->get('/horizon')->assertOk()->headers->get('Content-Security-Policy');

    expect($policy)->toContain("'unsafe-eval'")->toContain('https://fonts.bunny.net');
});

it('keeps the strict policy everywhere else', function (string $url): void {
    $policy = (string) $this->actingAs(superadmin())->get($url)->headers->get('Content-Security-Policy');

    expect($policy)->not->toContain("'unsafe-eval'")->not->toContain('bunny');
})->with(['/login', '/admin', '/horizonte']);

it('still forbids Horizon to anyone but the superadmin, with the strict policy', function (): void {
    $response = $this->actingAs(User::factory()->create())->get('/horizon')->assertForbidden();

    expect((string) $response->headers->get('Content-Security-Policy'))->not->toContain("'unsafe-eval'");
});

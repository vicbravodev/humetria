<?php

declare(strict_types=1);

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the app routes no longer answer at the root', function (string $path) {
    $this->actingAs(User::factory()->create())->get($path)->assertNotFound();
})->with(['/dashboard', '/settings/profile', '/login', '/forgot-password']);

test('guests are redirected from /admin to /admin/login', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
});

test('verified users see the dashboard at /admin', function () {
    $this->actingAs(User::factory()->create())
        ->get('/admin')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('dashboard'));
});

test('named routes resolve under /admin', function (string $name, string $path) {
    expect(route($name, absolute: false))->toBe($path);
})->with([
    ['dashboard', '/admin'],
    ['login', '/admin/login'],
    ['profile.edit', '/admin/settings/profile'],
    ['security.edit', '/admin/settings/security'],
    ['password.request', '/admin/forgot-password'],
    ['two-factor.login', '/admin/two-factor-challenge'],
    ['password.confirm', '/admin/user/confirm-password'],
]);

test('the login form posts to /admin/login', function () {
    $this->get('/admin/login')->assertOk();
    expect(route('login.store', absolute: false))->toBe('/admin/login');
});

test('the passkey endpoints and health check stay at the root', function () {
    $this->get('/.well-known/passkey-endpoints')->assertOk();
    $this->get('/up')->assertOk();
});

test('users land on /admin after login', function () {
    $user = User::factory()->create();

    $this->post('/admin/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect('/admin');
});

<?php

declare(strict_types=1);

use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * The public surface of the app: routes that may be reached without authentication.
 * Adding a name here needs a one-line reason.
 *
 * @return list<string>
 */
function publicRouteNames(): array
{
    return [
        'home', // landing page
        'login', // guest: sign-in form
        'login.store', // guest: sign-in submit
        'register', // guest: sign-up form
        'register.store', // guest: sign-up submit
        'password.request', // guest: forgot-password form
        'password.email', // guest: sends the reset link
        'password.reset', // guest: reset form reached from the emailed link
        'password.update', // guest: reset submit
        'two-factor.login', // mid-login: 2FA challenge, user is not authenticated yet
        'two-factor.login.store', // mid-login: 2FA challenge submit
        'passkey.login', // guest: passkey sign-in submit
        'passkey.login-options', // guest: passkey sign-in challenge
        'well-known.passkeys', // spec-mandated discovery document for password managers
    ];
}

/**
 * Unnamed routes the framework registers and Laravel gives no way to name, keyed by URI.
 *
 * @return list<string>
 */
function unnamedPublicUris(): array
{
    return [
        'up', // health check from bootstrap/app.php `health:`; load balancers must reach it unauthenticated
    ];
}

/** @return list<LaravelRoute> */
function appRoutes(): array
{
    return array_values(Route::getRoutes()->getRoutes());
}

function requiresAuthentication(LaravelRoute $route): bool
{
    return collect($route->gatherMiddleware())
        ->contains(fn (mixed $middleware): bool => is_string($middleware) && ($middleware === 'auth' || Str::startsWith($middleware, 'auth:')));
}

test('every route has a name', function () {
    $unnamed = collect(appRoutes())
        ->filter(fn (LaravelRoute $route): bool => $route->getName() === null && ! in_array($route->uri(), unnamedPublicUris(), true))
        ->map(fn (LaravelRoute $route): string => implode('|', $route->methods()).' '.$route->uri())
        ->values()
        ->all();

    expect($unnamed)->toBe([]);
});

test('every route requires authentication unless it is in the public allowlist', function () {
    $unprotected = collect(appRoutes())
        ->reject(fn (LaravelRoute $route): bool => requiresAuthentication($route))
        ->reject(fn (LaravelRoute $route): bool => in_array($route->getName(), publicRouteNames(), true) || in_array($route->uri(), unnamedPublicUris(), true))
        ->map(fn (LaravelRoute $route): string => implode('|', $route->methods()).' '.$route->uri())
        ->values()
        ->all();

    expect($unprotected)->toBe([]);
});

test('the public allowlist has no stale entries', function () {
    $names = collect(appRoutes())->map(fn (LaravelRoute $route): ?string => $route->getName())->filter()->all();
    $uris = collect(appRoutes())->map(fn (LaravelRoute $route): string => $route->uri())->all();

    expect(array_values(array_diff(publicRouteNames(), $names)))->toBe([])
        ->and(array_values(array_diff(unnamedPublicUris(), $uris)))->toBe([]);
});

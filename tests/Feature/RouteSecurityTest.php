<?php

declare(strict_types=1);

use Illuminate\Auth\Middleware\Authenticate;
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
        'demo-requests.store', // landing demo form; throttled + honeypot
        'login', // guest: sign-in form
        'login.store', // guest: sign-in submit
        'password.request', // guest: forgot-password form
        'password.email', // guest: sends the reset link
        'password.reset', // guest: reset form reached from the emailed link
        'password.update', // guest: reset submit
        'two-factor.login', // mid-login: 2FA challenge, user is not authenticated yet
        'two-factor.login.store', // mid-login: 2FA challenge submit
        'passkey.login', // guest: passkey sign-in submit
        'passkey.login-options', // guest: passkey sign-in challenge
        'well-known.passkeys', // spec-mandated discovery document for password managers
        'invitations.show', // guest: invitation link from the email; the token is the credential
        'invitations.accept', // guest: accepts the invitation; throttled, single-use token
        'survey.show', // guest: survey link from the email; the token is the credential; throttled
        'survey.submit', // guest: submits one guide; throttled, under a lock, once per guide
        'portal.show', // guest: la persona teletrabajadora no tiene cuenta; el token es la credencial; throttled
        'portal.checklist.show', // guest: la lista de verificación de la persona; el token del portal es la credencial; solo ve sus lugares; throttled
        'portal.checklist.store', // guest: envía la lista una sola vez, bajo candado; el token del portal es la credencial; throttled
        'portal.evidence.store', // guest: la persona envía las fotos que pidió la Comisión, una sola vez, bajo candado; el token del portal es la credencial; solo sus lugares; throttled
        'portal.visits.confirm', // guest: la persona autoriza por escrito la visita a su lugar; el token del portal es la credencial; solo sus lugares; throttled
        'report.shared', // guest: a report from a temporary link (48 h at most, revocable); the token is the credential; throttled
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

/**
 * Resolved the way the router runs them: groups expanded (and aliases, once the HTTP kernel registered them), so `auth` inside a middleware group
 * (Horizon wraps its routes in a `horizon` group built from config/horizon.php) counts too.
 */
function requiresAuthentication(LaravelRoute $route): bool
{
    return collect(Route::gatherRouteMiddleware($route))
        ->contains(fn (mixed $middleware): bool => is_string($middleware)
            && (in_array($middleware, ['auth', Authenticate::class], true) || Str::startsWith($middleware, ['auth:', Authenticate::class.':'])));
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

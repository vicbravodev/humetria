<?php

declare(strict_types=1);

use App\Http\SsrPages;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

mutates(SsrPages::class);

beforeEach(function (): void {
    config(['inertia.ssr.enabled' => true]);
    Cache::forget(SsrPages::CIRCUIT_KEY);
});

/** A request that resolved the named route, as the router leaves it before the view renders. */
function requestFor(?string $routeName, ?User $user = null): Request
{
    $request = Request::create('/');
    $request->setRouteResolver(fn () => $routeName === null ? null : Route::getRoutes()->getByName($routeName));
    $request->setUserResolver(fn () => $user);

    return $request;
}

it('renders the allowed pages for a visitor', function (string $route, string $component): void {
    expect(SsrPages::allows(requestFor($route), ['component' => $component]))->toBeTrue();
})->with([['home', 'landing'], ['privacy-notice', 'privacy-notice']]);

it('does not render when the switch is off', function (): void {
    config(['inertia.ssr.enabled' => false]);

    expect(SsrPages::allows(requestFor('home'), ['component' => 'landing']))->toBeFalse();
});

it('does not render a route outside the list, or no route at all', function (?string $route): void {
    expect(SsrPages::allows(requestFor($route), ['component' => 'landing']))->toBeFalse();
})->with([null, 'login', 'dashboard']);

it('does not render another component on an allowed route', function (string $component): void {
    expect(SsrPages::allows(requestFor('home'), ['component' => $component]))->toBeFalse();
})->with(['error', 'privacy-notice', 'dashboard']);

it('does not render a page that names no component', function (): void {
    expect(SsrPages::allows(requestFor('home'), []))->toBeFalse()
        ->and(SsrPages::allows(requestFor('home'), ['component' => ['landing']]))->toBeFalse();
});

it('does not render for anyone signed in', function (): void {
    expect(SsrPages::allows(requestFor('home', User::factory()->make()), ['component' => 'landing']))->toBeFalse();
});

it('does not render while the circuit breaker is open, and does again when it closes', function (): void {
    Cache::put(SsrPages::CIRCUIT_KEY, true, 30);

    expect(SsrPages::allows(requestFor('home'), ['component' => 'landing']))->toBeFalse();

    Cache::forget(SsrPages::CIRCUIT_KEY);

    expect(SsrPages::allows(requestFor('home'), ['component' => 'landing']))->toBeTrue();
});

it('lists only public GET routes with a name, no parameters and no auth', function (): void {
    expect(SsrPages::ALLOWED)->not->toBeEmpty();

    foreach (array_keys(SsrPages::ALLOWED) as $name) {
        expect(Route::has($name))->toBeTrue();

        $route = Route::getRoutes()->getRoutesByName()[$name];

        expect($route->methods())->toContain('GET')
            ->and($route->parameterNames())->toBe([])
            ->and($route->gatherMiddleware())->not->toContain('auth');
    }
});

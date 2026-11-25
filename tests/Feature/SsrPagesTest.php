<?php

declare(strict_types=1);

use App\Http\SsrPages;
use App\Models\User;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

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
})->with([['home', 'landing'], ['privacy-notice', 'privacy-notice'], ['blog.index', 'blog/index'], ['blog.show', 'blog/show']]);

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

    // The one parameter allowed is the slug of a blog article: public text in the address, never a credential.
    $parameters = ['blog.show' => ['slug']];

    foreach (array_keys(SsrPages::ALLOWED) as $name) {
        expect(Route::has($name))->toBeTrue();

        $route = Route::getRoutes()->getRoutesByName()[$name];

        expect($route->methods())->toContain('GET')
            ->and($route->parameterNames())->toBe($parameters[$name] ?? [])
            ->and($route->gatherMiddleware())->not->toContain('auth');
    }
});

/*
|--------------------------------------------------------------------------
| The whole app, through the real Inertia gateway, with a fake Node
|--------------------------------------------------------------------------
*/

/** Switches SSR on and puts a fake Node behind the gateway: no bundle, no real server. */
function fakeNode(): void
{
    config(['inertia.ssr.enabled' => true, 'inertia.ssr.ensure_bundle_exists' => false]);
    Http::fake(['http://127.0.0.1:13714/render' => Http::response(['head' => [], 'body' => '<div></div>'])]);
}

function nodeRenders(): int
{
    return Http::recorded(fn ($request): bool => str_ends_with($request->url(), '/render'))->count();
}

/** @return list<string> the URI of every GET route, each parameter filled with 'x' (token routes answer "link unavailable" or 404) */
function everyGetUri(): array
{
    $uris = [];

    foreach (Route::getRoutes()->getRoutes() as $route) {
        if (in_array('GET', $route->methods(), true)) {
            $uris[] = (string) preg_replace('/\{[^}]+\}/', 'x', $route->uri());
        }
    }

    return array_values(array_unique($uris));
}

it('sends to Node only the pages on the list, for a visitor: every GET route', function (): void {
    fakeNode();
    $rendered = [];

    foreach (everyGetUri() as $uri) {
        app()->forgetScopedInstances();
        $before = nodeRenders();
        $this->get('/'.ltrim($uri, '/'));

        if (nodeRenders() > $before) {
            $rendered[$uri] = nodeRenders() - $before;
        }
    }

    expect($rendered)->toBe([
        '/' => 1,
        'privacy-notice' => 1,
        'nom-035' => 1,
        'nom-037' => 1,
        'ley-silla' => 1,
        'buzon-de-quejas' => 1,
        'compensacion' => 1,
        'registro-de-jornada' => 1,
        'vacaciones-y-permisos' => 1,
        'organigrama' => 1,
        'clima-laboral' => 1,
        'guias/registro-de-jornada-2027' => 1,
        'blog' => 1,
    ]);
});

it('sends nothing to Node for anyone signed in, on any GET route', function (): void {
    fakeNode();
    $this->actingAs(User::factory()->create());

    foreach (everyGetUri() as $uri) {
        app()->forgetScopedInstances();
        $this->get('/'.ltrim($uri, '/'));
    }

    expect(nodeRenders())->toBe(0);
});

it('sends nothing to Node for a superadmin working as a company owner', function (): void {
    fakeNode();
    $tenant = Tenant::factory()->create();
    ownerOf($tenant);

    $this->actingAs(superadmin())->post(route('companies.impersonate', $tenant), supportEntry());
    $this->get('/')->assertOk();

    expect(nodeRenders())->toBe(0);
});

it('sends nothing to Node for the error page served on an allowed route', function (): void {
    fakeNode();
    config(['app.debug' => false]);
    Route::middleware('web')->get('/test/abort', fn () => abort(500))->name('home');

    $this->get('/test/abort')->assertStatus(500);

    expect(nodeRenders())->toBe(0);
});

it('is safe by default: a public route added tomorrow is not rendered in Node', function (): void {
    fakeNode();
    Route::middleware('web')->get('/test/new-public', fn () => Inertia::render('landing'))->name('new-public');

    $this->get('/test/new-public')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');

    expect(nodeRenders())->toBe(0);
});

it('sends the landing to Node once for a visitor', function (): void {
    fakeNode();

    $this->get('/')->assertOk();

    expect(nodeRenders())->toBe(1);
});

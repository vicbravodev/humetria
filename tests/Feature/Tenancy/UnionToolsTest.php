<?php

declare(strict_types=1);

use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Http\Middleware\LimitUnionToCompensation;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Support\ComplianceRoutes;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

mutates(LimitUnionToCompensation::class);

/**
 * The routes of an account that are open to a union as well as to a company. A new `tenant.*` route must be listed here
 * or in ComplianceRoutes: the test below fails with its name until someone decides which side it is on.
 *
 * @var list<string>
 */
const UNION_ALLOWED_ROUTES = [
    'tenant.dashboard',
    'tenant.employees.*',
    'tenant.areas.*',
    'tenant.members.*',
    'tenant.job-grades.*',
    'tenant.compensation.*',
];

/** The URL of `$route` inside `$tenant`, with the ids of another account's campaign in every other parameter. */
function unionProbeUrl(LaravelRoute $route, Tenant $tenant, Campaign $foreign): string
{
    $uri = preg_replace('/\{tenant(:slug)?\}/', $tenant->slug, $route->uri());

    return '/'.preg_replace_callback('/\{(\w+)\??\}/', function (array $match) use ($route, $foreign): string {
        $constraint = $route->wheres[$match[1]] ?? '';

        return match (true) {
            str_contains($constraint, '[0-9]') => '1',
            // A whereIn() constraint (a fixed list of words, digits allowed: nom035|nom037|seating): the first of them, or the
            // URL would not reach the route.
            preg_match('/^[a-z0-9_|-]+$/', $constraint) === 1 => explode('|', $constraint)[0],
            $match[1] === 'workCenter' => $foreign->work_center_id,
            default => $foreign->id,
        };
    }, (string) $uri);
}

/** @return list<LaravelRoute> */
function tenantRoutesMatching(string ...$patterns): array
{
    return array_values(array_filter(
        Route::getRoutes()->getRoutes(),
        fn (LaravelRoute $route): bool => Str::is($patterns, (string) $route->getName()),
    ));
}

it('answers 404 to a union on every route matching ComplianceRoutes, also with ids of another account', function (): void {
    $union = Tenant::factory()->union()->create();
    $owner = memberOf($union, TenantRole::Owner);
    $foreign = Campaign::factory()->create();
    $routes = tenantRoutesMatching(...ComplianceRoutes::patterns());

    expect($routes)->not->toBeEmpty();

    foreach ($routes as $route) {
        $url = unionProbeUrl($route, $union, $foreign);

        foreach ($route->methods() as $method) {
            if ($method === 'HEAD') {
                continue;
            }

            // The probe must reach this very route, or a 404 from the router would prove nothing.
            expect(Route::getRoutes()->match(Request::create($url, $method))->getName())->toBe($route->getName());

            $this->actingAs($owner)->call($method, $url)->assertNotFound();
        }
    }
});

it('lets a union reach employees, areas, members and the dashboard redirect to Compensación', function (): void {
    $union = Tenant::factory()->union()->create();
    $owner = memberOf($union, TenantRole::Owner);

    $this->actingAs($owner)->get(route('tenant.employees.index', $union))->assertOk();
    $this->actingAs($owner)->get(route('tenant.members.index', $union))->assertOk();
    $this->actingAs($owner)->get(route('tenant.areas.options', $union))->assertOk();
    // The panel of a union is Compensación (Task 7 of the calculator plan: it was its roster until then).
    $this->actingAs($owner)->get(route('tenant.dashboard', $union))->assertRedirect(route('tenant.compensation.index', $union));
});

it('leaves companies untouched', function (): void {
    $company = Tenant::factory()->create();
    $owner = memberOf($company, TenantRole::Owner);

    foreach (['tenant.dashboard', 'tenant.campaigns.index', 'tenant.work-centers.index', 'tenant.telework.index', 'tenant.seating.index', 'tenant.assistant.index', 'tenant.employees.index'] as $name) {
        $this->actingAs($owner)->get(route($name, $company))->assertOk();
    }
});

it('forces every named tenant.* route to be either compliance or allowed', function (): void {
    $unclassified = collect(Route::getRoutes()->getRoutes())
        ->map(fn (LaravelRoute $route): string => (string) $route->getName())
        ->filter(fn (string $name): bool => Str::startsWith($name, 'tenant.'))
        ->reject(fn (string $name): bool => Str::is([...ComplianceRoutes::patterns(), ...UNION_ALLOWED_ROUTES], $name))
        ->values()
        ->all();

    expect($unclassified)->toBe([]);
});

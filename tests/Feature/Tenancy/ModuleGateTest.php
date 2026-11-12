<?php

declare(strict_types=1);

use App\Modules\Tenancy\Contracts\CompanyModules;
use App\Modules\Tenancy\Enums\Module;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Http\Middleware\LimitToEnabledModules;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Support\ModuleRoutes;
use Carbon\CarbonImmutable;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

mutates(LimitToEnabledModules::class, ModuleRoutes::class);

/** The page that opens each module that has routes. */
const MODULE_PAGES = [
    'attendance' => 'tenant.attendance.index',
    'nom035' => 'tenant.campaigns.index',
    'nom037' => 'tenant.telework.index',
    'seating' => 'tenant.seating.index',
    'complaints' => 'tenant.complaints.index',
    'vacations' => 'tenant.vacations.index',
    'org_chart' => 'tenant.org-chart.index',
    'pulse' => 'tenant.pulse.index',
    'climate' => 'tenant.climate.index',
];

/** Pages that answer 404 on their own until something exists, so they cannot prove the module is what opens them. */
const POSTERS_THAT_WAIT_FOR_THEIR_CONTENT = [
    'tenant.disclosure.page.poster' => 'there is no published page yet',
    'tenant.complaints.poster' => 'there is no channel yet',
];

/** A company that has only `$modules`, from today. */
function companyWithOnly(array $modules): Tenant
{
    $tenant = Tenant::factory()->create();
    resolve(CompanyModules::class)->syncPlan($tenant->id, $modules, CarbonImmutable::now(config('app.display_timezone')));

    return $tenant;
}

it('makes every tenant route belong to exactly one module, or to the ones every company has', function (): void {
    $routes = array_values(array_filter(
        array_map(fn (LaravelRoute $route): string => (string) $route->getName(), Route::getRoutes()->getRoutes()),
        fn (string $name): bool => str_starts_with($name, 'tenant.'),
    ));
    $orphans = [];
    $twice = [];

    foreach ($routes as $name) {
        $owners = array_keys(array_filter(ModuleRoutes::patterns(), fn (array $patterns): bool => Str::is($patterns, $name)));
        $always = Str::is(ModuleRoutes::always(), $name);

        if (count($owners) + ($always ? 1 : 0) === 0) {
            $orphans[] = $name;
        }

        if (count($owners) + ($always ? 1 : 0) > 1) {
            $twice[] = $name;
        }
    }

    expect($orphans)->toBe([])->and($twice)->toBe([])
        ->and(array_diff(array_keys(ModuleRoutes::patterns()), array_map(fn (Module $module): string => $module->value, Module::cases())))->toBe([]);
});

it('opens the pages of a module that the company has and answers 404 once it does not', function (string $module, string $page): void {
    $others = array_values(array_diff(array_keys(MODULE_PAGES), [$module]));
    $tenant = companyWithOnly([$module]);
    $owner = ownerOf($tenant);

    $this->actingAs($owner)->get(route($page, $tenant))->assertOk();

    $without = companyWithOnly($others);
    $ownerWithout = ownerOf($without);

    $this->actingAs($ownerWithout)->get(route($page, $without))->assertNotFound();
    // The rest of the panel is not touched.
    $this->get(route('tenant.dashboard', $without))->assertOk();
    $this->get(route('tenant.employees.index', $without))->assertOk();
})->with(array_map(fn (string $page): array => [array_search($page, MODULE_PAGES, true), $page], MODULE_PAGES));

it('answers 404 to a company of another account, whatever it has', function (string $module, string $page): void {
    $tenant = Tenant::factory()->create();
    $stranger = ownerOf(Tenant::factory()->create());

    $this->actingAs($stranger)->get(route($page, $tenant))->assertNotFound();
})->with(array_map(fn (string $page): array => [array_search($page, MODULE_PAGES, true), $page], MODULE_PAGES));

it('closes every route of a disabled module that opens without ids, and leaves them open while it is enabled', function (): void {
    $enabled = companyWithOnly(array_keys(MODULE_PAGES));
    // A plan has at least one module: two companies with one each, so that every module is missing from one of them.
    $withAttendance = companyWithOnly(['attendance']);
    $withNom035 = companyWithOnly(['nom035']);
    $ownerOn = ownerOf($enabled);
    $owners = [$withAttendance->id => ownerOf($withAttendance), $withNom035->id => ownerOf($withNom035)];
    $checked = 0;
    $closedAnyway = [];

    foreach (ModuleRoutes::patterns() as $module => $patterns) {
        $disabled = $module === 'attendance' ? $withNom035 : $withAttendance;
        $ownerOff = $owners[$disabled->id];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (! Str::is($patterns, (string) $route->getName()) || ! in_array('GET', $route->methods(), true) || preg_match('/\{(?!tenant)/', $route->uri()) === 1) {
                continue;
            }

            $uri = fn (Tenant $tenant): string => '/'.str_replace(['{tenant:slug}', '{tenant}'], $tenant->slug, $route->uri());

            $this->actingAs($ownerOff)->get($uri($disabled))->assertNotFound();
            if (! array_key_exists((string) $route->getName(), POSTERS_THAT_WAIT_FOR_THEIR_CONTENT) && $this->actingAs($ownerOn)->get($uri($enabled))->getStatusCode() === 404) {
                $closedAnyway[] = (string) $route->getName();
            }

            $checked++;
        }
    }

    expect($closedAnyway)->toBe([])->and($checked)->toBeGreaterThan(20);
});

it('leaves a read-only company reading the modules it has, and still hides the ones it does not', function (): void {
    $tenant = Tenant::factory()->readOnly()->create();
    resolve(CompanyModules::class)->syncPlan($tenant->id, ['attendance'], CarbonImmutable::now(config('app.display_timezone')));
    $owner = ownerOf($tenant);

    $this->actingAs($owner)->get(route('tenant.attendance.index', $tenant))->assertOk();
    $this->get(route('tenant.seating.index', $tenant))->assertNotFound();
});

it('keeps a union on its own limiter: it never sees the compliance tools and keeps its compensation', function (): void {
    $union = Tenant::factory()->union()->create();
    $owner = memberOf($union, TenantRole::Owner);

    $this->actingAs($owner)->get(route('tenant.compensation.index', $union))->assertOk();
    $this->get(route('tenant.seating.index', $union))->assertNotFound();
});

it('shares the modules the company has so the menu hides the rest', function (): void {
    $tenant = companyWithOnly(['nom035', 'seating']);
    $owner = ownerOf($tenant);

    $this->actingAs($owner)->get(route('tenant.dashboard', $tenant))
        ->assertInertia(fn (Assert $page) => $page->where('tenant.modules', ['nom035', 'seating']));

    $legacy = Tenant::factory()->create();
    $this->actingAs(ownerOf($legacy))->get(route('tenant.dashboard', $legacy))
        ->assertInertia(fn (Assert $page) => $page->where('tenant.modules', ['attendance', 'vacations', 'org_chart', 'nom035', 'nom037', 'seating', 'complaints', 'pulse', 'climate']));
});

it('costs a company with no module rows no query for its modules, and one with rows a single one', function (): void {
    $legacy = Tenant::factory()->create();
    $planned = companyWithOnly(['attendance']);
    $queries = [];
    Illuminate\Support\Facades\DB::listen(function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });
    $count = function (Tenant $tenant) use (&$queries): int {
        $owner = ownerOf($tenant);
        $queries = [];
        $this->actingAs($owner)->get(route('tenant.dashboard', $tenant))->assertOk();

        return count(array_filter($queries, fn (string $sql): bool => str_contains($sql, 'tenant_modules')));
    };

    expect($count($legacy))->toBe(0)->and($count($planned))->toBe(1);
});

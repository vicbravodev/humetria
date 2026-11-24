<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Tenancy\Actions\CreateCompanyRole;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Http\Middleware\RequireRoutePermission;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Support\PermissionCatalog;
use App\Modules\Tenancy\Support\RoutePermissions;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

mutates(RoutePermissions::class, RequireRoutePermission::class);

/**
 * Every page of a company asks for a permission: a role of the company's own with few permissions must not open a page that
 * nobody protected. The account pages (settings.*, logout) and the company switcher live outside the company group, so they
 * have no tenant.* name and need no permission; inside the group the only exception is the page that says the person has
 * no access yet (RoutePermissions::EXEMPT), where a person with no permission at all lands.
 *
 * @return list<LaravelRoute>
 */
function companyGroupRoutes(): array
{
    return array_values(array_filter(
        Route::getRoutes()->getRoutes(),
        fn (LaravelRoute $route): bool => in_array(RequireRoutePermission::class, $route->gatherMiddleware(), true),
    ));
}

it('puts every tenant.* route, and only those, behind the permission of the route', function (): void {
    $tenantRoutes = array_values(array_filter(
        array_map(fn (LaravelRoute $route): string => $route->getName() ?? $route->uri(), Route::getRoutes()->getRoutes()),
        fn (string $name): bool => str_starts_with($name, 'tenant.'),
    ));
    $guarded = array_map(fn (LaravelRoute $route): string => $route->getName() ?? $route->uri(), companyGroupRoutes());

    expect($tenantRoutes)->not->toBeEmpty()
        ->and(array_values(array_diff($tenantRoutes, $guarded)))->toBe([])
        ->and(array_values(array_filter($guarded, fn (string $name): bool => ! str_starts_with($name, 'tenant.'))))->toBe([]);
});

it('gives every company route a permission of the catalog', function (): void {
    $missing = [];

    foreach (companyGroupRoutes() as $route) {
        $permission = RoutePermissions::for((string) $route->getName());

        if (RoutePermissions::isExempt((string) $route->getName())) {
            continue;
        }

        if ($permission === null || PermissionCatalog::find($permission) === null) {
            $missing[] = $route->getName().' => '.($permission ?? 'null');
        }
    }

    expect($missing)->toBe([]);
});

it('exempts only the page without access, and asks no permission for it', function (): void {
    $exempt = array_values(array_filter(
        array_map(fn (LaravelRoute $route): string => (string) $route->getName(), companyGroupRoutes()),
        RoutePermissions::isExempt(...),
    ));

    expect($exempt)->toBe(['tenant.no-access'])
        ->and(RoutePermissions::for('tenant.no-access'))->toBeNull()
        ->and(RoutePermissions::isExempt('tenant.no-access.other'))->toBeFalse()
        ->and(RoutePermissions::isExempt('tenant.made-up.page'))->toBeFalse();
});

it('answers null for a name it does not know, so the middleware closes it', function (): void {
    expect(RoutePermissions::for('tenant.made-up.page'))->toBeNull()
        ->and(RoutePermissions::for(''))->toBeNull();
});

it('asks to manage for a write, except where writing is part of reading', function (): void {
    // Each one is something the reader does as a reader: recording the channeling of Guía I is the job of whoever sees it;
    // a person acknowledges a NOM-037 document; the assigned Comisión or RH attaches or cancels a visit authorization.
    $writesByReaders = [
        'tenant.campaigns.screenings.update',
        'tenant.telework.documents.acknowledge',
        'tenant.telework.visit-authorizations.cancel',
        'tenant.telework.visit-authorizations.document.store',
    ];
    $offenders = [];

    foreach (companyGroupRoutes() as $route) {
        $name = (string) $route->getName();

        if (! in_array('GET', $route->methods(), true) && str_starts_with((string) RoutePermissions::for($name), 'view-') && ! in_array($name, $writesByReaders, true)) {
            $offenders[] = $name;
        }
    }

    expect($offenders)->toBe([]);
});

it('opens to a role with only handle-complaints the complaint cases and closes every other company route', function (): void {
    $tenant = Tenant::factory()->create();
    $owner = ownerOf($tenant);
    $role = app(CreateCompanyRole::class)->handle($tenant, 'Atiende quejas', ['handle-complaints'], null, $owner);
    $person = memberOf($tenant, TenantRole::Viewer);
    $registrar = resolve(PermissionRegistrar::class);
    $registrar->setPermissionsTeamId($tenant->id);
    $person->syncRoles([$role]);
    $person->unsetRelation('roles')->unsetRelation('permissions');
    workIn($tenant);
    $middleware = new RequireRoutePermission;
    $opened = [];

    foreach (companyGroupRoutes() as $route) {
        $request = Request::create('/');
        $request->setRouteResolver(fn (): LaravelRoute => $route);
        $request->setUserResolver(fn (): User => $person);

        try {
            $middleware->handle($request, fn (): Response => response('ok'));
            $opened[] = $route->getName();
        } catch (HttpException $refused) {
            expect($refused->getStatusCode())->toBe(RoutePermissions::refusalStatus((string) $route->getName()));
        }
    }

    expect($opened)->toEqualCanonicalizing([
        'tenant.complaints.cases.acknowledge', 'tenant.complaints.cases.close', 'tenant.complaints.cases.export',
        'tenant.complaints.cases.hold', 'tenant.complaints.cases.messages.store', 'tenant.complaints.cases.notes.store',
        'tenant.complaints.cases.recuse', 'tenant.complaints.cases.reopen', 'tenant.complaints.cases.review',
        'tenant.complaints.cases.share', 'tenant.complaints.cases.show', 'tenant.complaints.centers.options',
        'tenant.complaints.index', 'tenant.complaints.procedure', 'tenant.no-access',
    ]);
});

it('closes the company routes over HTTP to whoever lacks their permission', function (): void {
    $tenant = Tenant::factory()->create();
    $handler = memberOf($tenant, TenantRole::Complaints);

    $this->actingAs($handler)->get(route('tenant.complaints.index', $tenant))->assertOk();
    $this->actingAs($handler)->get(route('tenant.employees.index', $tenant))->assertForbidden();
    $this->actingAs($handler)->get(route('tenant.complaints.channel.show', $tenant))->assertForbidden();
    $this->actingAs($handler)->get(route('tenant.work-centers.index', $tenant))->assertForbidden();
    $this->actingAs(memberOf($tenant, TenantRole::Viewer))->get(route('tenant.members.index', $tenant))->assertForbidden();
});

it('answers 404, not 403, where the page must not be known to exist', function (): void {
    expect(RoutePermissions::refusalStatus('tenant.campaigns.screenings'))->toBe(404)
        ->and(RoutePermissions::refusalStatus('tenant.telework.reviews.index'))->toBe(404)
        ->and(RoutePermissions::refusalStatus('tenant.telework.notices.index'))->toBe(403)
        ->and(RoutePermissions::refusalStatus('tenant.employees.index'))->toBe(403);
});

it('refuses a route whose name it does not know, even to the owner', function (): void {
    $tenant = Tenant::factory()->create();
    $owner = ownerOf($tenant);
    workIn($tenant);
    $route = new LaravelRoute(['GET'], 'admin/{tenant}/made-up', fn (): string => 'ok');
    $route->name('tenant.made-up.page');
    $request = Request::create('/');
    $request->setRouteResolver(fn (): LaravelRoute => $route);
    $request->setUserResolver(fn (): User => $owner);

    expect(fn (): Response => (new RequireRoutePermission)->handle($request, fn (): Response => response('ok')))
        ->toThrow(HttpException::class);
});

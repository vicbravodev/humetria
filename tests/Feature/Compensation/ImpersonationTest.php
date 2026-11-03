<?php

declare(strict_types=1);

use App\Modules\Compensation\Http\Middleware\BlockWhileImpersonating;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

mutates(BlockWhileImpersonating::class);

it('answers 404 during an impersonation', function (): void {
    $tenant = Tenant::factory()->create();
    ownerOf($tenant);

    $this->actingAs(superadmin())->post(route('companies.impersonate', $tenant))->assertRedirect();

    $this->get(route('tenant.compensation.index', $tenant))->assertNotFound();
    // The rest of the account keeps working for the superadmin acting as the owner.
    $this->get(route('tenant.employees.index', $tenant))->assertOk();
});

it('answers 404 during an impersonation of a union too', function (): void {
    $union = Tenant::factory()->union()->create();
    ownerOf($union);

    $this->actingAs(superadmin())->post(route('companies.impersonate', $union))->assertRedirect();

    $this->get(route('tenant.compensation.index', $union))->assertNotFound();
});

it('puts the block on every tenant.compensation route, so a new one cannot skip it', function (): void {
    $routes = array_filter(
        Route::getRoutes()->getRoutes(),
        fn (LaravelRoute $route): bool => Str::startsWith((string) $route->getName(), 'tenant.compensation.'),
    );

    expect($routes)->not->toBeEmpty();

    foreach ($routes as $route) {
        expect($route->gatherMiddleware())->toContain(BlockWhileImpersonating::class);
    }
});

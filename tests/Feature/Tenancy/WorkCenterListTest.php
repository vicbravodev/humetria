<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Tenancy\Actions\ListWorkCenters;
use App\Modules\Tenancy\Enums\MexicanState;
use App\Modules\Tenancy\Http\Controllers\WorkCenterController;
use App\Modules\Tenancy\Http\Middleware\ResolveTenant;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Inertia\Testing\AssertableInertia as Assert;

mutates(WorkCenterController::class, ListWorkCenters::class, ResolveTenant::class);

it('lives at /admin/{slug}/work-centers', function (): void {
    expect(route('tenant.work-centers.index', ['tenant' => 'acme'], absolute: false))->toBe('/admin/acme/work-centers');
});

it('sends guests to the login', function (): void {
    $tenant = Tenant::factory()->create();

    $this->get(route('tenant.work-centers.index', $tenant))->assertRedirect(route('login'));
});

it('lists the active work centers of the company by name, with their address in one line', function (): void {
    $tenant = Tenant::factory()->create();
    WorkCenter::factory()->for($tenant)->create(['name' => 'Planta Querétaro', 'street' => 'Av. 5 de Febrero', 'exterior_number' => '1200', 'interior_number' => 'Nave 3', 'neighborhood' => 'Zona Industrial', 'postal_code' => '76120', 'municipality' => 'Querétaro', 'state' => MexicanState::Queretaro, 'main_activity' => 'Manufactura de autopartes']);
    WorkCenter::factory()->for($tenant)->create(['name' => 'Oficinas Centrales', 'interior_number' => null, 'street' => 'Reforma', 'exterior_number' => '222', 'neighborhood' => 'Juárez', 'postal_code' => '06600', 'municipality' => 'Cuauhtémoc', 'state' => MexicanState::MexicoCity]);

    $this->actingAs(ownerOf($tenant))
        ->get(route('tenant.work-centers.index', $tenant))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenancy/work-centers')
            ->where('view', 'active')
            ->has('workCenters', 2)
            ->where('workCenters.0.name', 'Oficinas Centrales')
            ->where('workCenters.0.address', 'Reforma 222, col. Juárez, C.P. 06600, Cuauhtémoc, Ciudad de México')
            ->where('workCenters.1', fn ($row): bool => collect($row)->except('id')->all() === [
                'name' => 'Planta Querétaro',
                'street' => 'Av. 5 de Febrero',
                'exterior_number' => '1200',
                'interior_number' => 'Nave 3',
                'neighborhood' => 'Zona Industrial',
                'postal_code' => '76120',
                'municipality' => 'Querétaro',
                'state' => 'QUE',
                'main_activity' => 'Manufactura de autopartes',
                'address' => 'Av. 5 de Febrero 1200 int. Nave 3, col. Zona Industrial, C.P. 76120, Querétaro, Querétaro',
                'archived_on' => null,
            ]));
});

it('keeps archived work centers out of the list and counts both', function (): void {
    $tenant = Tenant::factory()->create();
    WorkCenter::factory()->for($tenant)->create(['name' => 'Activo']);
    WorkCenter::factory()->for($tenant)->archived()->count(2)->create();

    $this->actingAs(ownerOf($tenant))
        ->get(route('tenant.work-centers.index', $tenant))
        ->assertInertia(fn (Assert $page) => $page
            ->where('counts', ['active' => 1, 'archived' => 2])
            ->where('workCenters', fn ($rows): bool => collect($rows)->pluck('name')->all() === ['Activo']));
});

it('lists only the archived ones, with the day each was archived, when asked', function (): void {
    $this->travelTo('2026-11-03 02:00:00'); // Still 2 Nov in Mexico City.
    $tenant = Tenant::factory()->create();
    WorkCenter::factory()->for($tenant)->create(['name' => 'Activo']);
    WorkCenter::factory()->for($tenant)->archived()->create(['name' => 'Cerrado']);

    $this->actingAs(ownerOf($tenant))
        ->get(route('tenant.work-centers.index', ['tenant' => $tenant, 'archived' => 1]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('view', 'archived')
            ->has('workCenters', 1)
            ->where('workCenters.0.name', 'Cerrado')
            ->where('workCenters.0.archived_on', '2 nov 2026'));
});

it('never shows the work centers of another company', function (): void {
    $tenant = Tenant::factory()->create();
    WorkCenter::factory()->create(['name' => 'Ajeno']);

    $this->actingAs(ownerOf($tenant))
        ->get(route('tenant.work-centers.index', $tenant))
        ->assertInertia(fn (Assert $page) => $page
            ->has('workCenters', 0)
            ->where('counts', ['active' => 0, 'archived' => 0]));
});

it('offers the 32 states in alphabetical order', function (): void {
    $tenant = Tenant::factory()->create();

    $this->actingAs(ownerOf($tenant))
        ->get(route('tenant.work-centers.index', $tenant))
        ->assertInertia(fn (Assert $page) => $page
            ->has('states', 32)
            ->where('states.0', ['value' => 'AGU', 'label' => 'Aguascalientes'])
            ->where('states.6', ['value' => 'CMX', 'label' => 'Ciudad de México'])
            ->where('states.31', ['value' => 'ZAC', 'label' => 'Zacatecas']));
});

it('answers 404 to a member of another company and to a superadmin who is not a member', function (): void {
    $tenant = Tenant::factory()->create();

    $this->actingAs(ownerOf(Tenant::factory()->create()))
        ->get(route('tenant.work-centers.index', $tenant))
        ->assertNotFound();

    $this->actingAs(superadmin())
        ->get(route('tenant.work-centers.index', $tenant))
        ->assertNotFound();

    $this->actingAs(User::factory()->create())
        ->get('/admin/does-not-exist/work-centers')
        ->assertNotFound();
});

it('shows the blocked page for a suspended company', function (): void {
    $tenant = Tenant::factory()->deactivated()->create();

    $this->actingAs(ownerOf($tenant))
        ->get(route('tenant.work-centers.index', $tenant))
        ->assertInertia(fn (Assert $page) => $page->component('tenancy/blocked'));
});

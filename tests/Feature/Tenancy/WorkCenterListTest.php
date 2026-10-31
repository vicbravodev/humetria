<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Support\CompanyEvaluationSchedule;
use App\Modules\Organization\Models\Area;
use App\Modules\Organization\Models\Employee;
use App\Modules\Organization\Support\CompanyHeadcount;
use App\Modules\Tenancy\Actions\ListWorkCenters;
use App\Modules\Tenancy\Enums\MexicanState;
use App\Modules\Tenancy\Http\Controllers\WorkCenterController;
use App\Modules\Tenancy\Http\Middleware\ResolveTenant;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Inertia\Testing\AssertableInertia as Assert;

mutates(WorkCenterController::class, ListWorkCenters::class, ResolveTenant::class, CompanyHeadcount::class, CompanyEvaluationSchedule::class);

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
            ->where('workCenters.total', 2)
            ->where('workCenters.data.0.name', 'Oficinas Centrales')
            ->where('workCenters.data.0.address', 'Reforma 222, col. Juárez, C.P. 06600, Cuauhtémoc, Ciudad de México')
            ->where('workCenters.data.1', fn ($row): bool => collect($row)->except('id')->all() === [
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
                'employees' => 0,
                'guides' => [],
                'areas' => [],
                'more_areas' => 0,
                'evaluation' => null,
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
            ->where('workCenters.data', fn ($rows): bool => collect($rows)->pluck('name')->all() === ['Activo']));
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
            ->has('workCenters.data', 1)
            ->where('workCenters.data.0.name', 'Cerrado')
            ->where('workCenters.data.0.archived_on', '2 nov 2026'));
});

it('pages the work centers 25 at a time, keeping the view', function (): void {
    $tenant = Tenant::factory()->create();

    foreach (range(1, 26) as $index) {
        WorkCenter::factory()->for($tenant)->archived()->create(['name' => sprintf('Centro %02d', $index)]);
    }

    $this->actingAs(ownerOf($tenant))
        ->get(route('tenant.work-centers.index', ['tenant' => $tenant, 'archived' => 1, 'page' => 2]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('counts', ['active' => 0, 'archived' => 26])
            ->where('workCenters.total', 26)
            ->has('workCenters.data', 1)
            ->where('workCenters.data.0.name', 'Centro 26')
            ->where('workCenters.prev_page_url', fn (string $url): bool => str_contains($url, 'archived=1')));
});

it('never shows the work centers of another company', function (): void {
    $tenant = Tenant::factory()->create();
    WorkCenter::factory()->create(['name' => 'Ajeno']);

    $this->actingAs(ownerOf($tenant))
        ->get(route('tenant.work-centers.index', $tenant))
        ->assertInertia(fn (Assert $page) => $page
            ->has('workCenters.data', 0)
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

it('counts each work center\'s active employees and says which NOM-035 guides apply', function (): void {
    $tenant = Tenant::factory()->create();
    $small = WorkCenter::factory()->for($tenant)->create(['name' => 'A Chico']);
    $medium = WorkCenter::factory()->for($tenant)->create(['name' => 'B Mediano']);
    WorkCenter::factory()->for($tenant)->create(['name' => 'C Vacío']);
    Employee::factory()->count(15)->create(['tenant_id' => $tenant->id, 'work_center_id' => $small->id]);
    Employee::factory()->count(16)->create(['tenant_id' => $tenant->id, 'work_center_id' => $medium->id]);
    Employee::factory()->deactivated()->count(3)->create(['tenant_id' => $tenant->id, 'work_center_id' => $small->id]);
    Employee::factory()->count(2)->create();

    $this->actingAs(ownerOf($tenant))
        ->get(route('tenant.work-centers.index', $tenant))
        ->assertInertia(fn (Assert $page) => $page
            ->where('workCenters.data', fn ($rows): bool => collect($rows)->map(fn ($row): array => [$row['name'], $row['employees'], $row['guides']])->all() === [
                ['A Chico', 15, ['I']],
                ['B Mediano', 16, ['I', 'II']],
                ['C Vacío', 0, []],
            ]));
});

it('shows each work center\'s five largest areas with their active people, and how many more there are', function (): void {
    $tenant = Tenant::factory()->create();
    $plant = WorkCenter::factory()->for($tenant)->create(['name' => 'A Planta']);
    $office = WorkCenter::factory()->for($tenant)->create(['name' => 'B Oficinas']);
    $areas = collect(['Producción' => 6, 'Calidad' => 3, 'Almacén' => 3, 'Compras' => 2, 'Mantenimiento' => 1, 'Seguridad' => 1])
        ->map(fn (int $people, string $name): array => [Area::factory()->create(['tenant_id' => $tenant->id, 'name' => $name]), $people]);
    $areas->each(fn (array $area) => Employee::factory()->count($area[1])->create(['tenant_id' => $tenant->id, 'work_center_id' => $plant->id, 'area_id' => $area[0]->id]));
    Employee::factory()->deactivated()->count(4)->create(['tenant_id' => $tenant->id, 'work_center_id' => $plant->id, 'area_id' => $areas['Seguridad'][0]->id]);
    Employee::factory()->count(2)->create(['tenant_id' => $tenant->id, 'work_center_id' => $office->id, 'area_id' => $areas['Compras'][0]->id]);

    $this->actingAs(ownerOf($tenant))
        ->get(route('tenant.work-centers.index', $tenant))
        ->assertInertia(fn (Assert $page) => $page
            ->where('workCenters.data.0.areas', [
                ['name' => 'Producción', 'employees' => 6],
                ['name' => 'Almacén', 'employees' => 3],
                ['name' => 'Calidad', 'employees' => 3],
                ['name' => 'Compras', 'employees' => 2],
                ['name' => 'Mantenimiento', 'employees' => 1],
            ])
            ->where('workCenters.data.0.more_areas', 1)
            ->where('workCenters.data.1.areas', [['name' => 'Compras', 'employees' => 2]])
            ->where('workCenters.data.1.more_areas', 0));
});

it('says where each work center stands with its NOM-035 evaluations', function (): void {
    $this->travelTo('2026-11-03 18:00:00');
    $tenant = Tenant::factory()->create();
    $centers = collect(['A En curso', 'B Borrador', 'C Al día', 'D Vencida', 'E Sin evaluar'])
        ->mapWithKeys(fn (string $name): array => [$name => WorkCenter::factory()->for($tenant)->create(['name' => $name])]);
    Campaign::factory()->active('2026-11-10')->create(['tenant_id' => $tenant->id, 'work_center_id' => $centers['A En curso']->id]);
    Campaign::factory()->closed()->create(['tenant_id' => $tenant->id, 'work_center_id' => $centers['A En curso']->id, 'closed_at' => '2025-01-10 18:00:00']);
    Campaign::factory()->create(['tenant_id' => $tenant->id, 'work_center_id' => $centers['B Borrador']->id]);
    Campaign::factory()->closed()->create(['tenant_id' => $tenant->id, 'work_center_id' => $centers['C Al día']->id, 'closed_at' => '2024-02-01 18:00:00']);
    Campaign::factory()->closed()->create(['tenant_id' => $tenant->id, 'work_center_id' => $centers['C Al día']->id, 'closed_at' => '2026-10-29 18:00:00']);
    Campaign::factory()->closed()->create(['tenant_id' => $tenant->id, 'work_center_id' => $centers['D Vencida']->id, 'closed_at' => '2024-11-02 18:00:00']);
    Campaign::factory()->trauma()->create(['tenant_id' => $tenant->id, 'work_center_id' => $centers['E Sin evaluar']->id]);

    $this->actingAs(ownerOf($tenant))
        ->get(route('tenant.work-centers.index', $tenant))
        ->assertInertia(fn (Assert $page) => $page
            ->where('workCenters.data', fn ($rows): bool => collect($rows)->map(fn ($row): array => [$row['name'], $row['evaluation']])->all() === [
                ['A En curso', ['state' => 'running', 'day' => '10 nov 2026']],
                ['B Borrador', ['state' => 'draft', 'day' => null]],
                ['C Al día', ['state' => 'due', 'day' => '29 oct 2028']],
                ['D Vencida', ['state' => 'overdue', 'day' => '2 nov 2026']],
                ['E Sin evaluar', null],
            ]));
});

it('keeps saying the last evaluation is overdue while the next one is only a draft (C-26)', function (): void {
    $this->travelTo('2026-11-03 18:00:00');
    $tenant = Tenant::factory()->create();
    $center = WorkCenter::factory()->for($tenant)->create();
    Campaign::factory()->closed()->create(['tenant_id' => $tenant->id, 'work_center_id' => $center->id, 'closed_at' => '2024-11-02 18:00:00']);
    Campaign::factory()->create(['tenant_id' => $tenant->id, 'work_center_id' => $center->id]);

    $this->actingAs(ownerOf($tenant))
        ->get(route('tenant.work-centers.index', $tenant))
        ->assertInertia(fn (Assert $page) => $page->where('workCenters.data.0.evaluation', ['state' => 'overdue', 'day' => '2 nov 2026']));
});

it('never reads the areas nor the evaluations of another company', function (): void {
    $tenant = Tenant::factory()->create();
    $center = WorkCenter::factory()->for($tenant)->create();
    $other = Tenant::factory()->create();
    Employee::factory()->count(2)->create(['tenant_id' => $other->id]);
    Campaign::factory()->active()->create(['tenant_id' => $other->id]);

    $this->actingAs(ownerOf($tenant))
        ->get(route('tenant.work-centers.index', $tenant))
        ->assertInertia(fn (Assert $page) => $page
            ->where('workCenters.data.0.id', $center->id)
            ->where('workCenters.data.0.areas', [])
            ->where('workCenters.data.0.evaluation', null));
});

<?php

declare(strict_types=1);

use App\Modules\Organization\Actions\ListEmployees;
use App\Modules\Organization\Enums\Gender;
use App\Modules\Organization\Http\Controllers\EmployeeController;
use App\Modules\Organization\Models\Area;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Inertia\Testing\AssertableInertia as Assert;

mutates(EmployeeController::class, ListEmployees::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->owner = ownerOf($this->tenant);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Norte']);
    $this->office = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Oficinas']);
    $this->production = Area::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Producción']);
    $this->sales = Area::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Ventas']);
});

function employeeIn(Tenant $tenant, WorkCenter $workCenter, Area $area, array $attributes = []): Employee
{
    return Employee::factory()->create(['tenant_id' => $tenant->id, 'work_center_id' => $workCenter->id, 'area_id' => $area->id, ...$attributes]);
}

it('lives at /admin/{slug}/employees', function (): void {
    expect(route('tenant.employees.index', ['tenant' => 'acme'], absolute: false))->toBe('/admin/acme/employees');
});

it('lists the active employees by name, with their center and area', function (): void {
    employeeIn($this->tenant, $this->plant, $this->production, ['name' => 'Zoe Ruiz']);
    $ana = employeeIn($this->tenant, $this->office, $this->sales, ['name' => 'Ana López', 'email' => 'ana@acme.mx', 'mobile' => '5512345678', 'gender' => Gender::Female, 'job_title' => 'Vendedora', 'employee_number' => 'A-1']);
    employeeIn($this->tenant, $this->plant, $this->production, ['name' => 'Baja Dada'])->update(['deactivated_at' => now()]);
    Employee::factory()->create(['name' => 'De Otra Empresa']);

    $this->actingAs($this->owner)
        ->get(route('tenant.employees.index', $this->tenant))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('organization/employees')
            ->where('employees.total', 2)
            ->where('employees.data', fn ($rows): bool => collect($rows)->pluck('name')->all() === ['Ana López', 'Zoe Ruiz'])
            ->where('employees.data.0', [
                'id' => $ana->id, 'name' => 'Ana López', 'email' => 'ana@acme.mx', 'mobile' => '5512345678',
                'gender' => 'female', 'gender_label' => 'Mujer', 'work_center_id' => $this->office->id, 'work_center' => 'Oficinas',
                'area' => 'Ventas', 'job_title' => 'Vendedora', 'employee_number' => 'A-1', 'deactivated_on' => null,
            ])
            ->where('filters', ['q' => '', 'work_center' => null, 'area' => null, 'status' => 'active'])
            // Work centers and areas are searched on the server as they are chosen, never sent whole.
            ->missing('workCenters')
            ->missing('areas')
            ->where('genders', [['value' => 'female', 'label' => 'Mujer'], ['value' => 'male', 'label' => 'Hombre'], ['value' => 'other', 'label' => 'Otro / prefiero no decir']]));
});

it('searches by name, email or employee number, ignoring case and accents', function (string $q, array $names): void {
    employeeIn($this->tenant, $this->plant, $this->production, ['name' => 'Ana López', 'email' => 'ana@acme.mx', 'employee_number' => 'N-77']);
    employeeIn($this->tenant, $this->plant, $this->production, ['name' => 'Beto Díaz', 'email' => 'beto@otro.mx']);

    $this->actingAs($this->owner)
        ->get(route('tenant.employees.index', ['tenant' => $this->tenant, 'q' => $q]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('employees.data', fn ($rows): bool => collect($rows)->pluck('name')->all() === $names)
            ->where('filters.q', trim($q)));
})->with([
    'name' => ['LÓPEZ', ['Ana López']],
    'email' => [' acme.mx ', ['Ana López']],
    'number' => ['n-77', ['Ana López']],
    'accents' => ['diaz', ['Beto Díaz']],
    'nothing' => ['zzz', []],
    'a wildcard is literal' => ['%', []],
]);

it('filters by work center, area and status, together', function (): void {
    employeeIn($this->tenant, $this->plant, $this->production, ['name' => 'Planta Producción']);
    employeeIn($this->tenant, $this->plant, $this->sales, ['name' => 'Planta Ventas']);
    employeeIn($this->tenant, $this->office, $this->production, ['name' => 'Oficina Producción']);
    employeeIn($this->tenant, $this->plant, $this->production, ['name' => 'Planta Baja', 'deactivated_at' => now()]);

    $names = fn (array $query): array => collect($this->actingAs($this->owner)
        ->get(route('tenant.employees.index', ['tenant' => $this->tenant, ...$query]))
        ->viewData('page')['props']['employees']['data'])->pluck('name')->all();

    expect($names(['work_center' => $this->plant->id]))->toBe(['Planta Producción', 'Planta Ventas'])
        ->and($names(['work_center' => $this->plant->id, 'area' => $this->production->id]))->toBe(['Planta Producción'])
        ->and($names(['status' => 'inactive']))->toBe(['Planta Baja'])
        ->and($names(['area' => 'not-a-uuid', 'work_center' => 'x', 'status' => 'nope']))->toBe(['Oficina Producción', 'Planta Producción', 'Planta Ventas']);

    $this->actingAs($this->owner)
        ->get(route('tenant.employees.index', ['tenant' => $this->tenant, 'work_center' => $this->plant->id, 'area' => $this->production->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.work_center', ['value' => $this->plant->id, 'label' => 'Planta Norte'])
            ->where('filters.area', ['value' => $this->production->id, 'label' => 'Producción']));
});

it('pages 25 at a time', function (): void {
    foreach (range(1, 26) as $i) {
        employeeIn($this->tenant, $this->plant, $this->production, ['name' => sprintf('Persona %02d', $i)]);
    }

    $this->actingAs($this->owner)
        ->get(route('tenant.employees.index', ['tenant' => $this->tenant, 'page' => 2]))
        ->assertInertia(fn (Assert $page) => $page->has('employees.data', 1)->where('employees.data.0.name', 'Persona 26'));
});

it('lists the page with a fixed number of queries', function (): void {
    foreach (range(1, 10) as $i) {
        employeeIn($this->tenant, $i % 2 === 0 ? $this->plant : $this->office, $i % 3 === 0 ? $this->sales : $this->production);
    }
    $owner = $this->owner->fresh();

    // Superadmin check, company, membership, role, permission cache (2), the user's permissions and roles there, work
    // centers, count, page, the page's areas and the user's first companies with their count: none per row.
    $this->expectsDatabaseQueryCount(15);

    $this->actingAs($owner)->get(route('tenant.employees.index', $this->tenant))->assertOk();
});

it('is for those who manage the organization, never a viewer', function (): void {
    $this->actingAs(memberOf($this->tenant, TenantRole::Hr))->get(route('tenant.employees.index', $this->tenant))->assertOk();
    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer))->get(route('tenant.employees.index', $this->tenant))->assertForbidden();
});

it('answers 404 outside the company', function (): void {
    $this->actingAs(ownerOf(Tenant::factory()->create()))->get(route('tenant.employees.index', $this->tenant))->assertNotFound();
});

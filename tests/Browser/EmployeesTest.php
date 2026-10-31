<?php

declare(strict_types=1);

use App\Modules\Organization\Models\Area;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Pest\Browser\Api\AwaitableWebpage;

$looks = [
    'light desktop' => [false, false],
    'dark desktop' => [true, false],
    'light mobile' => [false, true],
    'dark mobile' => [true, true],
];

/** Signs in the owner of Acme with two work centers, two areas and, unless told otherwise, three employees (one without email). */
function acmeWithEmployees(bool $employees = true): Tenant
{
    signInToPanel();
    $tenant = Tenant::query()->where('slug', 'acme')->sole();
    $plant = WorkCenter::factory()->for($tenant)->create(['name' => 'Planta Norte']);
    $office = WorkCenter::factory()->for($tenant)->create(['name' => 'Oficinas']);
    $production = Area::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Producción']);
    $sales = Area::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Ventas']);

    if ($employees) {
        Employee::factory()->create(['tenant_id' => $tenant->id, 'work_center_id' => $plant->id, 'area_id' => $production->id, 'name' => 'Ana López', 'email' => 'ana@acme.mx', 'mobile' => '5512345678']);
        Employee::factory()->create(['tenant_id' => $tenant->id, 'work_center_id' => $office->id, 'area_id' => $sales->id, 'name' => 'Beto Díaz', 'email' => 'beto@acme.mx']);
        Employee::factory()->create(['tenant_id' => $tenant->id, 'work_center_id' => $plant->id, 'area_id' => $sales->id, 'name' => 'Carla Ruiz', 'email' => null, 'mobile' => '5587654321']);
    }

    return $tenant;
}

it('invites a company without employees to add or upload them', function (bool $dark, bool $mobile): void {
    acmeWithEmployees(employees: false);

    visitPage('organization/employees', '/admin/acme/employees', $dark, $mobile)
        ->assertSee('Aún no hay empleados')
        ->assertSee('Cargar CSV');
})->with($looks);

it('lists the employees with their contact, center and area', function (bool $dark, bool $mobile): void {
    acmeWithEmployees();

    visitPage('organization/employees', '/admin/acme/employees', $dark, $mobile)
        ->assertSee('3 personas activas en 2 centros de trabajo.')
        ->assertSee('Ana López')
        ->assertSee('ana@acme.mx')
        ->assertSee('55 8765 4321')
        ->assertSee('Planta Norte')
        ->assertSee('1 persona no tiene correo');
})->with($looks);

it('fits the search, the filters and the status tabs inside the page at every width', function (): void {
    acmeWithEmployees();
    $page = visitPage('organization/employees', '/admin/acme/employees');

    foreach ([375, 768, 1024, 1280, 1440] as $width) {
        resizeTo($page, $width)->assertScript(spilledOut(), '');
    }
});

it('shows only the people the survey email cannot reach, and everyone again', function (): void {
    acmeWithEmployees();

    visit('/admin/acme/employees')
        ->click('Ver quién es')
        ->assertQueryStringHas('without_email', '1')
        ->assertSee('Carla Ruiz')
        ->assertDontSee('Ana López')
        ->click('Ver a todas')
        ->assertSee('Ana López')
        ->assertNoJavaScriptErrors();
});

it('searches as the person types, once they pause', function (): void {
    acmeWithEmployees();

    visit('/admin/acme/employees')
        ->type('[aria-label="Buscar por nombre, correo o número"]', 'beto')
        ->assertDontSee('Ana López')
        ->assertSee('Beto Díaz')
        ->assertQueryStringHas('q', 'beto')
        ->assertNoJavaScriptErrors();
});

it('filters by work center and says when nobody matches', function (): void {
    acmeWithEmployees();

    visit('/admin/acme/employees')
        ->click('#filter-work-center')
        ->type('[role="combobox"][aria-controls="filter-work-center-options"]', 'oficinas')
        ->assertSeeIn('#filter-work-center-options', 'Oficinas')
        ->keys('[role="combobox"][aria-controls="filter-work-center-options"]', 'Enter')
        ->assertSee('Beto Díaz')
        ->assertDontSee('Ana López')
        ->type('[aria-label="Buscar por nombre, correo o número"]', 'zzz')
        ->assertSee('Ningún empleado coincide')
        ->assertNoJavaScriptErrors();
});

it('adds an employee, creating their area as it is typed', function (): void {
    acmeWithEmployees(employees: false);

    visit('/admin/acme/employees')
        ->click('Dar de alta')
        ->type('name', 'Diego Luna')
        ->type('mobile', '55 8765 4321')
        ->click('#employee-work_center_id')
        ->type('[role="combobox"][aria-controls="employee-work_center_id-options"]', 'planta')
        ->assertSeeIn('#employee-work_center_id-options', 'Planta')
        ->keys('[role="combobox"][aria-controls="employee-work_center_id-options"]', 'Enter')
        ->click('#employee-area')
        ->type('[role="combobox"][aria-controls="employee-area-options"]', 'Logística')
        ->assertSee('Agregar «Logística»')
        ->keys('[role="combobox"][aria-controls="employee-area-options"]', 'Enter')
        ->type('job_title', 'Chofer')
        ->click('Hombre')
        ->click('[data-slot="sheet-content"] button[type="submit"]')
        ->assertSee('Empleado dado de alta.')
        ->assertSee('Diego Luna')
        ->assertSee('Logística')
        ->assertNoJavaScriptErrors();
});

it('passes the checks with the employee panel open', function (bool $dark, bool $mobile): void {
    acmeWithEmployees();

    visitPage('organization/employees', '/admin/acme/employees', $dark, $mobile, fn (AwaitableWebpage $page) => $page
        ->click('Dar de alta')
        ->assertSee('Con al menos uno de los dos basta.'));
})->with($looks);

it('deactivates an employee and reactivates them from the deactivated ones', function (): void {
    acmeWithEmployees();

    visit('/admin/acme/employees')
        ->click('[aria-label="Acciones de Ana López"]')->click('Dar de baja')
        ->assertSee('¿Dar de baja a Ana López?')
        ->press('Dar de baja')
        ->assertSee('Empleado dado de baja.')
        ->assertDontSee('Ana López')
        ->click('nav[aria-label="Estado de las personas"] a:has-text("Bajas")')
        ->assertSee('Ana López')
        ->click('[aria-label="Acciones de Ana López"]')->click('Reactivar')
        ->assertSee('Empleado reactivado.')
        ->assertNoJavaScriptErrors();
});

it('shows Empleados in the sidebar to owner and hr, never to a viewer', function (): void {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    $this->actingAs(memberOf($tenant, TenantRole::Viewer));

    visit('/admin/acme')->assertSee('Centros de trabajo')->assertDontSee('Empleados');

    $this->actingAs(memberOf($tenant, TenantRole::Hr));

    visit('/admin/acme')->click('Empleados')->assertPathIs('/admin/acme/employees');
});

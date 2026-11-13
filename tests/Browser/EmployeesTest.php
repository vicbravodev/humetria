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

it('sets the direct manager of an employee from their panel', function (): void {
    acmeWithEmployees();

    visit('/admin/acme/employees')
        ->click('[aria-label="Acciones de Ana López"]')->click('Editar')
        ->assertSee('Déjalo vacío si nadie está por encima.')
        ->assertSee('Antigüedad reconocida')
        ->click('#employee-manager_id')
        ->type('[role="combobox"][aria-controls="employee-manager_id-options"]', 'carla')
        ->assertSeeIn('#employee-manager_id-options', 'Carla Ruiz')
        ->keys('[role="combobox"][aria-controls="employee-manager_id-options"]', 'Enter')
        ->click('[data-slot="sheet-content"] button[type="submit"]')
        ->assertSee('Empleado actualizado.')
        ->assertSee('Reporta a Carla Ruiz')
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
        ->assertSee('Los días de vacaciones pendientes se calculan a esta fecha.')
        ->assertDisabled('[role="dialog"] button[type="submit"]')
        ->type('#terminated-on', now(config()->string('app.display_timezone'))->subWeek()->toDateString())
        ->click('#deactivation-reason')->click('[role="option"]:has-text("Renuncia")')
        ->press('Dar de baja')
        ->assertSee('Empleado dado de baja.')
        ->assertDontSee('Ana López')
        ->click('nav[aria-label="Estado de las personas"] a:has-text("Bajas")')
        ->assertSee('Ana López')
        ->click('[aria-label="Acciones de Ana López"]')->click('Reactivar')
        ->assertSee('Empleado reactivado.')
        ->assertNoJavaScriptErrors();
});

it('asks who takes the reports of a manager before deactivating them', function (): void {
    $tenant = acmeWithEmployees();
    $ana = Employee::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('name', 'Ana López')->sole();
    Employee::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('name', 'Beto Díaz')->update(['manager_id' => $ana->id]);

    visit('/admin/acme/employees')
        ->click('[aria-label="Acciones de Ana López"]')->click('Dar de baja')
        ->assertSee('Ana López tiene 1 reporte directo. Elige a quién le reportarán.')
        ->assertDisabled('[role="dialog"] button[type="submit"]')
        ->click('#deactivation-reason')->click('[role="option"]:has-text("Mutuo acuerdo")')
        ->assertDisabled('[role="dialog"] button[type="submit"]')
        ->click('#employee-reassign_to')
        ->type('[role="combobox"][aria-controls="employee-reassign_to-options"]', 'carla')
        ->assertSeeIn('#employee-reassign_to-options', 'Carla Ruiz')
        ->keys('[role="combobox"][aria-controls="employee-reassign_to-options"]', 'Enter')
        ->click('[role="dialog"] button[type="submit"]')
        ->assertSee('Empleado dado de baja.')
        ->assertSee('Reporta a Carla Ruiz')
        ->assertNoJavaScriptErrors();
});

it('shows Empleados in the sidebar to owner and hr, never to a viewer', function (): void {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    $this->actingAs(memberOf($tenant, TenantRole::Viewer));

    visit('/admin/acme')->assertSee('Centros de trabajo')->assertDontSee('Empleados');

    $this->actingAs(memberOf($tenant, TenantRole::Hr));

    visit('/admin/acme')->click('Empleados')->assertPathIs('/admin/acme/employees');
});

// E-08: RH sends a person the link of their portal from the row, and is told when there is no email to send it to.
it('sends a person the link of their portal from their row, or says why it cannot', function (): void {
    Illuminate\Support\Facades\Notification::fake();
    acmeWithEmployees();

    visit('/admin/acme/employees')
        ->click('[aria-label="Acciones de Ana López"]')->click('Enviar enlace a su portal')
        ->assertSee('Le enviamos su enlace por correo.')
        ->click('[aria-label="Acciones de Carla Ruiz"]')->click('Enviar enlace a su portal')
        ->assertSee('No tiene correo registrado: agrégalo en su ficha para enviarle el enlace.')
        ->assertNoJavaScriptErrors();
});

it('finds a person from the ⌘K palette and opens Empleados narrowed to them', function (): void {
    acmeWithEmployees();

    visit('/admin/acme/employees')
        // The top bar exists only once React mounted the app: the shortcut is listening by then.
        ->assertVisible('@command-palette')
        ->assertScript('() => { window.dispatchEvent(new KeyboardEvent("keydown", { key: "k", metaKey: true })); return true; }')
        ->type('#command-search', 'ló')
        ->assertSeeIn('[role="listbox"] [role="group"]', 'Personas')
        ->assertSee('Ana López')
        ->keys('#command-search', 'Enter')
        ->assertPathIs('/admin/acme/employees')
        ->assertScript('() => new URL(location.href).searchParams.get("q")', 'Ana López')
        ->assertNoJavaScriptErrors();
});

it('offers no people in the ⌘K palette to whoever cannot manage the organization', function (): void {
    $tenant = acmeWithEmployees();
    $this->actingAs(memberOf($tenant, TenantRole::Viewer));

    visit('/admin/acme')
        // The top bar exists only once React mounted the app: the shortcut is listening by then.
        ->assertVisible('@command-palette')
        ->assertScript('() => { window.dispatchEvent(new KeyboardEvent("keydown", { key: "k", metaKey: true })); return true; }')
        ->type('#command-search', 'ló')
        ->assertDontSee('Ana López');
});

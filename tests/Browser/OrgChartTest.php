<?php

declare(strict_types=1);

use App\Modules\Organization\Models\Area;
use App\Modules\Organization\Models\Employee;
use App\Modules\Telework\Actions\IssuePortalLink;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;

$looks = [
    'light desktop' => [false, false],
    'dark desktop' => [true, false],
    'light mobile' => [false, true],
    'dark mobile' => [true, true],
];

/**
 * Signs in the owner of Acme, whose director leads two managers: Gerardo, with Ana and Beto, and Sofía, with
 * nobody. Without `$lines`, the same people with no manager at all.
 *
 * @return array<string, Employee>
 */
function acmeOrgChart(bool $lines = true): array
{
    signInToPanel();
    $tenant = Tenant::query()->where('slug', 'acme')->sole();
    $place = [
        'tenant_id' => $tenant->id,
        'work_center_id' => WorkCenter::factory()->for($tenant)->create(['name' => 'Planta Norte'])->id,
        'area_id' => Area::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Producción'])->id,
    ];
    $person = fn (string $name, string $title, ?Employee $manager): Employee => Employee::factory()->create([...$place, 'name' => $name, 'job_title' => $title, 'manager_id' => $lines ? $manager?->id : null]);

    $dir = $person('Diana Torres', 'Directora general', null);
    $gerardo = $person('Gerardo Paz', 'Gerente de planta', $dir);

    return [
        'dir' => $dir,
        'gerardo' => $gerardo,
        'sofia' => $person('Sofía Luna', 'Gerente de ventas', $dir),
        'ana' => $person('Ana López', 'Operadora', $gerardo),
        'beto' => $person('Beto Díaz', 'Operador', $gerardo),
    ];
}

it('shows who is at the top, and their reports when they are opened', function (bool $dark, bool $mobile): void {
    acmeOrgChart();

    visitPage('organization/org-chart', '/admin/acme/org-chart', $dark, $mobile)
        ->assertSee('5 personas activas, 4 con jefe directo.')
        ->assertSee('Diana Torres')
        ->assertSee('Directora general')
        ->assertSee('2 directos · 4 en total')
        ->assertSeeIn('section[aria-label="Indicadores"]', 'Niveles')
        ->assertSeeIn('section[aria-label="Indicadores"]', 'Mediana de reportes directos')
        ->assertSeeIn('section[aria-label="Indicadores"]', '2.0')
        ->assertSeeIn('section[aria-label="Indicadores"]', 'Es una señal, no una regla.')
        ->assertDontSee('Gerardo Paz')
        ->click('[aria-label="Ver los reportes de Diana Torres"]')
        ->assertSee('Gerardo Paz')
        ->assertSee('Sofía Luna')
        ->assertSee('Revisar: pocos reportes')
        ->assertDontSee('Ana López')
        ->assertNoAccessibilityIssues(1)
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');
})->with($looks);

it('invites a company where nobody has a manager to assign them', function (bool $dark, bool $mobile): void {
    acmeOrgChart(lines: false);

    visitPage('organization/org-chart', '/admin/acme/org-chart', $dark, $mobile)
        ->assertSee('5 personas activas, ninguna con jefe directo.')
        ->assertSee('Todavía nadie tiene jefe asignado.')
        ->assertSee('Ir a Empleados')
        ->assertSee('Importar CSV')
        ->assertDontSee('Jefes por revisar')
        ->assertSee('Diana Torres')
        ->click('Ir a Empleados')
        ->assertPathIs('/admin/acme/employees');
})->with($looks);

it('focuses the person searched: the way down to them and their card', function (bool $dark, bool $mobile): void {
    $people = acmeOrgChart();

    $page = visitPage('organization/org-chart', '/admin/acme/org-chart', $dark, $mobile)
        ->click('#org-chart-person')
        ->type('[role="combobox"][aria-controls="org-chart-person-options"]', 'ana')
        ->assertSee('Ana López · Operadora')
        ->keys('[role="combobox"][aria-controls="org-chart-person-options"]', 'Enter')
        ->assertQueryStringHas('focus', $people['ana']->id)
        ->assertSeeIn('nav[aria-label="Línea de mando"]', 'Diana Torres')
        ->assertSeeIn('nav[aria-label="Línea de mando"]', 'Gerardo Paz')
        ->assertSeeIn('[aria-current="true"]', 'Ana López')
        // Only the way down to her: the other manager waits behind «Ver más».
        ->assertDontSee('Sofía Luna')
        ->assertDontSee('Beto Díaz')
        ->assertNoAccessibilityIssues(1)
        ->assertScript(cspViolations(), '[]');

    $page->click('[aria-label="Ver 1 más: reportes de Diana Torres"]')
        ->assertSee('Sofía Luna')
        ->click('nav[aria-label="Línea de mando"] a:has-text("Gerardo Paz")')
        ->assertQueryStringHas('focus', $people['gerardo']->id)
        ->assertSee('Beto Díaz')
        ->click('nav[aria-label="Línea de mando"] a:has-text("Organigrama")')
        ->assertQueryStringMissing('focus')
        ->assertNoJavaScriptErrors();
})->with($looks);

it('walks the tree with the keyboard', function (): void {
    acmeOrgChart();
    $focused = '() => document.activeElement?.getAttribute("aria-label")';

    visitPage('organization/org-chart', '/admin/acme/org-chart')
        ->keys('[role="treeitem"][tabindex="0"]', 'ArrowRight')
        ->assertSee('Gerardo Paz')
        ->assertAttribute('[role="treeitem"][tabindex="0"]', 'aria-expanded', 'true')
        ->keys('[role="treeitem"][tabindex="0"]', 'ArrowDown')
        ->assertScript($focused, 'Gerardo Paz, Gerente de planta')
        ->keys('[role="treeitem"][tabindex="0"]', 'ArrowRight')
        ->assertSee('Ana López')
        ->keys('[role="treeitem"][tabindex="0"]', 'End')
        ->assertScript($focused, 'Sofía Luna, Gerente de ventas')
        ->keys('[role="treeitem"][tabindex="0"]', 'Home')
        ->assertScript($focused, 'Diana Torres, Directora general')
        ->keys('[role="treeitem"][tabindex="0"]', 'ArrowLeft')
        ->assertDontSee('Gerardo Paz')
        ->assertNoAccessibilityIssues(1);
});

it('narrows the tree to an area, dimming whoever is only on the way', function (): void {
    $people = acmeOrgChart();
    $sales = Area::factory()->create(['tenant_id' => $people['ana']->tenant_id, 'name' => 'Ventas']);
    $people['ana']->update(['area_id' => $sales->id]);

    visitPage('organization/org-chart', '/admin/acme/org-chart')
        ->click('#org-chart-area')
        ->type('[role="combobox"][aria-controls="org-chart-area-options"]', 'ventas')
        ->assertSee('Ventas')
        ->keys('[role="combobox"][aria-controls="org-chart-area-options"]', 'Enter')
        ->assertQueryStringHas('area', $sales->id)
        ->click('[aria-label="Ver los reportes de Diana Torres"]')
        ->assertSee('Gerardo Paz')
        ->assertDontSee('Sofía Luna')
        ->click('Limpiar')
        ->assertQueryStringMissing('area')
        ->assertNoJavaScriptErrors();
});

it('loads the reports of a large team a page at a time', function (): void {
    $people = acmeOrgChart();
    Employee::factory()->count(58)->reportingTo($people['sofia'])->create(['work_center_id' => $people['sofia']->work_center_id, 'area_id' => $people['sofia']->area_id]);
    Employee::factory()->reportingTo($people['sofia'])->create(['name' => 'Zoe Última', 'work_center_id' => $people['sofia']->work_center_id, 'area_id' => $people['sofia']->area_id]);

    visitPage('organization/org-chart', '/admin/acme/org-chart?focus='.$people['sofia']->id)
        ->assertSee('Revisar: muchos reportes')
        ->assertDontSee('Zoe Última')
        ->click('Ver 9 más')
        ->assertSee('Zoe Última')
        ->assertDontSee('Ver 9 más')
        ->assertNoJavaScriptErrors();
});

it('says when the reports could not be loaded, and loads them on trying again', function (): void {
    acmeOrgChart();

    $page = visitPage('organization/org-chart', '/admin/acme/org-chart');
    // The first request for somebody's reports fails, as when the connection drops; the next ones go through.
    $page->script('() => { const real = window.fetch; window.fetch = (url, init) => { if (String(url).includes("/reports")) { window.fetch = real; return Promise.reject(new TypeError("offline")); } return real(url, init); }; }');

    $page->click('[aria-label="Ver los reportes de Diana Torres"]')
        ->assertSeeIn('[role="alert"]', 'No se pudieron cargar los reportes.')
        ->assertDontSee('Gerardo Paz')
        ->assertNoAccessibilityIssues(1)
        ->click('Reintentar')
        ->assertSee('Gerardo Paz')
        ->assertSee('Sofía Luna')
        ->assertDontSee('No se pudieron cargar los reportes.')
        ->assertNoJavaScriptErrors();
});

it('fits the tree inside the page at every width, three levels down', function (): void {
    $people = acmeOrgChart();
    $page = visitPage('organization/org-chart', '/admin/acme/org-chart?focus='.$people['ana']->id);

    foreach ([375, 768, 1280] as $width) {
        resizeTo($page, $width)->assertScript(spilledOut(), '');
    }
});

it('reaches the org chart and its data quality from the sidebar', function (): void {
    acmeOrgChart();

    visit('/admin/acme')
        ->click('[data-sidebar="sidebar"] >> text=Organigrama')
        ->click('[data-sidebar="sidebar"] >> text=Calidad de datos')
        ->assertPathIs('/admin/acme/org-chart/quality')
        ->click('[data-sidebar="menu-sub"] >> text=Organigrama')
        ->assertPathIs('/admin/acme/org-chart')
        ->assertNoJavaScriptErrors();
});

it('fixes on its row the manager of whoever has none, and the count goes down', function (bool $dark, bool $mobile): void {
    $people = acmeOrgChart();
    $place = ['tenant_id' => $people['dir']->tenant_id, 'work_center_id' => $people['dir']->work_center_id, 'area_id' => $people['dir']->area_id];
    $lupe = Employee::factory()->create([...$place, 'name' => 'Lupe Sola', 'job_title' => 'Analista']);
    Employee::factory()->create([...$place, 'name' => 'Memo Solo', 'job_title' => 'Analista']);

    visitPage('organization/org-chart-quality', '/admin/acme/org-chart/quality', $dark, $mobile)
        ->assertSee('2 personas por revisar en la línea de reporte.')
        ->assertSeeIn('nav[aria-label="Problemas de la línea de reporte"] [aria-current="page"]', 'Sin jefe')
        ->assertSee('Personas activas sin jefe directo y sin nadie que les reporte.')
        ->assertSee('Lupe Sola')
        ->assertSee('Producción · Planta Norte')
        ->assertNoAccessibilityIssues(1)
        ->click('#manager-'.$lupe->id)
        ->type('[role="combobox"][aria-controls="manager-'.$lupe->id.'-options"]', 'diana')
        ->assertSee('Diana Torres · Directora general')
        ->keys('[role="combobox"][aria-controls="manager-'.$lupe->id.'-options"]', 'Enter')
        ->click('[aria-label="Guardar el jefe directo de Lupe Sola"]')
        ->assertSee('Jefe actualizado.')
        ->assertSee('1 persona por revisar en la línea de reporte.')
        ->assertDontSee('Lupe Sola')
        ->assertSee('Memo Solo')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');

    expect($lupe->refresh()->manager_id)->toBe($people['dir']->id);
})->with($looks);

it('opens the roots when there is more than one, and saves nothing until a manager is chosen', function (): void {
    $people = acmeOrgChart();
    $other = Employee::factory()->create(['tenant_id' => $people['dir']->tenant_id, 'name' => 'Olga Raíz', 'job_title' => 'Socia']);
    Employee::factory()->reportingTo($other)->create(['name' => 'Pepe Díaz']);

    visitPage('organization/org-chart-quality', '/admin/acme/org-chart/quality')
        ->assertSeeIn('nav[aria-label="Problemas de la línea de reporte"] [aria-current="page"]', 'Raíces')
        ->assertSee('Hay 2 personas hasta arriba. Normalmente solo hay una.')
        ->assertSee('Olga Raíz')
        ->assertDisabled('[aria-label="Guardar el jefe directo de Olga Raíz"]')
        ->click('nav[aria-label="Problemas de la línea de reporte"] >> text=Jefe dado de baja')
        ->assertQueryStringHas('issue', 'inactive_manager')
        ->assertSee('Nadie en esta lista')
        ->assertNoJavaScriptErrors();
});

it('leaves with nobody above, if asked, whoever reports to someone who left', function (): void {
    $people = acmeOrgChart();
    $people['gerardo']->update(['deactivated_at' => now()]);

    visitPage('organization/org-chart-quality', '/admin/acme/org-chart/quality')
        ->assertSeeIn('nav[aria-label="Problemas de la línea de reporte"] [aria-current="page"]', 'Jefe dado de baja')
        ->assertSee('Gerardo Paz · Gerente de planta')
        ->click('#manager-'.$people['ana']->id)
        ->click('[role="option"]:has-text("Sin jefe directo")')
        ->click('[aria-label="Guardar el jefe directo de Ana López"]')
        ->assertSee('Jefe actualizado.')
        ->assertDontSee('Ana López')
        ->assertSee('Beto Díaz')
        ->assertNoAccessibilityIssues(1)
        ->assertNoJavaScriptErrors();

    expect($people['ana']->refresh()->manager_id)->toBeNull();
});

it('says the reporting line is complete, with the way back to the chart', function (bool $dark, bool $mobile): void {
    acmeOrgChart();

    visitPage('organization/org-chart-quality', '/admin/acme/org-chart/quality', $dark, $mobile)
        ->assertSee('La línea de reporte está completa.')
        ->assertDontSee('Raíces')
        ->assertNoAccessibilityIssues(1)
        ->click('Ver organigrama')
        ->assertPathIs('/admin/acme/org-chart')
        ->click('Calidad de datos')
        ->assertPathIs('/admin/acme/org-chart/quality')
        ->assertNoJavaScriptErrors();
})->with($looks);

/** The address of the personal portal of one of the people of Acme's org chart. */
function teamPortal(string $who, bool $lines = true): string
{
    $person = acmeOrgChart($lines)[$who];
    workIn(Tenant::query()->findOrFail($person->tenant_id));

    return '/portal/'.resolve(IssuePortalLink::class)->handle($person->id);
}

it('shows a person their team in their portal: their manager, their peers and whoever reports to them', function (bool $dark, bool $mobile): void {
    visitPage('organization/portal-team', teamPortal('gerardo').'/team', $dark, $mobile)
        ->assertSee('Mi equipo')
        ->assertSeeIn('section[aria-labelledby="team-manager"]', 'Diana Torres')
        ->assertSeeIn('section[aria-labelledby="team-manager"]', 'Directora general')
        ->assertSeeIn('section[aria-labelledby="team-peers"]', 'Sofía Luna')
        ->assertSeeIn('section[aria-labelledby="team-peers"]', 'Gerente de ventas · Producción')
        ->assertSeeIn('section[aria-labelledby="team-reports"]', 'Ana López')
        ->assertSeeIn('section[aria-labelledby="team-reports"]', 'Beto Díaz')
        ->assertNoAccessibilityIssues(1)
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');
})->with($looks);

it('tells a person with nobody above what to do, without lists of nobody', function (bool $dark, bool $mobile): void {
    visitPage('organization/portal-team', teamPortal('ana', lines: false).'/team', $dark, $mobile)
        ->assertSee('No tiene un jefe directo registrado. Si esto no es correcto, avise a Recursos Humanos.')
        ->assertNotPresent('#team-peers')
        ->assertNotPresent('#team-reports')
        ->assertNoAccessibilityIssues(1);
})->with($looks);

it('tells a person whose peers are nobody that nobody else reports to the same person', function (): void {
    visitPage('organization/portal-team', teamPortal('dir').'/team')
        ->assertSee('No tiene un jefe directo registrado.')
        ->assertNotPresent('#team-peers')
        ->assertSeeIn('section[aria-labelledby="team-reports"]', 'Gerardo Paz');

    $people = Employee::query()->withoutGlobalScopes()->where('name', 'Sofía Luna')->sole();
    $people->update(['deactivated_at' => now()]);

    visit('/portal/'.resolve(IssuePortalLink::class)->handle(Employee::query()->where('name', 'Gerardo Paz')->sole()->id).'/team')
        ->assertSee('Nadie más reporta a la misma persona.')
        ->assertNoJavaScriptErrors();
});

it('reaches the team from the start of the portal, and comes back', function (): void {
    $portal = teamPortal('gerardo');

    visit($portal)
        ->click('Mi equipo')
        ->assertPathIs($portal.'/team')
        ->assertSee('Su jefe directo')
        ->click('Volver a mi portal')
        ->assertPathIs($portal)
        ->assertNoJavaScriptErrors();
});

it('turns the pages of the reports without moving the peers', function (): void {
    $portal = teamPortal('gerardo');
    $gerardo = Employee::query()->where('name', 'Gerardo Paz')->sole();
    Employee::factory()->count(10)->reportingTo($gerardo)->create(['name' => 'Abel Primero', 'work_center_id' => $gerardo->work_center_id, 'area_id' => $gerardo->area_id]);

    visit($portal.'/team')
        ->assertSee('Abel Primero')
        ->assertDontSee('Beto Díaz')
        ->click('nav[aria-label="Quienes le reportan"] >> text=Siguientes')
        ->assertQueryStringHas('reports', '2')
        ->assertSee('Beto Díaz')
        ->assertSee('Sofía Luna')
        ->assertNoJavaScriptErrors();
});

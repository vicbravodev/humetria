<?php

declare(strict_types=1);

use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Organization\Models\Area;
use App\Modules\Organization\Models\Employee;
use App\Modules\Telework\Actions\IssuePortalLink;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Carbon\CarbonImmutable;

// The org chart is drawn as a canvas (React Flow) with a tray and a panel, and the list is a table with views and a
// search: the nested list and its keyboard walk, the indicator cards and the «Calidad de datos» tabs these tests held
// before are gone by the product decision of the redesign (docs/rediseno-personas/ORG-V2.md).

$looks = [
    'light desktop' => [false, false],
    'dark desktop' => [true, false],
    'light mobile' => [false, true],
    'dark mobile' => [true, true],
];

/** The looks of the canvas, which a phone does not have: it gets one person at a time. */
$desktop = [
    'light' => [false],
    'dark' => [true],
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

/**
 * Drags a card of the canvas onto another with a mouse that moves in steps, as a hand does: the canvas follows the
 * pointer and a single jump is not a drag to it.
 */
function orgDragCard(mixed $page, string $from, string $to): mixed
{
    $page->script(<<<JS
        () => new Promise((resolve) => {
            const from = document.querySelector('{$from}');
            const to = document.querySelector('{$to}');
            const a = from.getBoundingClientRect();
            const b = to.getBoundingClientRect();
            const start = { x: a.x + a.width / 2, y: a.y + a.height / 2 };
            const end = { x: b.x + b.width / 2, y: b.y + b.height / 2 };
            const fire = (target, type, point) => target.dispatchEvent(new MouseEvent(type, { bubbles: true, cancelable: true, view: window, clientX: point.x, clientY: point.y, button: 0, buttons: type === 'mouseup' ? 0 : 1 }));
            fire(from, 'mousedown', start);
            let step = 0;
            const tick = () => {
                step += 1;
                fire(window, 'mousemove', { x: start.x + (end.x - start.x) * step / 10, y: start.y + (end.y - start.y) * step / 10 });
                if (step < 10) { requestAnimationFrame(tick); } else { fire(window, 'mouseup', end); resolve(true); }
            };
            requestAnimationFrame(tick);
        })
    JS);

    return $page;
}

/** The card of a person of the canvas, by their name. */
function orgCard(string $name): string
{
    return '[data-slot="org-node"][aria-label^="'.$name.',"]';
}

it('draws the chart as a canvas: the person at the top with their team, two levels open', function (bool $dark): void {
    acmeOrgChart();

    visitPage('organization/org-chart', '/admin/acme/org-chart', $dark)
        ->assertSee('5 personas activas')
        ->assertSee('3 niveles')
        ->assertSee('4 con jefe directo')
        ->assertSee('De aquí salen quién aprueba las vacaciones')
        ->assertPresent(orgCard('Diana Torres'))
        ->assertAttribute(orgCard('Diana Torres'), 'aria-label', 'Diana Torres, Directora general, 2 directos, 4 en total')
        ->assertPresent(orgCard('Gerardo Paz'))
        ->assertPresent(orgCard('Sofía Luna'))
        // The third level waits behind the pill of Gerardo.
        ->assertNotPresent(orgCard('Ana López'))
        ->assertSee('+2 · 2 en total')
        ->assertSeeIn('[aria-label="Organigrama"]', 'Clic en una persona para ver su equipo')
        ->assertNoAccessibilityIssues(1)
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');
})->with($desktop);

it('opens readable, whatever the width of the first level: cards at 0.9 or more, with the root clear of the filter bar', function (): void {
    $people = acmeOrgChart();

    foreach (['Elena', 'Fabián', 'Gisela', 'Hugo', 'Irma', 'Jaime'] as $name) {
        Employee::factory()->create(['tenant_id' => $people['dir']->tenant_id, 'work_center_id' => $people['dir']->work_center_id, 'area_id' => $people['dir']->area_id, 'name' => $name, 'job_title' => 'Gerente', 'manager_id' => $people['dir']->id]);
    }

    visitPage('organization/org-chart', '/admin/acme/org-chart')
        ->resize(1440, 900)
        ->wait(1)
        ->assertScript('() => Number(new DOMMatrix(getComputedStyle(document.querySelector(".react-flow__viewport")).transform).a) >= 0.9', true)
        ->assertScript('() => document.querySelector(\'[data-slot="org-node"][aria-label^="Diana Torres,"]\').getBoundingClientRect().top >= document.querySelector(\'[role="search"]\').getBoundingClientRect().bottom', true)
        ->assertNoJavaScriptErrors();
});

it('opens a branch with its pill and closes it with the same one', function (): void {
    acmeOrgChart();

    visitPage('organization/org-chart', '/admin/acme/org-chart')
        ->click('[aria-label="Ver el equipo de Gerardo Paz"]')
        ->assertPresent(orgCard('Ana López'))
        ->assertPresent(orgCard('Beto Díaz'))
        ->assertAttribute('[aria-label="Ocultar el equipo de Gerardo Paz"]', 'aria-expanded', 'true')
        ->assertSee('2 directos')
        ->click('[aria-label="Ocultar el equipo de Gerardo Paz"]')
        ->assertNotPresent(orgCard('Ana López'))
        ->assertSee('Mostrar: a tu gusto')
        ->assertNoJavaScriptErrors();
});

it('opens the levels asked for, and everything at once', function (): void {
    acmeOrgChart();

    visitPage('organization/org-chart', '/admin/acme/org-chart')
        ->click('button:has-text("Mostrar: 2 niveles")')
        ->click('[role="menuitemradio"]:has-text("3 niveles")')
        ->assertPresent(orgCard('Ana López'))
        // The menu is still closing, and a click on its button now would close it again.
        ->wait(0.4)
        ->click('button:has-text("Mostrar: 3 niveles")')
        ->click('[role="menuitemradio"]:has-text("1 nivel")')
        ->assertNotPresent(orgCard('Gerardo Paz'))
        ->click('Expandir todo')
        ->assertPresent(orgCard('Beto Díaz'))
        ->assertSee('Mostrar: todos')
        ->click('Contraer')
        ->assertNotPresent(orgCard('Gerardo Paz'))
        ->assertNoJavaScriptErrors();
});

it('shows the panel of a person: their manager, their team and what comes out of the chart', function (bool $dark): void {
    $people = acmeOrgChart();

    visitPage('organization/org-chart', '/admin/acme/org-chart', $dark)
        ->click(orgCard('Gerardo Paz'))
        ->assertSeeIn('aside[aria-label="Panel de Gerardo Paz"]', 'Gerente de planta')
        ->assertSeeIn('aside[aria-label="Panel de Gerardo Paz"]', 'Su jefe')
        ->assertSeeIn('aside[aria-label="Panel de Gerardo Paz"]', 'Diana Torres')
        ->assertSeeIn('aside[aria-label="Panel de Gerardo Paz"]', 'Su equipo directo (2)')
        ->assertSeeIn('aside[aria-label="Panel de Gerardo Paz"]', 'Ana López')
        ->assertSeeIn('aside[aria-label="Panel de Gerardo Paz"]', 'Beto Díaz')
        ->assertSeeIn('aside[aria-label="Panel de Gerardo Paz"]', 'Equipo total: 2 personas')
        ->assertSeeIn('aside[aria-label="Panel de Gerardo Paz"]', 'Qué sale de aquí')
        ->assertSeeIn('aside[aria-label="Panel de Gerardo Paz"]', 'mínimo de respondientes (5)')
        // Nothing of their pay, their evaluations or their contact: only who they are and their team.
        ->assertDontSeeIn('aside[aria-label="Panel de Gerardo Paz"]', '@')
        ->assertNoAccessibilityIssues(1)
        ->click('aside[aria-label="Panel de Gerardo Paz"] [data-slot="org-mini-person"]:has-text("Diana Torres")')
        ->assertPresent('aside[aria-label="Panel de Diana Torres"]')
        ->assertSeeIn('aside[aria-label="Panel de Diana Torres"]', 'Nadie: está hasta arriba.')
        ->assertAttribute('aside[aria-label="Panel de Diana Torres"] a:has-text("Ver en Empleados")', 'href', '/admin/acme/employees?q=Diana+Torres')
        ->click('[aria-label="Cerrar el panel"]')
        ->assertNotPresent('aside[aria-label="Panel de Diana Torres"]')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');

    expect($people['dir']->refresh()->manager_id)->toBeNull();
})->with($desktop);

it('says nothing of who is away today: the chart is not the place for absences', function (): void {
    $people = acmeOrgChart();
    $today = CarbonImmutable::now(config()->string('app.display_timezone'))->toDateString();
    LeaveRequest::factory()->approved()->create(['tenant_id' => $people['dir']->tenant_id, 'employee_id' => $people['dir']->id, 'starts_on' => $today, 'ends_on' => $today, 'working_days' => 1]);

    visitPage('organization/org-chart', '/admin/acme/org-chart')
        ->assertPresent(orgCard('Diana Torres'))
        ->assertDontSee('Ausente')
        ->click(orgCard('Diana Torres'))
        ->assertDontSee('Ausente')
        ->assertNoJavaScriptErrors();
});

it('invites a company where nobody has a manager to assign them', function (bool $dark, bool $mobile): void {
    acmeOrgChart(lines: false);

    visitPage('organization/org-chart', '/admin/acme/org-chart', $dark, $mobile)
        ->assertSee('5 personas activas')
        ->assertSee('Ninguna con jefe directo')
        ->assertSee('Todavía nadie tiene jefe directo')
        ->assertSee('Asígnalo al editar a cada persona o súbelo en el CSV de empleados.')
        ->assertSee('Ir a Empleados')
        ->assertNotPresent('[data-slot="org-node"]')
        ->click('Ir a Empleados')
        ->assertPathIs('/admin/acme/employees');
})->with($looks);

it('does not offer to edit to whoever only sees the chart', function (): void {
    $user = App\Models\User::factory()->create();
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    App\Modules\Tenancy\Models\TenantMembership::factory()->for($tenant)->for($user)->viewer()->create();
    test()->actingAs($user);
    $dir = Employee::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Diana Torres', 'job_title' => 'Directora general']);
    Employee::factory()->reportingTo($dir)->create(['name' => 'Gerardo Paz', 'job_title' => 'Gerente de planta']);

    visitPage('organization/org-chart', '/admin/acme/org-chart')
        ->assertPresent(orgCard('Diana Torres'))
        ->assertDontSee('Editar estructura')
        ->click(orgCard('Gerardo Paz'))
        ->assertDontSee('Cambiar jefe')
        ->assertNoJavaScriptErrors();
});

it('finds the person searched: the page opens on their way down, the others dimmed', function (): void {
    $people = acmeOrgChart();

    visitPage('organization/org-chart', '/admin/acme/org-chart')
        ->click('#org-chart-person')
        ->type('[role="combobox"][aria-controls="org-chart-person-options"]', 'ana')
        ->assertSee('Ana López · Operadora')
        ->keys('[role="combobox"][aria-controls="org-chart-person-options"]', 'Enter')
        ->assertQueryStringHas('focus', $people['ana']->id)
        ->assertSeeIn('[aria-label="Panel de Ana López"]', 'Gerardo Paz')
        ->assertPresent(orgCard('Ana López'))
        // Only the way down to her: the rest of Gerardo's team waits behind «Ver 1 más».
        ->assertSee('Ver 1 más')
        // Sofía is not on her way: her card is dimmed.
        ->assertScript('() => getComputedStyle(document.querySelector(\'[aria-label^="Sofía Luna,"]\')).opacity', '0.45')
        ->assertScript('() => getComputedStyle(document.querySelector(\'[aria-label^="Ana López,"]\').closest("[data-slot=org-node]") ?? document.querySelector(\'[aria-label^="Ana López,"]\')).opacity', '1')
        ->assertNoAccessibilityIssues(1)
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');
});

it('is one person at a time on a phone: their manager above, their team below, and the way back', function (bool $dark): void {
    $people = acmeOrgChart();

    $page = visitPage('organization/org-chart', '/admin/acme/org-chart', $dark, mobile: true)
        ->assertNotPresent('.react-flow')
        ->assertSeeIn('[aria-label="Persona: Diana Torres"]', 'Diana Torres')
        ->assertSeeIn('[aria-label="Persona: Diana Torres"]', '2 directos · 4 en total')
        ->assertSeeIn('[aria-label="Su equipo directo"]', 'Su equipo directo (2)')
        ->assertSeeIn('[aria-label="Su equipo directo"]', 'Gerardo Paz')
        ->assertSee('Qué sale de aquí')
        ->assertNoAccessibilityIssues(1);

    $page->click('[aria-label="Su equipo directo"] [data-slot="org-mini-person"]:has-text("Gerardo Paz")')
        ->assertSeeIn('[aria-label="Persona: Gerardo Paz"]', 'Gerente de planta')
        ->assertSeeIn('[aria-label="Su jefe"]', 'Diana Torres')
        ->assertSeeIn('[aria-label="Su equipo directo"]', 'Ana López')
        ->assertSeeIn('nav[aria-label="Línea de mando"]', 'Diana Torres')
        ->click('[aria-label="Su equipo directo"] [data-slot="org-mini-person"]:has-text("Ana López")')
        ->assertSeeIn('[aria-label="Persona: Ana López"]', 'No tiene un equipo directo')
        ->click('nav[aria-label="Línea de mando"] button:has-text("Diana Torres")')
        ->assertSeeIn('[aria-label="Persona: Diana Torres"]', 'Directora general')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');

    expect($people['ana']->refresh()->manager_id)->toBe($people['gerardo']->id);
})->with([[false], [true]]);

it('finds a person on a phone: it opens on them with their way in the crumbs', function (): void {
    $people = acmeOrgChart();

    visitPage('organization/org-chart', '/admin/acme/org-chart', mobile: true)
        ->click('#org-chart-person')
        ->type('[role="combobox"][aria-controls="org-chart-person-options"]', 'ana')
        ->assertSee('Ana López · Operadora')
        ->keys('[role="combobox"][aria-controls="org-chart-person-options"]', 'Enter')
        ->assertQueryStringHas('focus', $people['ana']->id)
        ->assertSeeIn('nav[aria-label="Línea de mando"]', 'Diana Torres')
        ->assertSeeIn('nav[aria-label="Línea de mando"]', 'Gerardo Paz')
        ->assertSeeIn('[aria-label="Persona: Ana López"]', 'Operadora')
        ->assertNoJavaScriptErrors();
});

it('loads the team of a large manager a page at a time, from their panel', function (): void {
    $people = acmeOrgChart();
    Employee::factory()->count(58)->reportingTo($people['sofia'])->create(['work_center_id' => $people['sofia']->work_center_id, 'area_id' => $people['sofia']->area_id]);
    Employee::factory()->reportingTo($people['sofia'])->create(['name' => 'Zoe Última', 'work_center_id' => $people['sofia']->work_center_id, 'area_id' => $people['sofia']->area_id]);

    visitPage('organization/org-chart', '/admin/acme/org-chart')
        ->click(orgCard('Sofía Luna'))
        ->assertSeeIn('aside[aria-label="Panel de Sofía Luna"]', 'Su equipo directo (59)')
        ->assertSeeIn('aside[aria-label="Panel de Sofía Luna"]', 'Ver los 59')
        ->assertDontSeeIn('aside[aria-label="Panel de Sofía Luna"]', 'Zoe Última')
        ->click('aside[aria-label="Panel de Sofía Luna"] button:has-text("Ver los 59")')
        ->click('aside[aria-label="Panel de Sofía Luna"] button:has-text("Ver más personas")')
        ->assertSeeIn('aside[aria-label="Panel de Sofía Luna"]', 'Zoe Última')
        ->assertNoJavaScriptErrors();
});

it('says when the team could not be loaded, and loads it on trying again', function (): void {
    $people = acmeOrgChart();

    $page = visitPage('organization/org-chart', '/admin/acme/org-chart');
    // The first request for somebody's reports fails, as when the connection drops; the next ones go through.
    $page->script('() => { const real = window.fetch; window.fetch = (url, init) => { if (String(url).includes("/reports")) { window.fetch = real; return Promise.reject(new TypeError("offline")); } return real(url, init); }; }');

    $page->click(orgCard('Gerardo Paz'))
        ->assertSeeIn('[role="alert"]', 'No se pudo cargar a su equipo.')
        ->assertNoAccessibilityIssues(1)
        ->click('aside[aria-label="Panel de Gerardo Paz"] button:has-text("Reintentar")')
        ->assertSeeIn('aside[aria-label="Panel de Gerardo Paz"]', 'Ana López')
        ->assertNoJavaScriptErrors();

    expect($people['gerardo']->refresh()->manager_id)->toBe($people['dir']->id);
});

it('fits the chart inside the page at every width', function (): void {
    $people = acmeOrgChart();
    $page = visitPage('organization/org-chart', '/admin/acme/org-chart?focus='.$people['ana']->id);

    // The canvas clips what is outside it (it is panned, not scrolled): what must not spill is the page around it.
    $spills = '() => { const inset = document.querySelector(\'[data-slot="sidebar-inset"]\'); return inset.scrollWidth > inset.clientWidth + 1 || document.documentElement.scrollWidth > window.innerWidth + 1 ? "spills" : ""; }';

    foreach ([375, 768, 1280] as $width) {
        resizeTo($page, $width)->assertScript($spills, '');
    }
});

it('reaches the org chart and its list from the sidebar', function (): void {
    acmeOrgChart();

    visit('/admin/acme')
        ->click('Ordena tu operación')
        ->click('[data-sidebar="sidebar"] >> text=Organigrama')
        ->click('[data-sidebar="sidebar"] >> text=Lista')
        ->assertPathIs('/admin/acme/org-chart/quality')
        ->click('[data-sidebar="menu-sub"] >> text=Organigrama')
        ->assertPathIs('/admin/acme/org-chart')
        ->assertNoJavaScriptErrors();
});

it('switches between the chart and the list with the segmented control', function (): void {
    acmeOrgChart();

    visitPage('organization/org-chart', '/admin/acme/org-chart')
        ->assertAttribute('nav[aria-label="Vista del organigrama"] a[aria-current="page"]', 'href', '/admin/acme/org-chart')
        ->click('nav[aria-label="Vista del organigrama"] >> text=Lista')
        ->assertPathIs('/admin/acme/org-chart/quality')
        ->assertSeeIn('nav[aria-label="Vista del organigrama"] [aria-current="page"]', 'Lista')
        ->click('nav[aria-label="Vista del organigrama"] >> text=Organigrama')
        ->assertPathIs('/admin/acme/org-chart')
        ->assertNoJavaScriptErrors();
});

it('can be closed: the note that says what the chart is for does not come back', function (): void {
    acmeOrgChart();

    visitPage('organization/org-chart', '/admin/acme/org-chart')
        ->assertSee('De aquí salen quién aprueba')
        ->click('[aria-label="Cerrar esta nota"]')
        ->assertDontSee('De aquí salen quién aprueba')
        ->refresh()
        ->assertDontSee('De aquí salen quién aprueba')
        ->assertNoJavaScriptErrors();
});

it('edits in the same screen: the band, the tray, and «Cambiar jefe» with its undo', function (): void {
    $people = acmeOrgChart();
    Employee::factory()->create(['tenant_id' => $people['dir']->tenant_id, 'name' => 'Lupe Sola', 'job_title' => 'Analista']);

    $page = visitPage('organization/org-chart', '/admin/acme/org-chart')
        ->click('Editar estructura')
        ->assertSee('Editando')
        ->assertSeeIn('[data-slot="org-edit-band"]', 'Modo edición')
        ->assertSeeIn('[data-slot="org-edit-band"]', 'Los cambios se guardan al soltar.')
        ->assertSeeIn('aside[aria-label="Personas sin jefe"]', 'Lupe Sola')
        ->assertSeeIn('aside[aria-label="Personas sin jefe"]', 'Solo aparecen quienes no tienen jefe')
        ->assertSee('Listo')
        ->assertNoAccessibilityIssues(1);

    $page->click('[aria-label="Ver el equipo de Gerardo Paz"]')
        ->click(orgCard('Ana López'))
        ->click('aside[aria-label="Panel de Ana López"] button:has-text("Cambiar jefe")')
        ->assertSee('Cambiar jefe de Ana López')
        ->click('#manager-of-'.$people['ana']->id)
        ->type('[role="combobox"][aria-controls="manager-of-'.$people['ana']->id.'-options"]', 'sofía')
        // Pick the option itself: Enter right after typing can land before the server search narrows the list.
        ->click('#manager-of-'.$people['ana']->id.'-options [role="option"]:has-text("Sofía Luna")')
        ->click('[role="dialog"] button:has-text("Asignar")')
        ->assertSee('Ana López ahora reporta a Sofía Luna')
        ->assertSee('Deshacer')
        ->assertNoJavaScriptErrors();

    expect($people['ana']->refresh()->manager_id)->toBe($people['sofia']->id);

    $page->click('Deshacer')
        ->assertSee('Cambio deshecho')
        ->assertNoJavaScriptErrors();

    expect($people['ana']->refresh()->manager_id)->toBe($people['gerardo']->id);

    $page->click('Listo')
        ->assertDontSee('Modo edición')
        ->assertSee('Editar estructura');
});

it('drags a person of the tray onto their manager', function (): void {
    $people = acmeOrgChart();
    $lupe = Employee::factory()->create(['tenant_id' => $people['dir']->tenant_id, 'name' => 'Lupe Sola', 'job_title' => 'Analista']);

    visitPage('organization/org-chart', '/admin/acme/org-chart')
        ->click('Editar estructura')
        ->drag('[data-slot="org-tray-person"]:has-text("Lupe Sola")', orgCard('Sofía Luna'))
        ->assertSee('Lupe Sola ahora reporta a Sofía Luna')
        ->assertSee('Nadie sin jefe: todas las personas tienen su lugar en el organigrama.')
        ->assertNoJavaScriptErrors();

    expect($lupe->refresh()->manager_id)->toBe($people['sofia']->id);
});

it('drags a card onto a new manager, and refuses to make a loop: it says why, calmly', function (): void {
    $people = acmeOrgChart();

    $page = visitPage('organization/org-chart', '/admin/acme/org-chart')
        ->click('Editar estructura')
        ->click('[aria-label="Ver el equipo de Gerardo Paz"]');

    orgDragCard($page, orgCard('Ana López'), orgCard('Sofía Luna'))
        ->assertSee('Ana López ahora reporta a Sofía Luna')
        ->assertNoJavaScriptErrors();

    expect($people['ana']->refresh()->manager_id)->toBe($people['sofia']->id);

    // Diana leads Sofía, who now leads Ana: Diana cannot report to Ana.
    orgDragCard($page, orgCard('Diana Torres'), orgCard('Ana López'))
        ->assertSeeIn('[data-slot="org-edit-message"]', 'No se puede: Ana López reporta a Diana Torres.')
        // The toast of the first move is still fading in: axe reads colors as painted.
        ->wait(1)
        ->assertNoAccessibilityIssues(1)
        ->assertNoJavaScriptErrors();

    expect($people['dir']->refresh()->manager_id)->toBeNull();
});

it('lists the people with the views that ask for action first, and the ones that need a manager tinted', function (bool $dark, bool $mobile): void {
    $people = acmeOrgChart();
    Employee::factory()->create(['tenant_id' => $people['dir']->tenant_id, 'name' => 'Lupe Sola', 'job_title' => 'Analista']);

    visitPage('organization/org-chart-quality', '/admin/acme/org-chart/quality', $dark, $mobile)
        ->assertSee('6 personas activas')
        ->assertSee('Cómo se completa')
        ->assertSee('Elige el jefe directo de cada persona (o súbelo en el CSV)')
        ->assertSeeIn('nav[aria-label="Vistas de la lista"] [aria-current="page"]', 'Sin jefe')
        // What asks for action goes first; the views with nobody wait under «Otros filtros».
        ->assertSeeIn('nav[aria-label="Vistas de la lista"]', 'Todas')
        ->assertSeeIn('nav[aria-label="Vistas de la lista"]', 'Otros filtros')
        ->assertDontSeeIn('nav[aria-label="Vistas de la lista"]', 'Más de 15 directos')
        ->assertSee('Lupe Sola')
        ->assertSee('Diana Torres')
        ->assertSee('Producción')
        ->assertAttribute('a:has-text("Descargar la lista (CSV)")', 'href', '/admin/acme/org-chart/quality/export')
        ->assertAttribute('a:has-text("Subir CSV de empleados")', 'href', '/admin/acme/employees/import')
        ->assertNoAccessibilityIssues(1)
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');
})->with($looks);

it('chooses the manager on the row of whoever has none: «Asignar» is the primary button only once chosen', function (bool $dark, bool $mobile): void {
    $people = acmeOrgChart();
    $lupe = Employee::factory()->create(['tenant_id' => $people['dir']->tenant_id, 'name' => 'Lupe Sola', 'job_title' => 'Analista']);

    visitPage('organization/org-chart-quality', '/admin/acme/org-chart/quality', $dark, $mobile)
        ->assertDisabled('[aria-label="Asignar jefe a Lupe Sola"]')
        ->click('#manager-'.$lupe->id)
        ->type('[role="combobox"][aria-controls="manager-'.$lupe->id.'-options"]', 'sofía')
        // Pick the option itself: Enter right after typing can land before the server search narrows the list.
        ->click('#manager-'.$lupe->id.'-options [role="option"]:has-text("Sofía Luna")')
        ->click('[aria-label="Asignar jefe a Lupe Sola"]')
        ->assertSee('Jefe actualizado.')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');

    expect($lupe->refresh()->manager_id)->toBe($people['sofia']->id);
})->with($looks);

it('searches on the server and narrows by name', function (): void {
    $people = acmeOrgChart();
    Employee::factory()->create(['tenant_id' => $people['dir']->tenant_id, 'name' => 'Lupe Sola']);

    visitPage('organization/org-chart-quality', '/admin/acme/org-chart/quality?issue=all')
        ->assertSee('Gerardo Paz')
        ->type('input[type="search"]', 'lupe')
        ->assertQueryStringHas('q', 'lupe')
        ->assertSee('Lupe Sola')
        ->assertDontSee('Gerardo Paz')
        ->assertSeeIn('nav[aria-label="Vistas de la lista"] [aria-current="page"]', 'Todas')
        ->assertNoJavaScriptErrors();
});

it('marks whoever reports to somebody who left, and lets them leave with nobody above', function (): void {
    $people = acmeOrgChart();
    $people['gerardo']->update(['deactivated_at' => now()]);

    visitPage('organization/org-chart-quality', '/admin/acme/org-chart/quality')
        ->assertSeeIn('nav[aria-label="Vistas de la lista"] [aria-current="page"]', 'Jefe dado de baja')
        ->assertSee('Su jefe fue dado de baja')
        ->assertSee('Ana López')
        ->click('#manager-'.$people['ana']->id)
        ->click('[role="option"]:has-text("Sin jefe directo")')
        ->click('[aria-label="Asignar jefe a Ana López"]')
        ->assertSee('Jefe actualizado.')
        ->assertNoAccessibilityIssues(1)
        ->assertNoJavaScriptErrors();

    expect($people['ana']->refresh()->manager_id)->toBeNull();
});

it('says the reporting line is in order, with the way back to the chart', function (bool $dark, bool $mobile): void {
    acmeOrgChart();

    visitPage('organization/org-chart-quality', '/admin/acme/org-chart/quality', $dark, $mobile)
        ->assertSee('Todo en orden.')
        ->assertSee('Las 5 personas tienen su línea de reporte completa.')
        ->assertSeeIn('nav[aria-label="Vistas de la lista"] [aria-current="page"]', 'Todas')
        ->assertNoAccessibilityIssues(1)
        ->click('Ver organigrama')
        ->assertPathIs('/admin/acme/org-chart')
        ->assertNoJavaScriptErrors();
})->with($looks);

it('closes the help of the list and keeps it closed', function (): void {
    acmeOrgChart();

    visitPage('organization/org-chart-quality', '/admin/acme/org-chart/quality')
        ->assertSee('Cómo se completa')
        ->click('[aria-label="Cerrar esta ayuda"]')
        ->assertDontSee('Cómo se completa')
        ->refresh()
        ->assertDontSee('Cómo se completa')
        ->assertNoJavaScriptErrors();
});

it('shows a viewer the list without a way to change a manager', function (): void {
    $user = App\Models\User::factory()->create();
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    App\Modules\Tenancy\Models\TenantMembership::factory()->for($tenant)->for($user)->viewer()->create();
    test()->actingAs($user);
    Employee::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Lupe Sola']);

    visitPage('organization/org-chart-quality', '/admin/acme/org-chart/quality')
        ->assertSee('Lupe Sola')
        ->assertDontSee('Asignar')
        ->assertNoAccessibilityIssues(1)
        ->assertNoJavaScriptErrors();
});

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
        // The person's own frame, even in a browser signed in to the panel: no sidebar, no top bar.
        ->assertNotPresent('[data-sidebar="sidebar"]')
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

it('keeps the editing mode on when a person is searched, and opens readable from the top', function (): void {
    acmeOrgChart();

    visit('/admin/acme/org-chart')
        ->click('Editar estructura')
        ->assertSee('Editando')
        ->click('#org-chart-person')
        ->type('[role="combobox"][aria-controls="org-chart-person-options"]', 'ana')
        ->assertSeeIn('#org-chart-person-options', 'Ana López')
        ->keys('[role="combobox"][aria-controls="org-chart-person-options"]', 'Enter')
        ->assertSee('Editando')
        ->assertScript('() => new URL(location.href).searchParams.get("edit")', '1')
        ->assertNoJavaScriptErrors();
});

/** A company like the demo: several heads, one with a team of 19 who lead nobody and two managers with their own teams. */
function demoLikeOrgChart(): void
{
    $people = acmeOrgChart();
    $place = ['tenant_id' => $people['dir']->tenant_id, 'work_center_id' => $people['dir']->work_center_id, 'area_id' => $people['dir']->area_id];
    $head = fn (string $name): Employee => Employee::factory()->create([...$place, 'name' => $name, 'job_title' => 'Director', 'manager_id' => null]);

    $big = $head('Hugo Vega');
    foreach (range(1, 19) as $number) {
        Employee::factory()->create([...$place, 'name' => "Operario {$number}", 'job_title' => 'Operador', 'manager_id' => $big->id]);
    }

    $raquel = $head('Raquel Burgos');
    foreach (['Mario', 'Nora', 'Olga', 'Pablo'] as $name) {
        $manager = Employee::factory()->create([...$place, 'name' => $name, 'job_title' => 'Gerente', 'manager_id' => $raquel->id]);
        Employee::factory()->count(3)->create([...$place, 'job_title' => 'Operador', 'manager_id' => $manager->id]);
    }
}

it('opens on the heads and their first level at a readable size, the company card and the head clear of the filter bar, and a team of 19 behind its pill', function (): void {
    demoLikeOrgChart();

    visitPage('organization/org-chart', '/admin/acme/org-chart')
        ->resize(1440, 900)
        ->wait(1)
        ->assertScript('() => Number(new DOMMatrix(getComputedStyle(document.querySelector(".react-flow__viewport")).transform).a) >= 0.5', true)
        ->assertScript(<<<'JS'
            () => {
                const bar = document.querySelector('[role="search"]').getBoundingClientRect().bottom;
                const company = document.querySelector('.react-flow__node-company').getBoundingClientRect();
                const head = document.querySelector('[data-slot="org-node"][aria-label^="Raquel Burgos,"]').getBoundingClientRect();
                const first = document.querySelector('[data-slot="org-node"][aria-label^="Mario,"]').getBoundingClientRect();

                // The first level is right under its head (a row apart), not halfway down the tallest column of its row.
                return company.top >= bar && head.top >= bar && first.top - head.bottom < 200;
            }
        JS, true)
        ->assertNotPresent(orgCard('Operario 1'))
        ->assertSee('+19 · 19 en total')
        ->assertNoJavaScriptErrors();
});

it('opens with no card cut off at the left, and the company card in view', function (): void {
    demoLikeOrgChart();
    $place = ['tenant_id' => Employee::query()->withoutGlobalScopes()->firstOrFail()->tenant_id];

    // Two more heads with three managers each: the first level is wider than the screen at a readable size.
    foreach (['Ángeles Girón', 'Iván Soto'] as $name) {
        $head = Employee::factory()->create([...$place, 'name' => $name, 'job_title' => 'Director', 'manager_id' => null]);
        Employee::factory()->count(3)->create([...$place, 'job_title' => 'Gerente', 'manager_id' => $head->id]);
    }

    visit('/admin/acme/org-chart')
        ->resize(1440, 900)
        ->wait(1)
        ->assertScript(<<<'JS'
            () => {
                const canvas = document.querySelector('.react-flow').getBoundingClientRect();
                const cards = [...document.querySelectorAll('.react-flow__node-person, .react-flow__node-company')].map((node) => node.getBoundingClientRect());
                const company = document.querySelector('.react-flow__node-company').getBoundingClientRect();
                const cut = cards.filter((box) => box.top < canvas.bottom && box.bottom > canvas.top && box.left < canvas.left - 1 && box.right > canvas.left + 1);

                return cut.length === 0 && company.left >= canvas.left && company.right <= canvas.right;
            }
            JS, true)
        ->assertNoJavaScriptErrors();
});

it('fits the chart at a readable size with «Ajustar a la pantalla», from the top', function (): void {
    demoLikeOrgChart();

    visitPage('organization/org-chart', '/admin/acme/org-chart')
        ->resize(1440, 900)
        ->wait(1)
        ->click('[aria-label="Alejar"]')
        ->click('[aria-label="Alejar"]')
        ->click('[aria-label="Alejar"]')
        ->click('[aria-label="Ajustar a la pantalla"]')
        ->wait(1)
        ->assertScript('() => Number(new DOMMatrix(getComputedStyle(document.querySelector(".react-flow__viewport")).transform).a) >= 0.5', true)
        ->assertNoJavaScriptErrors();
});

it('keeps the bar on one line and the panel beside the canvas, not over it, while editing', function (): void {
    demoLikeOrgChart();

    visitPage('organization/org-chart', '/admin/acme/org-chart')
        ->resize(1440, 900)
        ->click('Editar estructura')
        ->wait(1)
        ->click(orgCard('Raquel Burgos'))
        ->wait(1)
        ->assertScript(<<<'JS'
            () => {
                const bar = document.querySelector('[role="search"]').getBoundingClientRect();
                const canvas = document.querySelector('.react-flow').getBoundingClientRect();
                const panel = document.querySelector('aside[aria-label^="Panel de"]').getBoundingClientRect();

                return bar.height < 60 && bar.right <= canvas.right && panel.left >= canvas.right - 1;
            }
        JS, true)
        ->assertNoJavaScriptErrors();
});

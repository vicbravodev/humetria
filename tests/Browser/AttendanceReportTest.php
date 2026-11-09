<?php

declare(strict_types=1);

use App\Modules\Attendance\Enums\MarkType;
use App\Modules\Attendance\Models\AttendanceMark;
use App\Modules\Attendance\Models\WeeklyConfirmation;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;

/** RH's panel with a center whose last week that ended has two days of Ana (one confirmed week) and one of Bob with no end. */
function reportPanel(): array
{
    $url = signInToPanel();
    $tenant = Tenant::query()->where('slug', 'acme')->firstOrFail();
    workIn($tenant);
    $plant = WorkCenter::factory()->for($tenant)->create(['name' => 'Planta Norte']);
    $ana = EmployeeFactory::new()->create(['tenant_id' => $tenant->id, 'work_center_id' => $plant->id, 'name' => 'Ana López', 'job_title' => 'Operadora']);
    $bob = EmployeeFactory::new()->create(['tenant_id' => $tenant->id, 'work_center_id' => $plant->id, 'name' => 'Bob Díaz', 'job_title' => 'Almacenista']);
    $monday = CarbonImmutable::now('America/Mexico_City')->startOfWeek()->subWeek();
    $marks = [
        [$ana, MarkType::Start, $monday->setTime(8, 0)], [$ana, MarkType::End, $monday->setTime(18, 0)],
        [$ana, MarkType::Start, $monday->addDay()->setTime(8, 0)], [$ana, MarkType::End, $monday->addDay()->setTime(16, 0)],
        [$bob, MarkType::Start, $monday->addDays(2)->setTime(9, 0)],
    ];
    foreach ($marks as [$person, $type, $at]) {
        AttendanceMark::factory()->create(['tenant_id' => $tenant->id, 'employee_id' => $person->id, 'work_center_id' => $plant->id, 'type' => $type, 'marked_at' => $at->utc()]);
    }
    WeeklyConfirmation::factory()->confirmed()->create(['tenant_id' => $tenant->id, 'employee_id' => $ana->id, 'week_start' => $monday->toDateString()]);

    return [$url, $plant];
}

it('shows RH the report of a center\'s week: its figures, each person\'s days and the download', function (bool $dark, bool $mobile): void {
    [$url, $plant] = reportPanel();

    visitPage('attendance/report', "{$url}/attendance/report?center={$plant->id}", $dark, $mobile)
        ->assertSee('Reporte para la autoridad')
        ->assertSee('Descargar CSV')
        ->assertSee('Ana López')
        ->assertSee('8 h · 2 h doble')
        ->assertSee('Sin marca de fin')
        ->assertSee('Confirmada')
        ->assertSee('Cómo leer este reporte')
        ->assertSee('los días sin marcas no aparecen')
        ->assertSee('cada descarga también')
        ->assertDontSee('Ofrecer solo el CSV no se anota')
        ->assertSee('«hora corregida de 08:12 a 08:00»')
        ->assertSee('con el horario vigente de cada persona al generar el reporte')
        ->assertSee('Imprimir')
        ->assertSee('Días confirmados')
        ->assertSee('Antes de mostrarlo en una inspección')
        ->assertSee('1 día sin marca de fin')
        ->assertSee('1 día de una semana que nadie respondió')
        ->assertSee('La evidencia de un día es la de su marca más débil')
        ->assertScript('() => document.querySelectorAll(\'[role="img"][aria-label^="Horas por día"]\').length', 2);
})->with([
    'light, desktop' => [false, false],
    'dark, desktop' => [true, false],
    'light, phone' => [false, true],
    'dark, phone' => [true, true],
]);

it('offers only the CSV for a period too long for the screen, saying why', function (): void {
    [$url, $plant] = reportPanel();

    visit("{$url}/attendance/report?center={$plant->id}&from=2026-01-01&to=2026-06-30")
        ->assertSee('Este periodo se descarga en CSV')
        ->assertSee('Elegiste 181 días y en pantalla mostramos hasta 93.')
        ->assertScript('() => document.querySelector("table") === null', true)
        ->assertDontSee('Ana López')
        ->assertSee('Ofrecer solo el CSV no se anota; cada descarga sí.')
        ->assertDontSee('Abrir el reporte de un centro queda en la bitácora')
        ->assertAttributeContains('section[data-slot="empty-state"] a:has-text("Descargar CSV")', 'href', "/attendance/report/export?center={$plant->id}&from=2026-01-01&to=2026-06-30")
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');
});

it('asks for a center first', function (): void {
    [$url] = reportPanel();

    visitPage('attendance/report', "{$url}/attendance/report")
        ->assertSee('Elige un centro de trabajo')
        ->assertDontSee('Descargar CSV');
});

it('takes RH from the sidebar to the report, a center and its days, with the CSV to download', function (): void {
    [$url, $plant] = reportPanel();

    visit($url)
        ->click('[data-sidebar="sidebar"] >> text=Asistencia')
        ->click('[data-sidebar="sidebar"] >> text=Reporte para la autoridad')
        ->waitForText('Elige un centro de trabajo')
        ->assertPathIs("{$url}/attendance/report")
        ->assertAttribute('[data-sidebar="sidebar"] a[href$="/attendance/report"]', 'data-active', 'true')
        ->click('#report-center')
        ->type('[role="combobox"][aria-controls="report-center-options"]', 'norte')
        ->click('[role="option"]:has-text("Planta Norte")')
        ->click('button:has-text("Ver reporte")')
        ->waitForText('Día por día')
        ->assertSee('Ana López')
        ->assertSee('Bob Díaz')
        ->assertAttributeContains('a:has-text("Descargar CSV")', 'href', "/attendance/report/export?center={$plant->id}")
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');
});

it('lists only the people with flags or an unanswered week from the tabs, and goes back to everyone', function (): void {
    [$url, $plant] = reportPanel();

    visit("{$url}/attendance/report?center={$plant->id}")
        ->assertSee('Ana López')
        ->assertSee('Bob Díaz')
        ->click('nav[aria-label="Qué personas ver"] >> text=Con avisos')
        ->waitForText('Sin marca de fin')
        ->assertDontSee('Ana López')
        ->assertSee('Bob Díaz')
        ->assertQueryStringHas('show', 'flagged')
        ->click('nav[aria-label="Qué personas ver"] >> text=Todas')
        ->waitForText('Ana López')
        ->assertSee('Bob Díaz')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');
});

it('lets RH pick a usual period, and opens the two days only for «Otro»', function (): void {
    [$url, $plant] = reportPanel();

    visit("{$url}/attendance/report?center={$plant->id}")
        ->assertChecked('input[name="period"][value="last-week"]')
        ->assertMissing('#report-from')
        ->click('label:has-text("Otro")')
        ->assertVisible('#report-from')
        ->assertVisible('#report-to')
        ->click('label:has-text("Esta semana")')
        ->assertMissing('#report-from')
        ->assertNoJavaScriptErrors();
});

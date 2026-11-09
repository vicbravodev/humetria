<?php

declare(strict_types=1);

use App\Modules\Attendance\Enums\MarkType;
use App\Modules\Attendance\Models\AttendanceMark;
use App\Modules\Attendance\Models\AttendanceSchedule;
use App\Modules\Attendance\Models\WeeklyConfirmation;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;

/** RH's panel with a center whose people worked last week: Ana confirmed, Carla worked 13 h on Tuesday; only Ana has a schedule. */
function overviewPanel(): string
{
    $url = signInToPanel();
    $tenant = Tenant::query()->where('slug', 'acme')->firstOrFail();
    workIn($tenant);
    $plant = WorkCenter::factory()->for($tenant)->create(['name' => 'Planta Norte']);
    $monday = CarbonImmutable::now('America/Mexico_City')->startOfWeek()->subWeek();
    $ana = EmployeeFactory::new()->create(['tenant_id' => $tenant->id, 'work_center_id' => $plant->id, 'name' => 'Ana López', 'job_title' => 'Operadora']);
    $carla = EmployeeFactory::new()->create(['tenant_id' => $tenant->id, 'work_center_id' => $plant->id, 'name' => 'Carla Ruiz', 'job_title' => 'Analista']);
    WeeklyConfirmation::factory()->confirmed()->create(['tenant_id' => $tenant->id, 'employee_id' => $ana->id, 'week_start' => $monday->toDateString()]);
    AttendanceSchedule::factory()->create(['tenant_id' => $tenant->id, 'employee_id' => $ana->id]);

    foreach ([[MarkType::Start, 7], [MarkType::End, 20]] as [$type, $hour]) {
        AttendanceMark::factory()->create(['tenant_id' => $tenant->id, 'employee_id' => $carla->id, 'work_center_id' => $plant->id, 'type' => $type, 'marked_at' => $monday->addDay()->setTime($hour, 0)->utc()]);
    }

    return $url;
}

it('shows RH the week by state, its people and the centers', function (bool $dark, bool $mobile): void {
    $url = overviewPanel();

    visitPage('attendance/index', "{$url}/attendance", $dark, $mobile)
        ->assertSee('Asistencia')
        ->assertSee('Topes en riesgo')
        ->assertSee('Ana López')
        ->assertSee('Confirmada')
        ->assertSee('Carla Ruiz')
        ->assertSee('Un día de más de 12 h')
        ->assertSee('Centros sin zonas')
        ->assertSee('Sin zonas')
        ->assertScript(overviewRowHas('Carla Ruiz', 'Sin horario'), true)
        ->assertScript(overviewRowHas('Ana López', 'Sin horario'), false);
})->with([
    'light, desktop' => [false, false],
    'dark, desktop' => [true, false],
    'light, phone' => [false, true],
    'dark, phone' => [true, true],
]);

/** A script that says whether the row of a person in the people list reads a text. */
function overviewRowHas(string $name, string $text): string
{
    return "() => [...document.querySelectorAll('tbody tr')].find((row) => row.textContent.includes('{$name}'))?.textContent.includes('{$text}') ?? null";
}

it('takes RH from «Sin horario» to that person on the schedules page', function (): void {
    $url = overviewPanel();

    visit("{$url}/attendance")
        ->click('Sin horario')
        ->waitForText('Fijar horario')
        ->assertPathIs("{$url}/attendance/schedules")
        ->assertQueryStringHas('status', 'unscheduled')
        ->assertSee('Carla Ruiz')
        ->assertDontSee('Ana López')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');
});

it('takes RH to the weeks at risk from their figure, and to the week before', function (): void {
    $url = overviewPanel();

    visit("{$url}/attendance")
        ->click('Topes en riesgo')
        ->waitForText('Carla Ruiz')
        ->assertDontSee('Ana López')
        ->click('Semana anterior')
        ->waitForText('Ninguna semana en riesgo')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');
});

it('shows a company with no centers what to do first', function (bool $dark): void {
    $url = signInToPanel();

    visitPage('attendance/index', "{$url}/attendance", $dark)
        ->assertSee('Aún no hay centros de trabajo')
        ->assertSee('Ir a centros de trabajo');
})->with(['light' => [false], 'dark' => [true]]);

it('takes RH from the sidebar to the overview and to the corrections', function (): void {
    $url = overviewPanel();

    visit($url)
        ->click('[data-sidebar="sidebar"] >> text=Asistencia')
        ->click('[data-sidebar="sidebar"] >> text=Resumen')
        ->waitForText('Persona por persona')
        ->assertPathIs("{$url}/attendance")
        ->click('[data-sidebar="sidebar"] >> text=Correcciones')
        ->waitForText('Nada por resolver')
        ->assertPathIs("{$url}/attendance/corrections")
        ->assertNoJavaScriptErrors();
});

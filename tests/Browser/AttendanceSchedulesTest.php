<?php

declare(strict_types=1);

use App\Modules\Attendance\Enums\ShiftType;
use App\Modules\Attendance\Enums\WorkMode;
use App\Modules\Attendance\Models\AttendanceSchedule;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Database\Factories\Organization\EmployeeFactory;

/** RH's panel with a center: Ana works Monday to Friday 8 h; Bob has no schedule yet. */
function schedulesPanel(): string
{
    $url = signInToPanel();
    $tenant = Tenant::query()->where('slug', 'acme')->firstOrFail();
    workIn($tenant);
    $plant = WorkCenter::factory()->for($tenant)->create(['name' => 'Planta Norte']);
    $ana = EmployeeFactory::new()->create(['tenant_id' => $tenant->id, 'work_center_id' => $plant->id, 'name' => 'Ana López', 'job_title' => 'Operadora']);
    EmployeeFactory::new()->create(['tenant_id' => $tenant->id, 'work_center_id' => $plant->id, 'name' => 'Bob Díaz', 'job_title' => 'Almacenista']);
    AttendanceSchedule::factory()->create(['tenant_id' => $tenant->id, 'employee_id' => $ana->id]);

    return $url;
}

it('shows RH each person\'s schedule and who has none', function (bool $dark, bool $mobile): void {
    $url = schedulesPanel();

    visitPage('attendance/schedules', "{$url}/attendance/schedules", $dark, $mobile)
        ->assertSee('Horarios')
        ->assertSee('1 persona no tiene horario.')
        ->assertSee('L–V 8 h')
        ->assertSee('40 h')
        ->assertSee('de 48 h')
        ->assertSee('Sáb y dom de descanso')
        ->assertSee('Diurna · Presencial')
        ->assertSee('Sin horario');
})->with([
    'light, desktop' => [false, false],
    'dark, desktop' => [true, false],
    'light, phone' => [false, true],
    'dark, phone' => [true, true],
]);

it('fits the table in the window without scrolling sideways, and opens a person\'s schedule from «…»', function (int $width): void {
    $url = schedulesPanel();

    visit("{$url}/attendance/schedules")->resize($width, 900)
        ->assertSee('Diurna · Presencial')
        ->assertScript('() => { const frame = document.querySelector(\'[data-slot="table-container"]\'); return frame.scrollWidth <= frame.clientWidth; }', true)
        ->assertScript('() => document.documentElement.scrollWidth <= document.documentElement.clientWidth', true)
        ->click('[aria-label="Acciones de Bob Díaz"]')
        ->click('Fijar horario')
        ->assertSee('Horario de Bob Díaz')
        ->assertNoJavaScriptErrors();
})->with([1024, 1440, 1920]);

it('opens the schedule from the row\'s «…», with time fields that are not cut off', function (): void {
    $url = schedulesPanel();

    visit("{$url}/attendance/schedules")->resize(1440, 900)
        ->click('[aria-label="Acciones de Bob Díaz"]')
        ->click('Fijar horario')
        ->assertSee('Entra a las')
        ->assertPresent('[role="dialog"][data-slot="sheet-content"]')
        ->click('Personalizar días')
        ->assertScript('() => [...document.querySelectorAll(\'[role="dialog"] [data-slot="time-picker"] input\')].filter((input) => input.scrollWidth > input.clientWidth).length', 0);
});

it('lets RH fix a schedule, with the week\'s total and a note above the year\'s maximum', function (): void {
    $url = schedulesPanel();

    visit("{$url}/attendance/schedules")
        ->click('[aria-label="Acciones de Bob Díaz"]')
        ->click('Fijar horario')
        ->assertSee('Horario de Bob Díaz')
        ->assertSee('40 h a la semana')
        ->assertDontSee('al día:')
        ->click('[aria-label="Cambiar la jornada"]')
        ->click('Nocturna')
        ->assertSee('La jornada nocturna es de hasta 7 h al día: el lunes, el martes, el miércoles, el jueves y el viernes tienen más.')
        ->click('Trabaja a distancia')
        ->click('Personalizar días')
        ->click('#schedule-rest-6')
        ->fill('#schedule-day-6', '9,5')
        ->assertSee('49 h 30 min a la semana')
        ->assertSee('Son 1 h 30 min más que el máximo ordinario')
        ->assertDontSee('tiempo extra que la ley permite')
        ->fill('#schedule-day-6', '19')
        ->assertSee('Además, pasa de las 9 h de tiempo extra que la ley permite a la semana.')
        ->fill('#schedule-day-6', '4')
        ->assertDontSee('más que el máximo ordinario')
        ->click('Guardar horario')
        ->waitForText('Horario de Bob Díaz guardado.')
        ->assertDontSee('no tiene horario')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');

    $bob = Employee::query()->withoutGlobalScopes()->where('name', 'Bob Díaz')->sole();
    workIn(Tenant::query()->where('slug', 'acme')->sole());
    expect(AttendanceSchedule::query()->where('employee_id', $bob->id)->sole())
        ->shift_type->toBe(ShiftType::Night)->work_mode->toBe(WorkMode::Remote)
        ->minutes_by_weekday->toBe([1 => 480, 2 => 480, 3 => 480, 4 => 480, 5 => 480, 6 => 240, 7 => 0]);
});

it('lets RH set the time each day starts, optional, and keeps it', function (): void {
    $url = schedulesPanel();

    visit("{$url}/attendance/schedules")
        ->click('[aria-label="Acciones de Bob Díaz"]')
        ->click('Fijar horario')
        ->assertSee('Opcional. Sin hora de entrada, el día no tiene retardo')
        ->click('Personalizar días')
        ->fill('#schedule-start-1', '09:00')
        ->fill('#schedule-start-2', '09:00')
        ->fill('#schedule-start-5', '08:30')
        ->click('Guardar horario')
        ->waitForText('Horario de Bob Díaz guardado.')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');

    $bob = Employee::query()->withoutGlobalScopes()->where('name', 'Bob Díaz')->sole();
    workIn(Tenant::query()->where('slug', 'acme')->sole());
    expect(AttendanceSchedule::query()->where('employee_id', $bob->id)->sole()->starts_by_weekday)
        ->toBe([1 => 540, 2 => 540, 3 => null, 4 => null, 5 => 510, 6 => null, 7 => null]);
});

it('sets the same start time on every working day with one field, and shows it in the summary of the schedule', function (): void {
    $url = schedulesPanel();

    visit("{$url}/attendance/schedules")
        ->click('[aria-label="Acciones de Bob Díaz"]')
        ->click('Fijar horario')
        ->fill('#schedule-start', '09:00')
        ->click('Personalizar días')
        ->assertValue('#schedule-start-1', '09:00')
        ->assertValue('#schedule-start-5', '09:00')
        ->assertMissing('#schedule-start-6')
        ->click('Guardar horario')
        ->waitForText('Horario de Bob Díaz guardado.')
        ->assertSee('Entrada 09:00')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');
});

it('lets RH choose people and fix the same schedule for all of them, from a template', function (): void {
    $url = schedulesPanel();
    $tenant = Tenant::query()->where('slug', 'acme')->sole();
    $plant = WorkCenter::query()->where('name', 'Planta Norte')->sole();
    EmployeeFactory::new()->create(['tenant_id' => $tenant->id, 'work_center_id' => $plant->id, 'name' => 'Carla Ruiz', 'job_title' => 'Analista']);

    visit("{$url}/attendance/schedules")
        ->assertDontSee('personas elegidas')
        ->click('[aria-label="Elegir a Bob Díaz"]')
        ->assertSee('1 persona elegida')
        ->click('[aria-label="Elegir a Carla Ruiz"]')
        ->assertSee('2 personas elegidas')
        ->click('Fijar el mismo horario')
        ->assertSee('Horario para 2 personas')
        ->assertSee('Bob Díaz y Carla Ruiz')
        ->assertDontSee('se reemplaza')
        ->click('Lunes a sábado')
        ->assertSee('48 h a la semana')
        ->click('Nocturno')
        ->assertSee('35 h a la semana')
        ->click('L–V 8 h · 40 h')
        ->assertSee('40 h a la semana')
        ->click('[role="dialog"] button[type="submit"]')
        ->waitForText('Horario guardado para 2 personas.')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');

    workIn($tenant);
    expect(AttendanceSchedule::query()->count())->toBe(3)
        ->and(AttendanceSchedule::query()->where('shift_type', ShiftType::Day)->count())->toBe(3);
});

it('tells RH who loses the schedule they had, and lets them clear the choice', function (): void {
    $url = schedulesPanel();

    visit("{$url}/attendance/schedules")
        ->click('[aria-label="Elegir a Ana López"]')
        ->click('[aria-label="Elegir a Bob Díaz"]')
        ->click('Fijar el mismo horario')
        ->assertSee('1 de ellas ya tiene horario: se reemplaza.')
        ->click('Cancelar')
        ->click('Quitar selección')
        ->assertDontSee('personas elegidas');
});

it('chooses everyone of the page from the head of the column', function (): void {
    $url = schedulesPanel();

    visit("{$url}/attendance/schedules")
        ->click('[aria-label="Elegir a todas las personas de esta página"]')
        ->assertSee('2 personas elegidas');
});

it('shows a viewer the schedules without choosing anyone', function (): void {
    $url = schedulesPanel();
    $this->actingAs(memberOf(Tenant::query()->where('slug', 'acme')->sole(), TenantRole::Viewer));

    visitPage('attendance/schedules', "{$url}/attendance/schedules")
        ->assertSee('Ana López')
        ->assertSee('Solo lectura')
        ->assertSee('Tu rol puede ver esta pantalla, pero no modificar nada. Pide al dueño o a RH que te den permiso de edición.')
        ->assertScript('() => document.querySelectorAll("[aria-label^=\'Elegir a\']").length', 0);
});

it('fills the week and its one start time from a template, and names the shift its hours say', function (): void {
    $url = schedulesPanel();

    visit("{$url}/attendance/schedules")
        ->click('[aria-label="Acciones de Bob Díaz"]')
        ->click('Fijar horario')
        ->assertSee('L–V 8 h · S 5 h · 45 h')
        ->assertSee('Jornada diurna')
        ->click('Nocturno')
        ->assertValue('#schedule-start', '22:00')
        ->assertSee('Jornada nocturna')
        ->click('Con sábado medio')
        ->assertValue('#schedule-start', '09:00')
        ->assertSee('45 h a la semana')
        ->fill('#schedule-start', '15:00')
        ->assertSee('Jornada mixta')
        ->click('Guardar horario')
        ->waitForText('Horario de Bob Díaz guardado.')
        ->assertNoJavaScriptErrors();

    $bob = Employee::query()->withoutGlobalScopes()->where('name', 'Bob Díaz')->sole();
    workIn(Tenant::query()->where('slug', 'acme')->sole());
    expect(AttendanceSchedule::query()->where('employee_id', $bob->id)->sole())
        ->shift_type->toBe(ShiftType::Mixed)->work_mode->toBe(WorkMode::Onsite)
        ->minutes_by_weekday->toBe([1 => 480, 2 => 480, 3 => 480, 4 => 480, 5 => 480, 6 => 300, 7 => 0])
        ->starts_by_weekday->toBe([1 => 900, 2 => 900, 3 => 900, 4 => 900, 5 => 900, 6 => 900, 7 => null]);
});

it('copies the schedule of someone who has one', function (): void {
    $url = schedulesPanel();
    $ana = Employee::query()->withoutGlobalScopes()->where('name', 'Ana López')->sole();
    AttendanceSchedule::query()->withoutGlobalScopes()->where('employee_id', $ana->id)->update([
        'minutes_by_weekday' => [1 => 420, 2 => 420, 3 => 420, 4 => 420, 5 => 420, 6 => 420, 7 => 0],
        'starts_by_weekday' => [1 => 420, 2 => 420, 3 => 420, 4 => 420, 5 => 420, 6 => 420, 7 => null],
    ]);

    visit("{$url}/attendance/schedules")
        ->click('[aria-label="Acciones de Bob Díaz"]')
        ->click('Fijar horario')
        ->click('#schedule-copy')
        ->click('[role="option"]')
        ->assertSee('42 h a la semana')
        ->assertValue('#schedule-start', '07:00')
        ->click('Guardar horario')
        ->waitForText('Horario de Bob Díaz guardado.')
        ->assertNoJavaScriptErrors();

    $bob = Employee::query()->withoutGlobalScopes()->where('name', 'Bob Díaz')->sole();
    workIn(Tenant::query()->where('slug', 'acme')->sole());
    expect(AttendanceSchedule::query()->where('employee_id', $bob->id)->sole())
        ->minutes_by_weekday->toBe([1 => 420, 2 => 420, 3 => 420, 4 => 420, 5 => 420, 6 => 420, 7 => 0])
        ->starts_by_weekday->toBe([1 => 420, 2 => 420, 3 => 420, 4 => 420, 5 => 420, 6 => 420, 7 => null]);
});

it('opens «Personalizar días» for a saved week no template gives, with the rest days dimmed and nothing to fill', function (): void {
    $url = schedulesPanel();
    $ana = Employee::query()->withoutGlobalScopes()->where('name', 'Ana López')->sole();
    AttendanceSchedule::query()->withoutGlobalScopes()->where('employee_id', $ana->id)->update([
        'minutes_by_weekday' => [1 => 480, 2 => 480, 3 => 480, 4 => 480, 5 => 360, 6 => 0, 7 => 0],
    ]);

    visit("{$url}/attendance/schedules")
        ->click('[aria-label="Acciones de Ana López"]')
        ->click('Cambiar horario')
        ->assertValue('#schedule-day-5', '6')
        ->assertMissing('#schedule-day-6')
        ->assertMissing('#schedule-start-7')
        ->assertSee('Día de descanso')
        ->click('#schedule-rest-1')
        ->assertMissing('#schedule-day-1')
        ->assertSee('30 h a la semana')
        ->assertNoJavaScriptErrors();
});

it('fixes a schedule on a phone, for one person and for several', function (bool $dark): void {
    $url = schedulesPanel();

    visitPage('attendance/schedules', "{$url}/attendance/schedules", $dark, true)
        ->click('[aria-label="Acciones de Bob Díaz"]')
        ->click('Fijar horario')
        ->click('Lunes a sábado')
        ->click('Personalizar días')
        ->click('[aria-label="Cambiar la jornada"]')
        ->wait(0.5)
        ->assertNoAccessibilityIssues(1)
        ->assertScript('() => { const panel = document.querySelector(\'[data-slot="sheet-content"]\'); const edge = panel.getBoundingClientRect().right; return panel.scrollWidth <= panel.clientWidth && [...panel.querySelectorAll("input")].every((input) => input.getBoundingClientRect().right <= edge); }', true)
        ->click('Guardar horario')
        ->waitForText('Horario de Bob Díaz guardado.')
        ->click('[aria-label="Elegir a Ana López"]')
        ->click('[aria-label="Elegir a Bob Díaz"]')
        ->click('Fijar el mismo horario')
        ->assertSee('2 de ellas ya tienen horario: se reemplaza.')
        ->click('Nocturno')
        ->click('[role="dialog"] button[type="submit"]')
        ->waitForText('Horario guardado para 2 personas.')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');
})->with(['light' => false, 'dark' => true]);

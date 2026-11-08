<?php

declare(strict_types=1);

use App\Modules\Attendance\Enums\ShiftType;
use App\Modules\Attendance\Enums\WorkMode;
use App\Modules\Attendance\Models\AttendanceSchedule;
use App\Modules\Organization\Models\Employee;
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
        ->assertSee('40 h a la semana')
        ->assertSee('Diurna · Presencial')
        ->assertSee('Sin horario');
})->with([
    'light, desktop' => [false, false],
    'dark, desktop' => [true, false],
    'light, phone' => [false, true],
    'dark, phone' => [true, true],
]);

it('lets RH fix a schedule, with the week\'s total and a note above the year\'s maximum', function (): void {
    $url = schedulesPanel();

    visit("{$url}/attendance/schedules")
        ->click('Fijar horario')
        ->assertSee('Horario de Bob Díaz')
        ->assertSee('40 h a la semana')
        ->assertDontSee('al día:')
        ->click('Nocturna')
        ->assertSee('La jornada nocturna es de hasta 7 h al día: el lunes, el martes, el miércoles, el jueves y el viernes tienen más.')
        ->click('Remoto')
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

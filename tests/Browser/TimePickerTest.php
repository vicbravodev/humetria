<?php

declare(strict_types=1);

use App\Modules\Attendance\Models\AttendanceSchedule;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Database\Factories\Organization\EmployeeFactory;

/** RH's panel with Bob, who has no schedule yet. */
function timePanel(): string
{
    $url = signInToPanel();
    $tenant = Tenant::query()->where('slug', 'acme')->firstOrFail();
    workIn($tenant);
    $plant = WorkCenter::factory()->for($tenant)->create(['name' => 'Planta Norte']);
    EmployeeFactory::new()->create(['tenant_id' => $tenant->id, 'work_center_id' => $plant->id, 'name' => 'Bob Díaz', 'job_title' => 'Almacenista']);

    return $url;
}

it('takes a time typed short or pasted in 12 hours, writes it as HH:mm and saves it', function (): void {
    $url = timePanel();

    visit("{$url}/attendance/schedules")
        ->click('[aria-label="Acciones de Bob Díaz"]')
        ->click('Fijar horario')
        ->click('Personalizar días')
        ->assertAttribute('#schedule-start-1', 'type', 'text')
        ->type('#schedule-start-1', '730')
        ->keys('#schedule-start-1', 'Tab')
        ->assertValue('#schedule-start-1', '07:30')
        ->fill('#schedule-start-2', ' 7:30 p. m. ')
        ->keys('#schedule-start-2', 'Tab')
        ->assertValue('#schedule-start-2', '19:30')
        ->click('Guardar horario')
        ->waitForText('Horario de Bob Díaz guardado.')
        ->assertNoJavaScriptErrors();

    $bob = Employee::query()->withoutGlobalScopes()->where('name', 'Bob Díaz')->sole();
    workIn(Tenant::query()->where('slug', 'acme')->sole());
    expect(AttendanceSchedule::query()->where('employee_id', $bob->id)->sole()->starts_by_weekday)
        ->toMatchArray([1 => 450, 2 => 1170]);
});

it('moves the time a quarter of an hour with the arrow keys, from 08:00 when empty', function (): void {
    $url = timePanel();

    visit("{$url}/attendance/schedules")
        ->click('[aria-label="Acciones de Bob Díaz"]')
        ->click('Fijar horario')
        ->click('Personalizar días')
        ->keys('#schedule-start-3', 'ArrowUp')
        ->assertValue('#schedule-start-3', '08:00')
        ->keys('#schedule-start-3', ['ArrowUp', 'ArrowUp'])
        ->assertValue('#schedule-start-3', '08:30')
        ->keys('#schedule-start-3', 'ArrowDown')
        ->assertValue('#schedule-start-3', '08:15');
});

it('says when the text is no time, once the field is left', function (): void {
    $url = timePanel();

    visit("{$url}/attendance/schedules")
        ->click('[aria-label="Acciones de Bob Díaz"]')
        ->click('Fijar horario')
        ->click('Personalizar días')
        ->fill('#schedule-start-4', '25:00')
        ->assertDontSee('No es una hora')
        ->keys('#schedule-start-4', 'Tab')
        ->assertSee('No es una hora: escríbela como 07:30.')
        ->assertAttribute('#schedule-start-4', 'aria-invalid', 'true');
});

it('fits the time fields on a phone, with a numeric keyboard and 44 px to touch', function (bool $dark): void {
    $url = timePanel();

    visitPage('attendance/schedules', "{$url}/attendance/schedules", $dark, true)
        ->click('[aria-label="Acciones de Bob Díaz"]')
        ->click('Fijar horario')
        ->click('Personalizar días')
        ->assertAttribute('#schedule-start-1', 'inputmode', 'numeric')
        ->assertScript('() => document.querySelector("#schedule-start-1").closest("[data-slot=input-group]").getBoundingClientRect().height >= 44', true)
        ->assertScript('() => document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoJavaScriptErrors();
})->with(['light' => false, 'dark' => true]);

<?php

declare(strict_types=1);

use App\Modules\Attendance\Enums\ShiftType;
use App\Modules\Attendance\Enums\WorkMode;
use App\Modules\Attendance\Http\Controllers\ScheduleController;
use App\Modules\Attendance\Http\Requests\ScheduleRequest;
use App\Modules\Attendance\Models\AttendanceSchedule;
use App\Modules\Attendance\Support\SchedulePages;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Inertia\Testing\AssertableInertia;

mutates(ScheduleController::class, ScheduleRequest::class, SchedulePages::class);

/** The queries of the schedules page, whatever the number of people or schedules. */
const SCHEDULES_PAGE_QUERIES = 18;

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-11 18:00:00', 'UTC'));
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    workIn($this->tenant);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Norte']);
    $this->office = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Oficinas']);
    $this->ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'name' => 'Ana López', 'job_title' => 'Operadora']);
    $this->bob = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'name' => 'Bob Díaz', 'job_title' => 'Almacenista']);
    $this->carla = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->office->id, 'name' => 'Carla Ruiz', 'job_title' => 'Analista']);
    EmployeeFactory::new()->deactivated()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]);
    AttendanceSchedule::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id]);
    AttendanceSchedule::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->carla->id, 'shift_type' => ShiftType::Night, 'work_mode' => WorkMode::Remote, 'minutes_by_weekday' => [1 => 420, 2 => 420, 3 => 420, 4 => 420, 5 => 420, 6 => 420, 7 => 0]]);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
});

function schedulesUrl(array $query = []): string
{
    return route('tenant.attendance.schedules.index', ['acme', ...$query]);
}

/** A schedule as the form sends it: Monday to Friday 8 h, by day. */
function scheduleInput(array $overrides = []): array
{
    return ['shift_type' => 'day', 'work_mode' => 'onsite', 'minutes' => ['1' => '480', '2' => '480', '3' => '480', '4' => '480', '5' => '480', '6' => '0', '7' => '0'], ...$overrides];
}

function saveSchedule(string $employeeId, array $input): Illuminate\Testing\TestResponse
{
    return test()->from(schedulesUrl())->put(route('tenant.attendance.schedules.update', ['acme', $employeeId]), $input);
}

describe('the list', function (): void {
    it('shows RH each person with their schedule, or that they have none, and the year\'s ordinary maximum', function (): void {
        $this->actingAs($this->hr);

        $this->get(schedulesUrl())->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('attendance/schedules')
            ->where('center', null)
            ->where('filters', ['q' => '', 'status' => 'all'])
            ->where('counts', ['all' => 3, 'scheduled' => 2, 'unscheduled' => 1])
            ->where('weeklyMaximum', ['year' => 2026, 'minutes' => 2880])
            ->where('canManage', true)
            ->where('people.total', 3)
            ->where('people.per_page', 25)
            ->where('people.data.0', [
                'id' => $this->ana->id, 'name' => 'Ana López', 'jobTitle' => 'Operadora', 'center' => 'Planta Norte',
                'schedule' => ['shiftType' => 'day', 'workMode' => 'onsite', 'minutes' => ['1' => 480, '2' => 480, '3' => 480, '4' => 480, '5' => 480, '6' => 0, '7' => 0]],
            ])
            ->where('people.data.1', ['id' => $this->bob->id, 'name' => 'Bob Díaz', 'jobTitle' => 'Almacenista', 'center' => 'Planta Norte', 'schedule' => null])
            ->where('people.data.2.schedule.shiftType', 'night')
            ->where('people.data.2.schedule.workMode', 'remote'));
    });

    it('lists the people with and without a schedule on their own tabs', function (string $status, array $names): void {
        $this->actingAs($this->hr);

        $this->get(schedulesUrl(['status' => $status]))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('filters.status', $status === 'everything' ? 'all' : $status)
            ->where('people.data', fn ($people): bool => collect($people)->pluck('name')->all() === $names));
    })->with([
        'with a schedule' => ['scheduled', ['Ana López', 'Carla Ruiz']],
        'without one' => ['unscheduled', ['Bob Díaz']],
        'unknown, all of them' => ['everything', ['Ana López', 'Bob Díaz', 'Carla Ruiz']],
    ]);

    it('searches by name without minding accents or case, and narrows to one center', function (): void {
        $this->actingAs($this->hr);

        $this->get(schedulesUrl(['q' => 'LOPEZ']))->assertInertia(fn (AssertableInertia $page) => $page->where('people.total', 1)->where('people.data.0.name', 'Ana López'));
        $this->get(schedulesUrl(['center' => $this->office->id]))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('center', ['id' => $this->office->id, 'name' => 'Oficinas'])
            ->where('counts', ['all' => 1, 'scheduled' => 1, 'unscheduled' => 0]));
        $this->get(schedulesUrl(['center' => WorkCenter::factory()->for(Tenant::factory()->create())->create()->id]))->assertNotFound();
    });

    it('takes the same queries with a few people or many, with their schedules', function (int $more): void {
        foreach (EmployeeFactory::new()->count($more)->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->office->id]) as $person) {
            AttendanceSchedule::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $person->id]);
        }
        $this->actingAs($this->hr);

        $this->expectsDatabaseQueryCount(SCHEDULES_PAGE_QUERIES);
        $this->get(schedulesUrl())->assertOk();
    })->with(['a few' => [2], 'many more' => [47]]);

    it('lets a viewer read it, keeps the committee out and answers 404 to a union', function (): void {
        $this->actingAs(memberOf($this->tenant, TenantRole::Viewer));
        $this->get(schedulesUrl())->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('canManage', false));

        $this->actingAs(memberOf($this->tenant, TenantRole::Committee));
        $this->get(schedulesUrl())->assertForbidden();

        $union = Tenant::factory()->union()->create();
        $this->actingAs(memberOf($union, TenantRole::Owner));
        $this->get(route('tenant.attendance.schedules.index', $union->slug))->assertNotFound();
    });
});

describe('saving a schedule', function (): void {
    it('saves the person\'s one schedule, changed on a second save, and tells RH', function (): void {
        $this->actingAs($this->hr);

        saveSchedule($this->bob->id, scheduleInput())->assertRedirect(schedulesUrl())
            ->assertSessionHas('inertia.flash_data.toast', ['type' => 'success', 'message' => 'Horario de Bob Díaz guardado.']);
        saveSchedule($this->bob->id, scheduleInput(['shift_type' => 'mixed', 'work_mode' => 'remote', 'minutes' => array_replace(scheduleInput()['minutes'], ['6' => '300'])]))->assertSessionHasNoErrors();

        expect(AttendanceSchedule::query()->where('employee_id', $this->bob->id)->sole())
            ->shift_type->toBe(ShiftType::Mixed)
            ->work_mode->toBe(WorkMode::Remote)
            ->minutes_by_weekday->toBe([1 => 480, 2 => 480, 3 => 480, 4 => 480, 5 => 480, 6 => 300, 7 => 0]);
    });

    it('takes the exact limits of a day: rest at 0, 12 hours at 720', function (): void {
        $this->actingAs($this->hr);

        saveSchedule($this->bob->id, scheduleInput(['minutes' => array_replace(scheduleInput()['minutes'], ['1' => '720', '2' => '0'])]))->assertSessionHasNoErrors();
    });

    it('is checked in Spanish', function (array $input, string $field, string $message): void {
        $this->actingAs($this->hr);

        saveSchedule($this->bob->id, scheduleInput($input))->assertSessionHasErrors([$field => $message]);
        expect(AttendanceSchedule::query()->where('employee_id', $this->bob->id)->exists())->toBeFalse();
    })->with([
        'a weekday missing' => [['minutes' => ['1' => '480', '2' => '480', '3' => '480', '4' => '480', '5' => '480', '6' => '0']], 'minutes', 'Escribe las horas de los siete días; 0 es descanso.'],
        'a day that is not one' => [['minutes' => array_replace(scheduleInput()['minutes'], ['8' => '60'])], 'minutes', 'Escribe las horas de los siete días; 0 es descanso.'],
        'more than 12 hours in a day' => [['minutes' => array_replace(scheduleInput()['minutes'], ['3' => '721'])], 'minutes.3', 'Un día puede tener de 0 a 12 horas.'],
        'a negative day' => [['minutes' => array_replace(scheduleInput()['minutes'], ['3' => '-1'])], 'minutes.3', 'Un día puede tener de 0 a 12 horas.'],
        'minutes not a whole number' => [['minutes' => array_replace(scheduleInput()['minutes'], ['3' => '90.5'])], 'minutes.3', 'El campo minutos del día debe ser un número entero.'],
        'an unknown shift' => [['shift_type' => 'weekend'], 'shift_type', 'Elige diurna, nocturna o mixta.'],
        'no mode' => [['work_mode' => ''], 'work_mode', 'El campo modalidad es obligatorio.'],
        'an unknown mode' => [['work_mode' => 'hybrid'], 'work_mode', 'Elige presencial o remoto.'],
    ]);

    it('answers 404 for a person of another company or unknown, even with a valid form', function (): void {
        $foreign = EmployeeFactory::new()->create();
        workIn($this->tenant);
        $this->actingAs($this->hr);

        saveSchedule($foreign->id, scheduleInput())->assertNotFound();
        saveSchedule('0b8a3a4e-0000-4000-8000-000000000000', scheduleInput())->assertNotFound();
        $this->put(route('tenant.attendance.schedules.update', ['acme', 'not-a-person']), scheduleInput())->assertNotFound();
        expect(AttendanceSchedule::query()->withoutGlobalScopes()->where('employee_id', $foreign->id)->exists())->toBeFalse();
    });

    it('cannot be done by a viewer', function (): void {
        $this->actingAs(memberOf($this->tenant, TenantRole::Viewer));

        saveSchedule($this->bob->id, scheduleInput())->assertForbidden();
        expect(AttendanceSchedule::query()->where('employee_id', $this->bob->id)->exists())->toBeFalse();
    });
});

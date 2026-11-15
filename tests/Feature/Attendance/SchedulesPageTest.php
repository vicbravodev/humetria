<?php

declare(strict_types=1);

use App\Modules\Attendance\Actions\SaveSchedules;
use App\Modules\Attendance\Enums\ShiftType;
use App\Modules\Attendance\Enums\WorkMode;
use App\Modules\Attendance\Http\Controllers\ScheduleController;
use App\Modules\Attendance\Http\Requests\BulkScheduleRequest;
use App\Modules\Attendance\Http\Requests\ScheduleRequest;
use App\Modules\Attendance\Models\AttendanceSchedule;
use App\Modules\Attendance\Support\SchedulePages;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia;

mutates(ScheduleController::class, ScheduleRequest::class, BulkScheduleRequest::class, SchedulePages::class, SaveSchedules::class);

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
            ->where('weeklyMaximum', ['year' => 2026, 'minutes' => 2880, 'overtimeMinutes' => 540, 'dailyMinutes' => ['day' => 480, 'night' => 420, 'mixed' => 450]])
            ->where('canManage', true)
            ->where('people.total', 3)
            ->where('people.per_page', 25)
            ->where('people.data.0', [
                'id' => $this->ana->id, 'name' => 'Ana López', 'jobTitle' => 'Operadora', 'center' => 'Planta Norte',
                'schedule' => ['shiftType' => 'day', 'workMode' => 'onsite', 'minutes' => ['1' => 480, '2' => 480, '3' => 480, '4' => 480, '5' => 480, '6' => 0, '7' => 0], 'starts' => null],
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

    it('saves the optional start time of each day, and nothing when none is given', function (): void {
        $this->actingAs($this->hr);

        saveSchedule($this->bob->id, scheduleInput())->assertSessionHasNoErrors();
        expect(AttendanceSchedule::query()->where('employee_id', $this->bob->id)->sole()->starts_by_weekday)->toBeNull();

        saveSchedule($this->bob->id, scheduleInput(['starts' => ['1' => '540', '2' => '540', '3' => '', '4' => '0', '5' => '1439', '6' => '', '7' => '']]))->assertSessionHasNoErrors();
        expect(AttendanceSchedule::query()->where('employee_id', $this->bob->id)->sole()->starts_by_weekday)->toBe([1 => 540, 2 => 540, 3 => null, 4 => 0, 5 => 1439, 6 => null, 7 => null]);

        saveSchedule($this->bob->id, scheduleInput(['starts' => ['1' => '', '2' => '']]))->assertSessionHasNoErrors();
        expect(AttendanceSchedule::query()->where('employee_id', $this->bob->id)->sole()->starts_by_weekday)->toBeNull();
    });

    it('rejects a start time that is not a minute of the day', function (string $value): void {
        $this->actingAs($this->hr);

        saveSchedule($this->bob->id, scheduleInput(['starts' => ['1' => $value]]))->assertSessionHasErrors(['starts.1' => 'La hora de entrada debe ser entre las 00:00 y las 23:59.']);
        expect(AttendanceSchedule::query()->where('employee_id', $this->bob->id)->exists())->toBeFalse();
    })->with(['1440', '-1', '9.5', 'nueve']);

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

/** The same schedule for several people, as the form sends it. */
function saveSchedules(array $employeeIds, array $input = []): Illuminate\Testing\TestResponse
{
    return test()->from(schedulesUrl())->put(route('tenant.attendance.schedules.bulk', 'acme'), ['employee_ids' => $employeeIds, ...scheduleInput(), ...$input]);
}

describe('saving the same schedule for several people', function (): void {
    it('gives each of them the one schedule, replacing the one they had, and tells RH how many', function (): void {
        $this->actingAs($this->hr);

        saveSchedules([$this->ana->id, $this->bob->id], scheduleInput(['shift_type' => 'mixed', 'work_mode' => 'remote', 'minutes' => array_replace(scheduleInput()['minutes'], ['6' => '240'])]))
            ->assertRedirect(schedulesUrl())
            ->assertSessionHas('inertia.flash_data.toast', ['type' => 'success', 'message' => 'Horario guardado para 2 personas.']);

        foreach ([$this->ana, $this->bob] as $person) {
            expect(AttendanceSchedule::query()->where('employee_id', $person->id)->sole())
                ->shift_type->toBe(ShiftType::Mixed)->work_mode->toBe(WorkMode::Remote)
                ->minutes_by_weekday->toBe([1 => 480, 2 => 480, 3 => 480, 4 => 480, 5 => 480, 6 => 240, 7 => 0]);
        }
        // Carla was not chosen: hers stays.
        expect(AttendanceSchedule::query()->where('employee_id', $this->carla->id)->sole())->shift_type->toBe(ShiftType::Night);
    });

    it('says «de la persona» for one', function (): void {
        $this->actingAs($this->hr);

        saveSchedules([$this->bob->id])->assertSessionHas('inertia.flash_data.toast', ['type' => 'success', 'message' => 'Horario de Bob Díaz guardado.']);
    });

    it('writes a line of the log for each of them, with their ids and no schedule', function (): void {
        Log::spy();
        $this->actingAs($this->hr);

        saveSchedules([$this->ana->id, $this->bob->id]);

        Log::shouldHaveReceived('info')->with('attendance.schedule_saved', Mockery::on(fn (array $context): bool => $context['employee_id'] === $this->bob->id && ! array_key_exists('minutes', $context)))->once();
        Log::shouldHaveReceived('info')->with('attendance.schedule_saved', Mockery::on(fn (array $context): bool => $context['employee_id'] === $this->ana->id))->once();
    });

    it('saves nobody when one of them is of another company or unknown: the same 404', function (): void {
        $foreign = EmployeeFactory::new()->create();
        workIn($this->tenant);
        $this->actingAs($this->hr);

        saveSchedules([$this->bob->id, $foreign->id])->assertNotFound();
        saveSchedules([$this->bob->id, '0b8a3a4e-0000-4000-8000-000000000000'])->assertNotFound();
        expect(AttendanceSchedule::query()->where('employee_id', $this->bob->id)->exists())->toBeFalse()
            ->and(AttendanceSchedule::query()->withoutGlobalScopes()->where('employee_id', $foreign->id)->exists())->toBeFalse();
    });

    it('takes the exact limits of the group: one person and a hundred', function (): void {
        $this->actingAs($this->hr);
        $crowd = EmployeeFactory::new()->count(100)->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id])->pluck('id')->all();

        saveSchedules($crowd)->assertSessionHasNoErrors();
        expect(AttendanceSchedule::query()->whereIn('employee_id', $crowd)->count())->toBe(100);
        saveSchedules([...$crowd, $this->bob->id])->assertSessionHasErrors(['employee_ids' => 'Elige hasta 100 personas a la vez.']);
    });

    it('is checked in Spanish', function (array $ids, string $field, string $message): void {
        $this->actingAs($this->hr);

        saveSchedules($ids === ['bob', 'bob'] ? [$this->bob->id, $this->bob->id] : $ids)->assertSessionHasErrors([$field => $message]);
        expect(AttendanceSchedule::query()->where('employee_id', $this->bob->id)->exists())->toBeFalse();
    })->with([
        'nobody' => [[], 'employee_ids', 'El campo personas es obligatorio.'],
        'the same person twice' => [['bob', 'bob'], 'employee_ids.0', 'El campo persona tiene un valor duplicado.'],
        'something that is not an id' => [['bob'], 'employee_ids.0', 'El campo persona debe ser un UUID válido.'],
    ]);

    it('gives each of them the start times too', function (): void {
        $this->actingAs($this->hr);

        saveSchedules([$this->ana->id, $this->bob->id], scheduleInput(['starts' => ['1' => '480']]))->assertSessionHasNoErrors();

        expect(AttendanceSchedule::query()->whereIn('employee_id', [$this->ana->id, $this->bob->id])->pluck('starts_by_weekday')->all())->toBe([[1 => 480, 2 => null, 3 => null, 4 => null, 5 => null, 6 => null, 7 => null], [1 => 480, 2 => null, 3 => null, 4 => null, 5 => null, 6 => null, 7 => null]]);
    });

    it('is checked like a single schedule', function (): void {
        $this->actingAs($this->hr);

        saveSchedules([$this->bob->id], ['minutes' => array_replace(scheduleInput()['minutes'], ['3' => '721'])])->assertSessionHasErrors(['minutes.3' => 'Un día puede tener de 0 a 12 horas.']);
    });

    it('cannot be done by a viewer', function (): void {
        $this->actingAs(memberOf($this->tenant, TenantRole::Viewer));

        saveSchedules([$this->bob->id])->assertForbidden();
        expect(AttendanceSchedule::query()->where('employee_id', $this->bob->id)->exists())->toBeFalse();
    });
});

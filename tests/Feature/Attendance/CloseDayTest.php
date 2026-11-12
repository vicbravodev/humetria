<?php

declare(strict_types=1);

use App\Modules\Attendance\Actions\CloseDay;
use App\Modules\Attendance\Actions\CloseDays;
use App\Modules\Attendance\Enums\MarkType;
use App\Modules\Attendance\Events\DayClosed;
use App\Modules\Attendance\Models\AttendanceMark;
use App\Modules\Attendance\Models\AttendanceSchedule;
use App\Modules\Attendance\Support\WorkedDay;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

mutates(CloseDay::class, CloseDays::class, WorkedDay::class);

beforeEach(function (): void {
    // Wednesday 11 November 2026, 00:15 in Mexico City: Monday the 9th ended a whole day ago.
    $this->travelTo(CarbonImmutable::parse('2026-11-11 06:15:00', 'UTC'));
    $this->tenant = Tenant::factory()->create();
    workIn($this->tenant);
    $this->center = WorkCenter::factory()->for($this->tenant)->create();
    $this->ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);
    // Eight hours Monday to Friday, weekends off.
    AttendanceSchedule::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id]);
});

/** A mark of `$person` at a local time of Mexico City. */
function closeMark(Employee $person, MarkType $type, string $local): AttendanceMark
{
    return AttendanceMark::factory()->create([
        'tenant_id' => $person->tenant_id, 'employee_id' => $person->id, 'work_center_id' => $person->work_center_id,
        'type' => $type, 'marked_at' => CarbonImmutable::parse($local, 'America/Mexico_City')->utc(),
    ]);
}

function closeDay(Employee $person, string $date): ?App\Modules\Attendance\Data\DayHours
{
    return resolve(CloseDay::class)->handle($person->id, CarbonImmutable::parse($date, 'America/Mexico_City'));
}

describe('a day', function (): void {
    it('counts the minutes worked net of the break against the minutes the schedule set, and says it closed', function (): void {
        Event::fake([DayClosed::class]);
        Log::spy();
        closeMark($this->ana, MarkType::Start, '2026-11-09 09:00');
        closeMark($this->ana, MarkType::BreakStart, '2026-11-09 14:00');
        closeMark($this->ana, MarkType::BreakEnd, '2026-11-09 15:00');
        closeMark($this->ana, MarkType::End, '2026-11-09 18:30');

        $day = closeDay($this->ana, '2026-11-09');

        expect($day?->date->toDateString())->toBe('2026-11-09')
            ->and($day?->workedMinutes)->toBe(510)
            ->and($day?->scheduledMinutes)->toBe(480)
            ->and($day?->restDay)->toBeFalse();
        Event::assertDispatchedTimes(DayClosed::class, 1);
        Event::assertDispatched(DayClosed::class, fn (DayClosed $event): bool => $event->employeeId === $this->ana->id && $event->date === '2026-11-09' && ! $event->missingEnd);
        Log::shouldHaveReceived('info')->with('attendance.day_closed', ['employee_id' => $this->ana->id, 'date' => '2026-11-09', 'missing_end' => false]);
    });

    it('flags a start with no end and never closes it for the person: no mark is added', function (): void {
        Event::fake([DayClosed::class]);
        closeMark($this->ana, MarkType::Start, '2026-11-09 09:00');
        closeMark($this->ana, MarkType::BreakStart, '2026-11-09 14:00');
        closeMark($this->ana, MarkType::BreakEnd, '2026-11-09 15:00');

        $day = closeDay($this->ana, '2026-11-09');

        // Only what the marks prove: 09:00 to 14:00; the open stretch counts nothing.
        expect($day?->workedMinutes)->toBe(300)
            ->and(AttendanceMark::query()->count())->toBe(3);
        Event::assertDispatched(DayClosed::class, fn (DayClosed $event): bool => $event->missingEnd);
    });

    it('puts a night that starts on Sunday and ends on Monday on Sunday, a rest day by the schedule', function (): void {
        closeMark($this->ana, MarkType::Start, '2026-11-08 22:00');
        closeMark($this->ana, MarkType::End, '2026-11-09 06:00');

        expect(closeDay($this->ana, '2026-11-08'))->workedMinutes->toBe(480)->scheduledMinutes->toBe(0)->restDay->toBeTrue()
            ->and(closeDay($this->ana, '2026-11-09'))->workedMinutes->toBe(0)->scheduledMinutes->toBe(480)->restDay->toBeFalse();
    });

    it('keeps a day started at Sunday 23:59 on Sunday, with its Monday end', function (): void {
        closeMark($this->ana, MarkType::Start, '2026-11-08 23:59');
        closeMark($this->ana, MarkType::End, '2026-11-09 08:00');

        expect(closeDay($this->ana, '2026-11-08')?->workedMinutes)->toBe(481)
            ->and(closeDay($this->ana, '2026-11-09')?->workedMinutes)->toBe(0);
    });

    it('puts a day started at Monday 00:00 exactly on Monday', function (): void {
        closeMark($this->ana, MarkType::Start, '2026-11-08 23:00');
        closeMark($this->ana, MarkType::End, '2026-11-08 23:59');
        closeMark($this->ana, MarkType::Start, '2026-11-09 00:00');
        closeMark($this->ana, MarkType::End, '2026-11-09 08:00');

        expect(closeDay($this->ana, '2026-11-08')?->workedMinutes)->toBe(59)
            ->and(closeDay($this->ana, '2026-11-09')?->workedMinutes)->toBe(480);
    });

    it('closes a working day with no marks as worked 0', function (): void {
        expect(closeDay($this->ana, '2026-11-09'))->workedMinutes->toBe(0)->scheduledMinutes->toBe(480);
    });

    it('has nothing to close on a rest day with no marks, nor for a person with no schedule and no marks', function (): void {
        Event::fake([DayClosed::class]);
        $bob = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);

        expect(closeDay($this->ana, '2026-11-08'))->toBeNull()
            ->and(closeDay($bob, '2026-11-09'))->toBeNull();
        Event::assertNotDispatched(DayClosed::class);
    });

    it('takes the legal daily maximum of a day shift as the schedule of a person with none', function (): void {
        $bob = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);
        closeMark($bob, MarkType::Start, '2026-11-08 09:00');
        closeMark($bob, MarkType::End, '2026-11-08 10:00');

        expect(closeDay($bob, '2026-11-08'))->workedMinutes->toBe(60)->scheduledMinutes->toBe(480)->restDay->toBeFalse();
    });

    it('gives the same day when closed twice', function (): void {
        closeMark($this->ana, MarkType::Start, '2026-11-09 09:00');
        closeMark($this->ana, MarkType::End, '2026-11-09 17:00');

        expect(closeDay($this->ana, '2026-11-09'))->toEqual(closeDay($this->ana, '2026-11-09'));
    });

    it('counts the correction instead of the mark it replaced', function (): void {
        $start = closeMark($this->ana, MarkType::Start, '2026-11-09 09:30');
        AttendanceMark::factory()->correcting($start)->create(['marked_at' => CarbonImmutable::parse('2026-11-09 09:00', 'America/Mexico_City')->utc()]);
        closeMark($this->ana, MarkType::End, '2026-11-09 17:00');

        expect(closeDay($this->ana, '2026-11-09')?->workedMinutes)->toBe(480);
    });
});

describe('every night', function (): void {
    it('closes the day before yesterday for each active person with a mark that day or a working day by their schedule, in every company', function (): void {
        Event::fake([DayClosed::class]);
        Log::spy();
        $bob = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);
        closeMark($bob, MarkType::Start, '2026-11-09 09:00');
        // Not scheduled on Mondays and no marks: nothing to close.
        $carla = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);
        AttendanceSchedule::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $carla->id, 'minutes_by_weekday' => [1 => 0, 2 => 480]]);
        $globex = Tenant::factory()->create();
        $dan = EmployeeFactory::new()->create(['tenant_id' => $globex->id]);
        closeMark($dan, MarkType::Start, '2026-11-09 09:00');
        workIn($this->tenant);

        expect(resolve(CloseDays::class)->handle())->toBe(3);

        Event::assertDispatchedTimes(DayClosed::class, 3);
        foreach ([$this->ana, $bob, $dan] as $person) {
            Event::assertDispatched(DayClosed::class, fn (DayClosed $event): bool => $event->employeeId === $person->id && $event->date === '2026-11-09');
        }
        Log::shouldHaveReceived('info')->with('attendance.days_closed', ['closed' => 3]);
    });

    it('closes nothing for a company whose plan has no Attendance: it records no marks, so there is nothing new to close', function (): void {
        Event::fake([DayClosed::class]);
        closeMark($this->ana, MarkType::Start, '2026-11-09 09:00');
        resolve(App\Modules\Tenancy\Contracts\CompanyModules::class)->syncPlan($this->tenant->id, ['nom035'], CarbonImmutable::now(config('app.display_timezone')));

        expect(resolve(CloseDays::class)->handle())->toBe(0);
    });

    it('skips a person who left', function (): void {
        Event::fake([DayClosed::class]);
        closeMark($this->ana, MarkType::Start, '2026-11-09 09:00');
        $this->ana->update(['deactivated_at' => now()]);

        expect(resolve(CloseDays::class)->handle())->toBe(0);
        Event::assertNotDispatched(DayClosed::class);
    });

    it('closes only the day before yesterday: not a mark of the day before it, nor of yesterday or today', function (): void {
        Event::fake([DayClosed::class]);
        $bob = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);
        closeMark($bob, MarkType::Start, '2026-11-08 09:00');
        closeMark($bob, MarkType::End, '2026-11-08 17:00');
        closeMark($bob, MarkType::Start, '2026-11-10 09:00');
        closeMark($bob, MarkType::End, '2026-11-10 17:00');
        closeMark($bob, MarkType::Start, '2026-11-11 00:05');

        expect(resolve(CloseDays::class)->handle())->toBe(1);
        Event::assertDispatched(DayClosed::class, fn (DayClosed $event): bool => $event->employeeId === $this->ana->id);
    });

    it('closes a night that started that day only once it ended: its end is not missing', function (): void {
        Event::fake([DayClosed::class]);
        $bob = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);
        closeMark($bob, MarkType::Start, '2026-11-09 22:00');
        closeMark($bob, MarkType::End, '2026-11-10 06:00');

        resolve(CloseDays::class)->handle();

        Event::assertDispatched(DayClosed::class, fn (DayClosed $event): bool => $event->employeeId === $bob->id && $event->date === '2026-11-09' && ! $event->missingEnd);
    });

    it('runs from the console', function (): void {
        $this->artisan('attendance:close-days')->expectsOutput('Días cerrados: 1')->assertSuccessful();
    });
});

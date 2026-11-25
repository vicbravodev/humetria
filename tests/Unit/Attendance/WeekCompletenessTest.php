<?php

declare(strict_types=1);

use App\Modules\Attendance\Enums\MarkType;
use App\Modules\Attendance\Models\AttendanceMark;
use App\Modules\Attendance\Models\AttendanceSchedule;
use App\Modules\Attendance\Support\ScheduleTimeline;
use App\Modules\Attendance\Support\WeekCompleteness;
use App\Modules\Attendance\Support\WeekPeriod;
use App\Modules\Tenancy\Models\Tenant;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

mutates(WeekCompleteness::class);

const COMPLETE_WEEK = '2026-11-02';

beforeEach(function (): void {
    // Wednesday 11 November 2026, 12:00 in Mexico City: the week of 2 November is over.
    $this->travelTo(CarbonImmutable::parse('2026-11-11 18:00:00', 'UTC'));
    $this->tenant = Tenant::factory()->create();
    workIn($this->tenant);
    $this->ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id]);
});

/** A day worked, 09:00 to 17:00 local (Mexico City, UTC-6 in November). */
function completeDay(string $date): array
{
    return [$date => [
        new AttendanceMark(['type' => MarkType::Start, 'marked_at' => CarbonImmutable::parse("{$date} 15:00", 'UTC'), 'timezone' => 'America/Mexico_City']),
        new AttendanceMark(['type' => MarkType::End, 'marked_at' => CarbonImmutable::parse("{$date} 23:00", 'UTC'), 'timezone' => 'America/Mexico_City']),
    ]];
}

/** The days a person with a Monday to Friday schedule (unless `$weekdays` says other) has left uncovered. */
function completeness(object $test, array $byDay = [], array $away = [], array $holidays = [], ?string $hiredOn = null, string $week = COMPLETE_WEEK, bool $schedule = true): WeekCompleteness
{
    if ($schedule) {
        AttendanceSchedule::factory()->create(['tenant_id' => $test->tenant->id, 'employee_id' => $test->ana->id]);
    }

    return WeekCompleteness::of(
        WeekPeriod::ofDate(CarbonImmutable::parse($week), 'America/Mexico_City'),
        $byDay,
        ScheduleTimeline::of([$test->ana->id]),
        $test->ana->id,
        $away,
        $holidays,
        $hiredOn === null ? null : CarbonImmutable::parse($hiredOn),
    );
}

/** Monday to Friday of the week of 2 November, each worked. */
function workedWeek(string ...$except): array
{
    return array_merge(...array_map(fn (string $date): array => in_array($date, $except, true) ? [] : completeDay($date), ['2026-11-02', '2026-11-03', '2026-11-04', '2026-11-05', '2026-11-06']));
}

it('has no day uncovered in a week worked in full', function (): void {
    $week = completeness($this, workedWeek());

    expect($week->uncoveredDays())->toBe([])
        ->and($week->day(0))->toBe(['required' => true, 'uncovered' => false])
        ->and($week->day(5))->toBe(['required' => false, 'uncovered' => false]);
});

it('leaves uncovered a working day with no marks', function (): void {
    $week = completeness($this, workedWeek('2026-11-04'));

    expect($week->uncoveredDays())->toBe(['2026-11-04'])
        ->and($week->day(2))->toBe(['required' => true, 'uncovered' => true])
        ->and($week->day(1))->toBe(['required' => true, 'uncovered' => false]);
});

it('leaves uncovered a working day with a start and no end, and gives the days in order', function (): void {
    $open = ['2026-11-03' => [new AttendanceMark(['type' => MarkType::Start, 'marked_at' => CarbonImmutable::parse('2026-11-03 15:00', 'UTC'), 'timezone' => 'America/Mexico_City'])]];

    $week = completeness($this, [...workedWeek('2026-11-03', '2026-11-06'), ...$open]);

    expect($week->uncoveredDays())->toBe(['2026-11-03', '2026-11-06']);
});

it('does not ask for a day of rest of the schedule, with or without marks', function (): void {
    $week = completeness($this, [...workedWeek(), ...completeDay('2026-11-07')]);

    expect($week->uncoveredDays())->toBe([])
        ->and($week->day(5))->toBe(['required' => false, 'uncovered' => false])
        ->and($week->day(6))->toBe(['required' => false, 'uncovered' => false]);
});

it('does not ask for a holiday, a day of vacations or a leave', function (string $kind): void {
    $byDay = workedWeek('2026-11-04');
    $week = $kind === 'holiday'
        ? completeness($this, $byDay, holidays: ['2026-11-04'])
        : completeness($this, $byDay, away: [2 => $kind]);

    expect($week->uncoveredDays())->toBe([])
        ->and($week->day(2))->toBe(['required' => false, 'uncovered' => false]);
})->with(['holiday', 'vacation', 'leave']);

it('does not ask for the days before the hire date, and asks for the day of the hire', function (): void {
    $week = completeness($this, workedWeek('2026-11-02', '2026-11-03', '2026-11-04'), hiredOn: '2026-11-04');

    expect($week->uncoveredDays())->toBe(['2026-11-04'])
        ->and($week->day(0))->toBe(['required' => false, 'uncovered' => false])
        ->and($week->day(1))->toBe(['required' => false, 'uncovered' => false])
        ->and($week->day(2)['required'])->toBeTrue();
});

it('asks for every day when the hire date is unknown', function (): void {
    expect(completeness($this, [], hiredOn: null)->uncoveredDays())->toBe(['2026-11-02', '2026-11-03', '2026-11-04', '2026-11-05', '2026-11-06']);
});

it('has no day uncovered for a person with no schedule in effect, and does not ask for them', function (): void {
    $week = completeness($this, [], schedule: false);

    expect($week->uncoveredDays())->toBe([])
        ->and($week->day(0))->toBe(['required' => false, 'uncovered' => false]);
});

it('does not ask for the days before a schedule starts, and asks from the day it does (criterio 2.6)', function (): void {
    AttendanceSchedule::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'valid_from' => '2026-11-05']);

    $week = completeness($this, [], schedule: false);

    expect($week->uncoveredDays())->toBe(['2026-11-05', '2026-11-06'])
        ->and($week->day(2))->toBe(['required' => false, 'uncovered' => false]);
});

it('reads each day with the schedule in effect that day when it changes in the middle of the week', function (): void {
    // Until Tuesday they rest on Wednesday; from Wednesday on they work it and rest on Friday.
    AttendanceSchedule::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'valid_from' => '2000-01-01', 'minutes_by_weekday' => [1 => 480, 2 => 480, 3 => 0, 4 => 480, 5 => 480, 6 => 0, 7 => 0]]);
    AttendanceSchedule::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'valid_from' => '2026-11-04', 'minutes_by_weekday' => [1 => 480, 2 => 480, 3 => 480, 4 => 480, 5 => 0, 6 => 0, 7 => 0]]);

    $week = completeness($this, completeDay('2026-11-02') + completeDay('2026-11-03'), schedule: false);

    expect($week->uncoveredDays())->toBe(['2026-11-04', '2026-11-05']);
});

it('keeps reading an earlier week with the schedule it had then', function (): void {
    AttendanceSchedule::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'valid_from' => '2000-01-01', 'minutes_by_weekday' => [1 => 480, 2 => 480, 3 => 0, 4 => 480, 5 => 480, 6 => 0, 7 => 0]]);
    AttendanceSchedule::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'valid_from' => '2026-11-04', 'minutes_by_weekday' => [1 => 480, 2 => 480, 3 => 480, 4 => 480, 5 => 0, 6 => 0, 7 => 0]]);

    expect(completeness($this, [], week: '2026-10-26', schedule: false)->uncoveredDays())->toBe(['2026-10-26', '2026-10-27', '2026-10-29', '2026-10-30']);
});

it('does not ask for a day that has not ended: today or a later one, in the zone of the week', function (): void {
    // Wednesday 11 November, 12:00 in Mexico City: Tuesday the 10th is over, Wednesday is not.
    $this->travelTo(CarbonImmutable::parse('2026-11-11 18:00:00', 'UTC'));
    $week = completeness($this, completeDay('2026-11-09'), week: '2026-11-09');

    expect($week->uncoveredDays())->toBe(['2026-11-10'])
        ->and($week->day(2))->toBe(['required' => false, 'uncovered' => false])
        ->and($week->day(3))->toBe(['required' => false, 'uncovered' => false]);
});

it('takes today in the zone of the week, not in UTC', function (): void {
    // 01:00 UTC of Thursday the 12th is still Wednesday the 11th, 19:00, in Mexico City.
    $this->travelTo(CarbonImmutable::parse('2026-11-12 01:00:00', 'UTC'));

    expect(completeness($this, [], week: '2026-11-09')->uncoveredDays())->toBe(['2026-11-09', '2026-11-10']);
});

it('covers a night shift that ends the next morning: its end belongs to the day it started', function (): void {
    $night = ['2026-11-08' => [
        new AttendanceMark(['type' => MarkType::Start, 'marked_at' => CarbonImmutable::parse('2026-11-09 04:00', 'UTC'), 'timezone' => 'America/Mexico_City']),
        new AttendanceMark(['type' => MarkType::End, 'marked_at' => CarbonImmutable::parse('2026-11-09 12:00', 'UTC'), 'timezone' => 'America/Mexico_City']),
    ]];
    AttendanceSchedule::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'minutes_by_weekday' => [1 => 480, 2 => 480, 3 => 480, 4 => 480, 5 => 480, 6 => 0, 7 => 480]]);

    expect(completeness($this, [...workedWeek(), ...$night], schedule: false)->uncoveredDays())->toBe([]);
});

it('does not call a missing end yesterday\'s: a shift that started then may still be running, but a day with no marks at all is', function (): void {
    // Wednesday 11 November, 12:00 in Mexico City: Tuesday the 10th is yesterday, Monday the 9th the day before.
    $started = fn (string $date): array => [$date => [new AttendanceMark(['type' => MarkType::Start, 'marked_at' => CarbonImmutable::parse("{$date} 04:00", 'UTC')->addDay(), 'timezone' => 'America/Mexico_City'])]];

    $yesterday = completeness($this, $started('2026-11-10'), week: '2026-11-09');

    expect($yesterday->day(1))->toBe(['required' => true, 'uncovered' => false])
        ->and($yesterday->uncoveredDays())->toBe(['2026-11-09'])
        ->and(completeness($this, $started('2026-11-09'), week: '2026-11-09', schedule: false)->day(0))->toBe(['required' => true, 'uncovered' => true]);
});

it('can be read with no day uncovered, keeping which are required', function (): void {
    $missing = completeness($this, workedWeek('2026-11-04'));
    $week = $missing->withoutUncovered();

    expect($missing->uncoveredDays())->toBe(['2026-11-04'])
        ->and($week->uncoveredDays())->toBe([])
        ->and($week->day(2))->toBe(['required' => true, 'uncovered' => false]);
});

it('asks nothing of a day that is not in the week', function (): void {
    expect(completeness($this, [])->day(7))->toBe(['required' => false, 'uncovered' => false]);
});

it('reads nothing from the database', function (): void {
    $timeline = ScheduleTimeline::of([$this->ana->id]);
    DB::enableQueryLog();

    WeekCompleteness::of(WeekPeriod::ofDate(CarbonImmutable::parse(COMPLETE_WEEK), 'America/Mexico_City'), [], $timeline, $this->ana->id, [], [], null)->uncoveredDays();

    expect(DB::getQueryLog())->toBe([]);
});

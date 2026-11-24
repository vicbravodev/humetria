<?php

declare(strict_types=1);

use App\Modules\Attendance\Actions\BuildAuthorityReport;
use App\Modules\Attendance\Actions\CloseDay;
use App\Modules\Attendance\Actions\CloseDays;
use App\Modules\Attendance\Enums\MarkType;
use App\Modules\Attendance\Models\AttendanceMark;
use App\Modules\Attendance\Models\AttendancePolicy;
use App\Modules\Attendance\Models\AttendanceSchedule;
use App\Modules\Attendance\Support\PersonWeek;
use App\Modules\Attendance\Support\ScheduleTimeline;
use App\Modules\Attendance\Support\TeamWeek;
use App\Modules\Attendance\Support\WeekPeriod;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Support\Facades\DB;

mutates(ScheduleTimeline::class, TeamWeek::class, PersonWeek::class, CloseDay::class, CloseDays::class, BuildAuthorityReport::class);

const MEXICO = 'America/Mexico_City';

/** 9 to 6 (9 h, no break) Monday to Friday until the change; from Monday 1 March 2027, 7 to 4 (8 h) Monday to Thursday and Friday off. */
beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2027-03-20 18:00:00', 'UTC'));
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    workIn($this->tenant);
    $this->center = WorkCenter::factory()->for($this->tenant)->create();
    $this->ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'name' => 'Ana López']);
    AttendancePolicy::factory()->create(['tenant_id' => $this->tenant->id, 'late_tolerance_minutes' => 5]);
    $this->old = AttendanceSchedule::factory()->create([
        'tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id,
        'minutes_by_weekday' => [1 => 540, 2 => 540, 3 => 540, 4 => 540, 5 => 540, 6 => 0, 7 => 0],
        'starts_by_weekday' => [1 => 540, 2 => 540, 3 => 540, 4 => 540, 5 => 540, 6 => null, 7 => null],
    ]);
    $this->new = AttendanceSchedule::factory()->create([
        'tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'valid_from' => '2027-03-01',
        'minutes_by_weekday' => [1 => 480, 2 => 480, 3 => 480, 4 => 480, 5 => 0, 6 => 0, 7 => 0],
        'starts_by_weekday' => [1 => 420, 2 => 420, 3 => 420, 4 => 420, 5 => null, 6 => null, 7 => null],
    ]);
});

function readerMark(Employee $person, MarkType $type, string $local): AttendanceMark
{
    return AttendanceMark::factory()->create([
        'tenant_id' => $person->tenant_id, 'employee_id' => $person->id, 'work_center_id' => $person->work_center_id,
        'type' => $type, 'marked_at' => CarbonImmutable::parse($local, MEXICO)->utc(),
    ]);
}

function readerDay(Employee $person, string $date, string $start, string $end): void
{
    readerMark($person, MarkType::Start, "{$date} {$start}");
    readerMark($person, MarkType::End, "{$date} {$end}");
}

describe('the timeline', function (): void {
    it('gives each day the schedule in effect then, none before the first, and the one of the day a new one starts', function (): void {
        $timeline = ScheduleTimeline::of([$this->ana->id]);
        $schedule = fn (string $date): ?string => $timeline->on($this->ana->id, CarbonImmutable::parse($date))?->id;

        expect($schedule('1999-01-01'))->toBe($this->old->id)
            ->and($schedule('2027-02-28'))->toBe($this->old->id)
            ->and($schedule('2027-03-01'))->toBe($this->new->id)
            ->and($schedule('2027-12-31'))->toBe($this->new->id)
            ->and($timeline->on('not-an-employee', CarbonImmutable::parse('2027-03-01')))->toBeNull();

        $first = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id]);
        AttendanceSchedule::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $first->id, 'valid_from' => '2027-03-10']);
        expect(ScheduleTimeline::of([$first->id])->on($first->id, CarbonImmutable::parse('2027-03-09')))->toBeNull();
    });

    it('reads its people in one query and never another company\'s schedules', function (): void {
        $other = Tenant::factory()->create();
        $stranger = EmployeeFactory::new()->create(['tenant_id' => $other->id]);
        AttendanceSchedule::factory()->create(['tenant_id' => $other->id, 'employee_id' => $stranger->id]);
        DB::enableQueryLog();

        $timeline = ScheduleTimeline::of([$this->ana->id, $stranger->id]);

        expect(DB::getQueryLog())->toHaveCount(1)
            ->and($timeline->on($stranger->id, CarbonImmutable::parse('2027-03-01')))->toBeNull()
            ->and(ScheduleTimeline::ofCompany()->working(CarbonImmutable::parse('2027-03-02')))->toBe([$this->ana->id]);
    });
});

describe('the report for the authority', function (): void {
    it('does not give January lateness or overtime because of a schedule that began in March', function (): void {
        // Monday 4 January, 9 to 6: on time and no overtime by the schedule of then; by March's (7 to 4) it would be 2 h late.
        readerDay($this->ana, '2027-01-04', '09:00', '18:00');
        // Monday 8 March, 7:20 to 4 pm: 20 min late by March's, 40 min of overtime over 8 h.
        readerDay($this->ana, '2027-03-08', '07:20', '16:00');

        $rows = resolve(BuildAuthorityReport::class)->handle($this->center->id, CarbonImmutable::parse('2027-01-01'), CarbonImmutable::parse('2027-03-31'))->values()->all();

        expect($rows)->toHaveCount(2)
            ->and($rows[0])->localDate->toBe('2027-01-04')->lateMinutes->toBe(0)->ordinaryMinutes->toBe(540)->doubleMinutes->toBe(0)->tripleMinutes->toBe(0)
            ->and($rows[1])->localDate->toBe('2027-03-08')->lateMinutes->toBe(20)->ordinaryMinutes->toBe(480)->doubleMinutes->toBe(40);
    });

    it('gives the same report for a past period now and after the schedule changes again', function (): void {
        readerDay($this->ana, '2027-01-04', '09:20', '18:00');
        $report = fn (): array => array_map(fn ($row): array => [$row->localDate, $row->lateMinutes, $row->ordinaryMinutes, $row->doubleMinutes], resolve(BuildAuthorityReport::class)->handle($this->center->id, CarbonImmutable::parse('2027-01-01'), CarbonImmutable::parse('2027-01-31'))->values()->all());
        $before = $report();

        AttendanceSchedule::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'valid_from' => '2027-03-15', 'minutes_by_weekday' => [1 => 300, 2 => 300, 3 => 300, 4 => 300, 5 => 300, 6 => 0, 7 => 0]]);

        expect($report())->toBe($before)->and($before)->toBe([['2027-01-04', 20, 520, 0]]);
    });
});

describe('a week with a change in the middle', function (): void {
    // Week of Monday 1 March 2027 changes on Wednesday the 3rd: Monday and Tuesday 9 h, then 8 h with Friday off.
    beforeEach(function (): void {
        AttendanceSchedule::query()->whereKey($this->new->id)->update(['valid_from' => '2027-03-03']);
    });

    it('splits each day with its own schedule in «Mi semana»', function (): void {
        foreach (['2027-03-01', '2027-03-02', '2027-03-03', '2027-03-04'] as $date) {
            readerDay($this->ana, $date, '09:00', '18:00');
        }

        $week = PersonWeek::of($this->ana->id, 'Ana López', WeekPeriod::ofDate(CarbonImmutable::parse('2027-03-01'), MEXICO), 'acme');

        expect(array_column($week['days'], 'scheduledMinutes'))->toBe([540, 540, 480, 480, 0, 0, 0])
            ->and(array_column($week['days'], 'lateMinutes'))->toBe([0, 0, 120, 120, null, null, null])
            ->and(array_column($week['days'], 'restDay'))->toBe([false, false, false, false, true, true, true])
            ->and($week['summary']['overtimeMinutes'])->toBe(120);
    });

    it('splits each day with its own schedule for the whole team, in the queries it took with one schedule', function (): void {
        foreach (['2027-03-01', '2027-03-02', '2027-03-03', '2027-03-04', '2027-03-05'] as $date) {
            readerDay($this->ana, $date, '09:00', '18:00');
        }
        DB::enableQueryLog();

        [$summaries, $worked] = TeamWeek::withDays([$this->ana->id], WeekPeriod::ofDate(CarbonImmutable::parse('2027-03-01'), MEXICO));

        // Wednesday and Thursday 60 min over their 8 h; Friday, now a rest day, is rest-day work.
        expect($summaries[$this->ana->id])->overtimeMinutes->toBe(120)->restDayMinutes->toBe(540)
            ->and($worked[$this->ana->id])->toBe([540, 540, 540, 540, 540, 0, 0])
            ->and(DB::getQueryLog())->toHaveCount(3);
    });
});

describe('closing days', function (): void {
    it('closes a day with no marks only if the schedule of that day made it a working day', function (): void {
        // Friday 26 February was a working day (old schedule); Friday 5 March is a rest day (new one).
        expect(resolve(CloseDay::class)->handle($this->ana->id, CarbonImmutable::parse('2027-02-26', MEXICO), MEXICO))->not->toBeNull()
            ->scheduledMinutes->toBe(540)
            ->and(resolve(CloseDay::class)->handle($this->ana->id, CarbonImmutable::parse('2027-03-05', MEXICO), MEXICO))->toBeNull();
    });

    it('closes by the schedule of the day it closes, in the nightly run', function (string $now, int $closed): void {
        $this->travelTo(CarbonImmutable::parse($now, MEXICO));

        // The night closes the day before yesterday: Friday 26 Feb, a working day then; Friday 5 March, a rest day now.
        expect(resolve(CloseDays::class)->center($this->tenant->id, $this->center->id))->toBe($closed);
    })->with([
        'before the change' => ['2027-02-28 00:15', 1],
        'after it' => ['2027-03-07 00:15', 0],
    ]);
});

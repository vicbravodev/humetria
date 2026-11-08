<?php

declare(strict_types=1);

use App\Modules\Attendance\Data\WeekSummary;
use App\Modules\Attendance\Enums\MarkType;
use App\Modules\Attendance\Enums\OverLimit;
use App\Modules\Attendance\Models\AttendanceMark;
use App\Modules\Attendance\Models\AttendanceSchedule;
use App\Modules\Attendance\Support\TeamWeek;
use App\Modules\Attendance\Support\WeekPeriod;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

mutates(TeamWeek::class);

function weekWith(int $overtime, array $flags = []): WeekSummary
{
    return new WeekSummary(2400, $overtime, $overtime, 0, 0, $flags, []);
}

describe('at risk', function (): void {
    it('puts a week at risk from 80 % of the year\'s weekly overtime cap', function (int $year, int $below): void {
        expect(TeamWeek::atRisk(weekWith($below), $year))->toBeFalse()
            ->and(TeamWeek::atRisk(weekWith($below + 1), $year))->toBeTrue();
    })->with([
        // 2026: 9 h a week, 80 % is 432 minutes; 2030: 12 h, 576.
        '2026' => [2026, 431],
        '2030' => [2030, 575],
    ]);

    it('puts a week that broke any limit at risk, whatever its overtime', function (OverLimit $flag): void {
        expect(TeamWeek::atRisk(weekWith(0, [$flag]), 2026))->toBeTrue();
    })->with(OverLimit::cases());

    it('leaves a week with no overtime and no limit broken out', function (): void {
        expect(TeamWeek::atRisk(weekWith(0), 2026))->toBeFalse();
    });
});

describe('the weeks of several people', function (): void {
    beforeEach(function (): void {
        config(['app.display_timezone' => 'America/Mexico_City']);
        $this->tenant = Tenant::factory()->create();
        $this->center = WorkCenter::factory()->for($this->tenant)->create();
        workIn($this->tenant);
    });

    it('splits each person\'s week by their own schedule, in a fixed number of queries', function (): void {
        $people = EmployeeFactory::new()->count(3)->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);
        [$ana, $bob, $carla] = [$people[0], $people[1], $people[2]];
        // Ana works 10 h on Tuesday against her 8: 2 h of overtime.
        foreach ([[MarkType::Start, '2026-11-03 08:00'], [MarkType::End, '2026-11-03 18:00']] as [$type, $local]) {
            AttendanceMark::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $ana->id, 'work_center_id' => $this->center->id, 'type' => $type, 'marked_at' => CarbonImmutable::parse($local, 'America/Mexico_City')]);
        }
        // Bob has a schedule that rests on Tuesday: his 6 h are a rest day's.
        AttendanceSchedule::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $bob->id, 'minutes_by_weekday' => [1 => 480, 2 => 0, 3 => 480, 4 => 480, 5 => 480, 6 => 480, 7 => 0]]);
        foreach ([[MarkType::Start, '2026-11-03 08:00'], [MarkType::End, '2026-11-03 14:00']] as [$type, $local]) {
            AttendanceMark::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $bob->id, 'work_center_id' => $this->center->id, 'type' => $type, 'marked_at' => CarbonImmutable::parse($local, 'America/Mexico_City')]);
        }
        DB::enableQueryLog();

        $weeks = TeamWeek::summaries([$ana->id, $bob->id, $carla->id], WeekPeriod::ofDate(CarbonImmutable::parse('2026-11-04')));

        expect(count(DB::getQueryLog()))->toBe(3)
            ->and(array_keys($weeks))->toBe([$ana->id, $bob->id, $carla->id])
            ->and($weeks[$ana->id])->ordinaryMinutes->toBe(480)->overtimeMinutes->toBe(120)->restDayMinutes->toBe(0)
            ->and($weeks[$bob->id])->ordinaryMinutes->toBe(0)->overtimeMinutes->toBe(0)->restDayMinutes->toBe(360)
            ->and($weeks[$carla->id])->ordinaryMinutes->toBe(0)->overtimeMinutes->toBe(0)->flags->toBe([])
            ->and(TeamWeek::summaries([], WeekPeriod::ofDate(CarbonImmutable::parse('2026-11-04'))))->toBe([]);
    });
});

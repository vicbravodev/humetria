<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Support;

use App\Modules\Attendance\Enums\ShiftType;
use App\Modules\Attendance\Models\AttendanceSchedule;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Organization\Data\RosterEntry;
use App\Modules\Organization\Data\RosterQuery;
use App\Modules\Tenancy\Contracts\WorkCenters;
use App\Modules\Tenancy\Data\WorkCenterSummary;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;

/**
 * What RH's page of schedules sends to the browser: the people active in the centers in use (or in one), how many have an
 * agreed schedule and how many not (they are held to 8 h a day with no rest day until RH sets one), then the people of one
 * tab, 25 at a time, searched by name, each with their schedule; and the year's weekly ordinary maximum, overtime cap and
 * each shift's daily limit to compare with.
 */
final readonly class SchedulePages
{
    private const int PER_PAGE = 25;

    private const array VIEWS = ['all', 'scheduled', 'unscheduled'];

    public function __construct(private Roster $roster, private WorkCenters $workCenters) {}

    /**
     * @return array<string, mixed>
     */
    public function index(?WorkCenterSummary $center, string $view, string $search): array
    {
        $scope = PanelScope::of($this->workCenters, $this->roster, $center);
        $scheduled = AttendanceSchedule::query()->among('employee_id', $scope->people)->get(['employee_id'])
            ->map(fn (AttendanceSchedule $schedule): string => $schedule->employee_id)->all();
        $unscheduled = array_values(array_diff($scope->people, $scheduled));
        $view = in_array($view, self::VIEWS, true) ? $view : 'all';
        $page = $this->roster->page(new RosterQuery(
            employeeIds: match ($view) {
                'scheduled' => array_values(array_intersect($scope->people, $scheduled)),
                'unscheduled' => $unscheduled,
                default => $scope->people,
            },
            search: $search,
        ), self::PER_PAGE)->withQueryString();
        $schedules = AttendanceSchedule::query()
            ->among('employee_id', array_values(array_map(fn (RosterEntry $person): string => $person->id, $page->items())))
            ->get(['employee_id', 'shift_type', 'work_mode', 'minutes_by_weekday'])->keyBy('employee_id');
        $year = CarbonImmutable::now(config()->string('app.display_timezone'))->year;

        return [
            'center' => $center === null ? null : ['id' => $center->id, 'name' => $center->name],
            'filters' => ['q' => $search, 'status' => $view],
            'counts' => ['all' => count($scope->people), 'scheduled' => count($scope->people) - count($unscheduled), 'unscheduled' => count($unscheduled)],
            'weeklyMaximum' => [
                'year' => $year,
                'minutes' => LegalLimits::weeklyOrdinaryMinutes($year),
                'overtimeMinutes' => LegalLimits::weeklyOvertimeCapMinutes($year),
                'dailyMinutes' => array_combine(
                    array_map(fn (ShiftType $shift): string => $shift->value, ShiftType::cases()),
                    array_map(LegalLimits::dailyOrdinaryMinutes(...), ShiftType::cases()),
                ),
            ],
            'people' => $page->through(fn (RosterEntry $person): array => [
                'id' => $person->id,
                'name' => $person->name,
                'jobTitle' => $person->jobTitle,
                'center' => $scope->names[$person->workCenterId] ?? '—',
                'schedule' => ($schedule = $schedules->get($person->id)) === null ? null : [
                    'shiftType' => $schedule->shift_type->value,
                    'workMode' => $schedule->work_mode->value,
                    'minutes' => $schedule->minutes_by_weekday,
                ],
            ]),
            'canManage' => Gate::allows('manage-attendance'),
        ];
    }
}

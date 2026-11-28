<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Support;

use App\Modules\Attendance\Enums\ShiftType;
use App\Modules\Attendance\Models\AttendanceSchedule;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Organization\Data\RosterEntry;
use App\Modules\Organization\Data\RosterQuery;
use App\Modules\Tenancy\Contracts\Timezones;
use App\Modules\Tenancy\Contracts\WorkCenters;
use App\Modules\Tenancy\Data\WorkCenterSummary;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * What RH's page of schedules sends to the browser: the people active in the centers in use (or in one), how many have an
 * agreed schedule and how many not (they are held to 8 h a day with no rest day until RH sets one), then the people of one
 * tab, 25 at a time, searched by name, each with their schedule; and the year's weekly ordinary maximum, overtime cap and
 * each shift's daily limit to compare with. Each person comes with the schedule in effect today, the ones still to start
 * (the soonest first) and the ones that ended (the newest first): a schedule is never erased, each day is read with the one
 * in effect that day. Whoever has none started yet counts as without one.
 */
final readonly class SchedulePages
{
    private const int PER_PAGE = 25; // @pest-mutate-ignore: DecrementInteger,IncrementInteger

    private const int COPIES = 20; // @pest-mutate-ignore: DecrementInteger,IncrementInteger

    private const array VIEWS = ['all', 'scheduled', 'unscheduled']; // @pest-mutate-ignore: RemoveArrayItem

    public function __construct(private Roster $roster, private WorkCenters $workCenters, private Timezones $timezones) {}

    /**
     * @return array<string, mixed>
     */
    public function index(?WorkCenterSummary $center, string $view, string $search): array
    {
        // Today where the company is: what is in effect, and the year whose weekly overtime cap applies.
        $now = CarbonImmutable::now($this->timezones->forTenant());
        $today = $now->toDateString();
        $scope = PanelScope::of($this->workCenters, $this->roster, $center);
        $scheduled = AttendanceSchedule::query()->among('employee_id', $scope->people)->startedBy($today)->get(['employee_id'])
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
            ->orderBy('valid_from')
            ->get(['employee_id', 'valid_from', 'shift_type', 'work_mode', 'minutes_by_weekday', 'starts_by_weekday'])->groupBy('employee_id');
        $year = $now->year;

        return [
            'center' => $center === null ? null : ['id' => $center->id, 'name' => $center->name],
            'today' => $today,
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
            'people' => $page->through(function (RosterEntry $person) use ($scope, $schedules, $today): array {
                [$current, $upcoming, $history] = self::timeline($schedules->get($person->id), $today);

                return [
                    'id' => $person->id,
                    'name' => $person->name,
                    'jobTitle' => $person->jobTitle,
                    'center' => $scope->names[$person->workCenterId] ?? '—',
                    'schedule' => $current === null ? null : self::schedule($current),
                    'upcoming' => array_map(self::schedule(...), $upcoming),
                    'history' => array_map(self::schedule(...), $history),
                ];
            }),
            'canManage' => Gate::allows('manage-attendance'),
        ];
    }

    /**
     * The people active in the centers in use who have a schedule in effect today, searched by name, the first 20, each with it.
     *
     * @return list<array{value: string, label: string, schedule: array<string, mixed>}>
     */
    public function copies(string $search): array
    {
        $scope = PanelScope::of($this->workCenters, $this->roster, null);
        // The latest that has started wins the key: the one in effect today.
        $schedules = AttendanceSchedule::query()->among('employee_id', $scope->people)
            ->startedBy(CarbonImmutable::now($this->timezones->forTenant())->toDateString())->orderBy('valid_from')
            ->get(['employee_id', 'valid_from', 'shift_type', 'work_mode', 'minutes_by_weekday', 'starts_by_weekday'])->keyBy('employee_id');
        $found = $this->roster->page(new RosterQuery(employeeIds: array_values(array_map(strval(...), $schedules->keys()->all())), search: $search), self::COPIES)->items();

        return array_values(array_filter(array_map(fn (RosterEntry $person): ?array => ($schedule = $schedules->get($person->id)) === null ? null : [
            'value' => $person->id,
            'label' => $person->name,
            'schedule' => self::schedule($schedule),
        ], $found)));
    }

    /**
     * A person's schedules split by what they are today.
     *
     * @param  Collection<int, AttendanceSchedule>|null  $rows  the earliest start first; null for a person with none
     * @return array{0: AttendanceSchedule|null, 1: list<AttendanceSchedule>, 2: list<AttendanceSchedule>} the one in effect, the ones to start (soonest first) and the ones that ended (newest first)
     */
    private static function timeline(?Collection $rows, string $today): array
    {
        $schedules = $rows?->values()->all() ?? [];
        $started = array_values(array_filter($schedules, fn (AttendanceSchedule $schedule): bool => $schedule->valid_from->toDateString() <= $today));
        $current = array_pop($started);

        return [
            $current,
            array_values(array_filter($schedules, fn (AttendanceSchedule $schedule): bool => $schedule->valid_from->toDateString() > $today)),
            array_reverse($started),
        ];
    }

    /**
     * @return array{validFrom: string|null, shiftType: string, workMode: string, minutes: array<int, int>, starts: array<int, int|null>|null} validFrom is null for «since always»
     */
    private static function schedule(AttendanceSchedule $schedule): array
    {
        return [
            'validFrom' => $schedule->valid_from->toDateString() === AttendanceSchedule::SINCE_ALWAYS ? null : $schedule->valid_from->toDateString(),
            'shiftType' => $schedule->shift_type->value,
            'workMode' => $schedule->work_mode->value,
            'minutes' => $schedule->minutes_by_weekday,
            'starts' => $schedule->starts_by_weekday,
        ];
    }
}

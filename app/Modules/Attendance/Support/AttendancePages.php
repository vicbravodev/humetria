<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Support;

use App\Modules\Attendance\Enums\CorrectionStatus;
use App\Modules\Attendance\Enums\OverLimit;
use App\Modules\Attendance\Enums\WeekStatus;
use App\Modules\Attendance\Models\AttendanceZone;
use App\Modules\Attendance\Models\CorrectionRequest;
use App\Modules\Attendance\Models\KioskDevice;
use App\Modules\Attendance\Models\WeeklyConfirmation;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Organization\Data\RosterEntry;
use App\Modules\Organization\Data\RosterQuery;
use App\Modules\Tenancy\Contracts\WorkCenters;
use App\Modules\Tenancy\Data\WorkCenterSummary;
use Carbon\CarbonImmutable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

/**
 * What RH's overview of the registro sends to the browser: one week (the last that ended unless another is asked for,
 * never one to come) of the people active in the company's centers in use, or in one of them. How many are al día,
 * objetadas, sin confirmar and near the year's limits, the correction requests waiting and the centers with no zone;
 * then the people of one state, 25 at a time, searched by name, with their hours and the limits they broke, and the
 * centers 25 at a time. Never a position, a coordinate or a device.
 */
final readonly class AttendancePages
{
    private const int PER_PAGE = 25;

    /** The tabs of the list: every person, each answer, and the weeks at risk. */
    private const array VIEWS = ['all', 'pending', 'disputed', 'confirmed', 'at_risk'];

    public function __construct(private Roster $roster, private WorkCenters $workCenters) {}

    /**
     * @return array<string, mixed>
     */
    public function index(?WorkCenterSummary $center, string $week, string $view, string $search): array
    {
        $period = $this->week($week);
        $centers = array_filter($this->workCenters->all(), fn (WorkCenterSummary $summary): bool => ! $summary->archived);
        $names = array_column(array_map(fn (WorkCenterSummary $summary): array => [$summary->id, $summary->name], $centers), 1, 0);
        $byCenter = $names === [] ? [] : $this->roster->activeIdsInMany(array_keys($names));
        $scope = $center === null ? array_keys($names) : [$center->id];
        $people = array_merge([], ...array_values(array_intersect_key($byCenter, array_flip($scope))));
        $summaries = TeamWeek::summaries($people, $period);
        $answers = WeeklyConfirmation::query()->among('employee_id', $people)->where('week_start', $period->start()->toDateString())
            ->toBase()->pluck('status', 'employee_id')->all();
        $states = [];
        foreach ($people as $id) {
            $states[$id] = is_string($answers[$id] ?? null) ? $answers[$id] : WeekStatus::Pending->value;
        }
        $atRisk = array_keys(array_filter($summaries, fn ($summary): bool => TeamWeek::atRisk($summary, $period->start()->year)));
        $zones = AttendanceZone::query()->toBase()->selectRaw('work_center_id, count(*) as total')->groupBy('work_center_id')->pluck('total', 'work_center_id')->all();
        $view = in_array($view, self::VIEWS, true) ? $view : 'all';

        return [
            'week' => [
                'start' => $period->start()->toDateString(),
                'end' => $period->end()->toDateString(),
                'over' => $period->isOver(),
                'previous' => $period->start()->subWeek()->toDateString(),
                'next' => $period->isOver() ? $period->start()->addWeek()->toDateString() : null,
            ],
            'center' => $center === null ? null : ['id' => $center->id, 'name' => $center->name],
            'filters' => ['q' => $search, 'status' => $view],
            'counts' => [
                'all' => count($people),
                ...array_combine(
                    array_map(fn (WeekStatus $status): string => $status->value, WeekStatus::cases()),
                    array_map(fn (WeekStatus $status): int => count(array_keys($states, $status->value, true)), WeekStatus::cases()),
                ),
                'at_risk' => count($atRisk),
            ],
            'pendingCorrections' => CorrectionRequest::query()->where('status', CorrectionStatus::Pending)->count(),
            'centersWithoutZones' => count(array_diff($scope, array_keys($zones))),
            'people' => $this->roster->page(new RosterQuery(employeeIds: match ($view) {
                'all' => $people,
                'at_risk' => $atRisk,
                default => array_keys($states, $view, true),
            }, search: $search), self::PER_PAGE)->through(fn (RosterEntry $person): array => [
                'id' => $person->id,
                'name' => $person->name,
                'jobTitle' => $person->jobTitle,
                'center' => $names[$person->workCenterId] ?? '—',
                'ordinaryMinutes' => $summaries[$person->id]->ordinaryMinutes ?? 0,
                'overtimeMinutes' => $summaries[$person->id]->overtimeMinutes ?? 0,
                'flags' => array_map(fn (OverLimit $flag): string => $flag->value, $summaries[$person->id]->flags ?? []),
                'status' => $states[$person->id] ?? WeekStatus::Pending->value,
                'atRisk' => in_array($person->id, $atRisk, true),
            ]),
            'centers' => $this->centers($byCenter, $zones),
            'canManage' => Gate::allows('manage-attendance'),
        ];
    }

    /**
     * The centers in use, 25 at a time: their active people, their zones and the screens that show their code.
     *
     * @param  array<string, list<string>>  $byCenter
     * @param  array<array-key, mixed>  $zones
     * @return LengthAwarePaginator<int, array{id: string, name: string, people: int, zones: int, screens: int}>
     */
    private function centers(array $byCenter, array $zones): LengthAwarePaginator
    {
        $page = $this->workCenters->activePage(self::PER_PAGE, 'centers');
        $ids = array_map(fn (WorkCenterSummary $summary): string => $summary->id, $page->items());
        $screens = KioskDevice::query()->toBase()->whereIn('work_center_id', $ids)->whereNotNull('activated_at')->whereNull('revoked_at')
            ->selectRaw('work_center_id, count(*) as total')->groupBy('work_center_id')->pluck('total', 'work_center_id')->all();

        return $page->through(fn (WorkCenterSummary $summary): array => [
            'id' => $summary->id,
            'name' => $summary->name,
            'people' => count($byCenter[$summary->id] ?? []),
            'zones' => is_int($zones[$summary->id] ?? null) ? $zones[$summary->id] : 0,
            'screens' => is_int($screens[$summary->id] ?? null) ? $screens[$summary->id] : 0,
        ]);
    }

    /** The week asked for by any of its days, the last one that ended by default; one to come is the week in course. */
    private function week(string $day): WeekPeriod
    {
        $current = WeekPeriod::containing(CarbonImmutable::now());
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) === 1 ? CarbonImmutable::createFromFormat('!Y-m-d', $day, config()->string('app.display_timezone')) : null;

        // createFromFormat rolls 2026-02-30 over to March: only a date that reads back the same is one.
        if (! $date instanceof CarbonImmutable || $date->format('Y-m-d') !== $day) {
            return WeekPeriod::ofDate($current->start()->subWeek());
        }

        $asked = WeekPeriod::ofDate($date);

        return $asked->start()->greaterThan($current->start()) ? $current : $asked;
    }
}

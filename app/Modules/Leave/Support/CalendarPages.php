<?php

declare(strict_types=1);

namespace App\Modules\Leave\Support;

use App\Modules\Leave\Contracts\Absences;
use App\Modules\Leave\Data\AbsenceData;
use App\Modules\Leave\Enums\RequestStatus;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Organization\Contracts\Areas;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Organization\Data\RosterEntry;
use App\Modules\Organization\Data\RosterQuery;
use App\Modules\Tenancy\Contracts\WorkCenters;
use Carbon\CarbonImmutable;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * The team's calendar of a month: a row per person with the stretches they are away, cut to the month, and how many are out
 * each day. It only says vacations or leave, never which leave (criterio C-22). Whoever manages vacations also sees, dotted,
 * what still waits for an answer.
 *
 * @phpstan-type Stretch array{from: string, to: string, kind: string, pending: bool}
 * @phpstan-type Away array{employeeId: string, from: CarbonImmutable, to: CarbonImmutable, kind: string, pending: bool}
 */
final readonly class CalendarPages
{
    public const int PER_PAGE = 25;

    public function __construct(private Roster $roster, private Absences $absences, private WorkCenters $centers, private Areas $areas) {}

    /**
     * The first day of the month a text names (Y-m); the current one when it is not a month nor a believable year.
     */
    public static function monthOf(string $asked): CarbonImmutable
    {
        if (preg_match('/^(20\d{2}|2100)-(0[1-9]|1[0-2])$/', $asked) === 1) {
            return CarbonImmutable::parse("{$asked}-01");
        }

        return PeriodWindow::today()->startOfMonth();
    }

    /**
     * @return array{month: string, showAll: bool, counts: array{absent: int, everyone: int}, prevMonth: string, nextMonth: string, today: string, out: list<int>, holidays: list<string>, filters: array{center: array{value: string, label: string}|null, area: array{value: string, label: string}|null}, canManage: bool, people: LengthAwarePaginator<int, array{id: string, name: string, absences: list<Stretch>}>}
     */
    public function index(CarbonImmutable $first, ?string $centerId, ?string $areaId, bool $canManage, bool $onlyAbsent = true): array
    {
        $last = $first->endOfMonth()->startOfDay();
        $query = new RosterQuery(activeInWorkCenter: $centerId, areaId: $areaId);
        $everyone = $this->roster->matchingIds($query);
        $away = $this->absences->between($first, $last, $everyone);
        $pending = $canManage ? self::pending($first, $last, $everyone) : [];
        $stretches = [...array_map(fn (AbsenceData $absence): array => ['employeeId' => $absence->employeeId, 'from' => $absence->from, 'to' => $absence->to, 'kind' => $absence->kind, 'pending' => false], $away), ...$pending];
        $absent = array_values(array_unique(array_map(fn (array $stretch): string => $stretch['employeeId'], $stretches)));
        $page = $this->roster->page($onlyAbsent ? new RosterQuery(employeeIds: $absent) : $query, self::PER_PAGE);

        return [
            'month' => $first->format('Y-m'),
            'showAll' => ! $onlyAbsent,
            'counts' => ['absent' => count($absent), 'everyone' => count($everyone)],
            'prevMonth' => $first->subMonthNoOverflow()->format('Y-m'),
            'nextMonth' => $first->addMonthNoOverflow()->format('Y-m'),
            'today' => PeriodWindow::today()->toDateString(),
            'out' => self::out($first, $last, $away),
            'holidays' => Holidays::between($first, $last),
            'filters' => [
                'center' => $centerId === null ? null : ['value' => $centerId, 'label' => $this->centers->findActive($centerId)->name ?? ''],
                'area' => $areaId === null ? null : ['value' => $areaId, 'label' => $this->areas->name($areaId) ?? ''],
            ],
            'canManage' => $canManage,
            'people' => $page->through(fn (RosterEntry $person): array => [
                'id' => $person->id,
                'name' => $person->name,
                'absences' => self::stretches($person->id, $stretches, $first, $last),
            ]),
        ];
    }

    /**
     * How many people are away each day of the month, from the first.
     *
     * @param  list<AbsenceData>  $away
     * @return list<int>
     */
    private static function out(CarbonImmutable $first, CarbonImmutable $last, array $away): array
    {
        $people = array_fill(0, $first->daysInMonth, []);

        foreach ($away as $absence) {
            for ($day = max($absence->from, $first); $day <= min($absence->to, $last); $day = $day->addDay()) {
                $people[$day->day - 1][$absence->employeeId] = true;
            }
        }

        return array_map(count(...), $people);
    }

    /**
     * What still waits for an answer and touches the month, of the people asked.
     *
     * @param  list<string>  $ids
     * @return list<Away>
     */
    private static function pending(CarbonImmutable $first, CarbonImmutable $last, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return array_values(LeaveRequest::query()->among('employee_id', $ids)->where('status', RequestStatus::Requested)
            ->where('starts_on', '<=', $last->toDateString())->where('ends_on', '>=', $first->toDateString())
            ->orderBy('starts_on')->orderBy('id')->get(['employee_id', 'leave_type_id', 'starts_on', 'ends_on'])
            ->map(fn (LeaveRequest $request): array => ['employeeId' => $request->employee_id, 'from' => $request->starts_on, 'to' => $request->ends_on, 'kind' => $request->leave_type_id === null ? 'vacation' : 'leave', 'pending' => true])
            ->all());
    }

    /**
     * The stretches of one person cut to the month, from the earliest.
     *
     * @param  list<Away>  $absences
     * @return list<Stretch>
     */
    private static function stretches(string $employeeId, array $absences, CarbonImmutable $first, CarbonImmutable $last): array
    {
        $mine = array_values(array_filter($absences, fn (array $absence): bool => $absence['employeeId'] === $employeeId));
        usort($mine, fn (array $a, array $b): int => $a['from'] <=> $b['from']);

        return array_map(fn (array $absence): array => [
            'from' => max($absence['from'], $first)->toDateString(),
            'to' => min($absence['to'], $last)->toDateString(),
            'kind' => $absence['kind'],
            'pending' => $absence['pending'],
        ], $mine);
    }
}

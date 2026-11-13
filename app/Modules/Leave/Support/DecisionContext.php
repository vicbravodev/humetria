<?php

declare(strict_types=1);

namespace App\Modules\Leave\Support;

use App\Modules\Leave\Enums\RequestStatus;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Organization\Contracts\ReportingLines;
use App\Modules\Organization\Contracts\Roster;
use Carbon\CarbonImmutable;

/**
 * What RH reads next to a request that waits for an answer, for a whole page of them at once (a few queries whatever the
 * page holds): whether the days cover it (the same assessment approving makes) and which teammates of the same manager are
 * away on those days. Of the teammates it says only that they are away, never why, nor which leave it is (criterio C-22).
 */
final readonly class DecisionContext
{
    /** The strip of days shows at most these, from the first day of the request. */
    public const int STRIP_DAYS = 14;

    public function __construct(private ReportingLines $lines, private Roster $roster, private PolicyResolver $policies) {}

    /**
     * @param  list<LeaveRequest>  $requests
     * @return array<string, array{coverage: array{fits: bool, leftAfter: int}|null, canApprove: bool, team: array{days: list<array{date: string, away: int}>, names: list<string>}|null}> by request id, only for those that wait
     */
    public function for(array $requests, bool $withCoverage, bool $withTeam = true): array
    {
        $waiting = array_values(array_filter($requests, fn (LeaveRequest $request): bool => $request->status === RequestStatus::Requested));

        if ($waiting === []) {
            return [];
        }

        $coverage = $withCoverage ? $this->coverage($waiting) : [];
        $advance = $withCoverage ? $this->allowsAdvance($waiting, $coverage) : [];
        $team = $withTeam ? $this->team($waiting) : [];
        $context = [];

        foreach ($waiting as $request) {
            // What approving would refuse (no days, and a policy that does not give them in advance) is not offered; a leave uses none.
            $canApprove = ($coverage[$request->id]['fits'] ?? true) || ($advance[$request->id] ?? false);
            $context[$request->id] = ['coverage' => $coverage[$request->id] ?? null, 'canApprove' => $canApprove, 'team' => $team[$request->id] ?? null];
        }

        return $context;
    }

    /**
     * @param  list<LeaveRequest>  $waiting
     * @return array<string, array{fits: bool, leftAfter: int}> by request id; a leave uses no days and is not in it
     */
    private function coverage(array $waiting): array
    {
        $vacations = array_values(array_filter($waiting, fn (LeaveRequest $request): bool => $request->leave_type_id === null));
        $periods = Balances::of(array_values(array_unique(array_map(fn (LeaveRequest $request): string => $request->employee_id, $vacations))));
        $coverage = [];

        foreach ($vacations as $request) {
            $approval = Approval::assess($periods[$request->employee_id] ?? [], $request->working_days);
            $coverage[$request->id] = ['fits' => $approval->fits(), 'leftAfter' => $approval->leftAfter];
        }

        return $coverage;
    }

    /**
     * @param  list<LeaveRequest>  $waiting
     * @param  array<string, array{fits: bool, leftAfter: int}>  $coverage
     * @return array<string, bool> by request id, for the ones the days do not cover: whether the policy of the person gives days in advance
     */
    private function allowsAdvance(array $waiting, array $coverage): array
    {
        // The policy is read only for those who lack days: whoever fits does not need it.
        $vacations = array_values(array_filter($waiting, fn (LeaveRequest $request): bool => isset($coverage[$request->id]) && ! $coverage[$request->id]['fits']));
        if ($vacations === []) {
            return [];
        }

        $terms = $this->policies->for(array_values(array_unique(array_map(fn (LeaveRequest $request): string => $request->employee_id, $vacations))));

        return array_column(array_map(fn (LeaveRequest $request): array => ['id' => $request->id, 'advance' => ($terms[$request->employee_id] ?? null)?->allowsAdvance ?? false], $vacations), 'advance', 'id');
    }

    /**
     * @param  list<LeaveRequest>  $waiting
     * @return array<string, array{days: list<array{date: string, away: int}>, names: list<string>}>
     */
    private function team(array $waiting): array
    {
        $peers = $this->lines->peersOf(array_values(array_unique(array_map(fn (LeaveRequest $request): string => $request->employee_id, $waiting))));
        $everyone = array_values(array_unique(array_merge(...array_values($peers))));
        $away = $everyone === [] ? [] : $this->away($everyone, $waiting);
        $names = $this->roster->findMany(array_values(array_unique(array_map(fn (array $stretch): string => $stretch[0], $away))));
        $team = [];

        foreach ($waiting as $request) {
            $mine = $peers[$request->employee_id] ?? [];
            $days = [];
            $present = [];

            foreach (self::strip($request) as $day) {
                $out = array_values(array_filter($mine, fn (string $peer): bool => self::isOut($away, $peer, $day)));
                $present = [...$present, ...$out];
                $days[] = ['date' => $day->toDateString(), 'away' => count($out)];
            }

            $team[$request->id] = [
                'days' => $days,
                'names' => array_values(array_map(fn (string $peer): string => ($names[$peer] ?? null)->name ?? '', array_filter($mine, fn (string $peer): bool => in_array($peer, $present, true)))),
            ];
        }

        return $team;
    }

    /**
     * The absences of the people among `$people` that touch the days of the requests: approved or enjoyed.
     *
     * @param  list<string>  $people
     * @param  list<LeaveRequest>  $waiting
     * @return list<array{string, CarbonImmutable, CarbonImmutable}> employee id, first and last day
     */
    private function away(array $people, array $waiting): array
    {
        $first = $waiting[0]->starts_on;
        $last = $waiting[0]->starts_on->addDays(self::STRIP_DAYS - 1);

        foreach ($waiting as $request) {
            $first = min($first, $request->starts_on);
            $last = max($last, $request->starts_on->addDays(self::STRIP_DAYS - 1));
        }

        return array_values(array_map(
            fn (LeaveRequest $row): array => [$row->employee_id, $row->starts_on, $row->ends_on],
            LeaveRequest::query()
                ->among('employee_id', $people)
                ->whereIn('status', [RequestStatus::Approved, RequestStatus::Taken])
                ->where('starts_on', '<=', $last->toDateString())
                ->where('ends_on', '>=', $first->toDateString())
                ->get(['id', 'employee_id', 'starts_on', 'ends_on'])
                ->all(),
        ));
    }

    /**
     * @return list<CarbonImmutable>
     */
    private static function strip(LeaveRequest $request): array
    {
        $days = [];

        for ($day = $request->starts_on; $day <= $request->ends_on && count($days) < self::STRIP_DAYS; $day = $day->addDay()) {
            $days[] = $day;
        }

        return $days;
    }

    /**
     * @param  list<array{string, CarbonImmutable, CarbonImmutable}>  $away
     */
    private static function isOut(array $away, string $person, CarbonImmutable $day): bool
    {
        foreach ($away as [$who, $from, $to]) {
            if ($who === $person && $from <= $day && $to >= $day) {
                return true;
            }
        }

        return false;
    }
}

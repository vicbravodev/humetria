<?php

declare(strict_types=1);

namespace App\Modules\Leave\Support;

use App\Modules\Leave\Enums\RequestStatus;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Models\LeaveType;
use App\Modules\Organization\Contracts\Roster;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * What a manager reads in their portal to answer: only the requests that wait for them, each with where its days would
 * come from today. Whoever approves does see the type of a leave (criterio C-22). Nothing of who did not ask, and nobody's id.
 */
final readonly class PortalTeamPages
{
    public const int PER_PAGE = 10;

    public function __construct(private Roster $roster, private PolicyResolver $policies) {}

    /**
     * How many requests wait for this person's answer.
     */
    public static function pendingFor(string $approverId): int
    {
        return self::waitingFor($approverId)->count();
    }

    /**
     * The requests that wait for this person, the oldest first: the same queries whatever their number.
     *
     * @return LengthAwarePaginator<int, array{id: string, name: string, type: string|null, startsOn: string, endsOn: string, workingDays: int, note: string|null, periods: list<array{year: int, days: int}>, shortBy: int, allowsAdvance: bool, canApprove: bool}>
     */
    public function requests(string $approverId): LengthAwarePaginator
    {
        $page = self::waitingFor($approverId)->orderBy('requested_on')->orderBy('starts_on')->orderBy('id')->paginate(self::PER_PAGE)->withQueryString();
        $ids = array_values(array_unique(array_map(fn (LeaveRequest $request): string => $request->employee_id, $page->items())));
        $types = LeaveType::query()->whereIn('id', array_filter(array_map(fn (LeaveRequest $request): ?string => $request->leave_type_id, $page->items())))->get(['id', 'name'])->mapWithKeys(fn (LeaveType $type): array => [$type->id => $type->name]);
        $people = $this->roster->findMany($ids);
        $balances = Balances::of($ids);
        $terms = $this->policies->for($ids);

        return $page->through(function (LeaveRequest $request) use ($types, $people, $balances, $terms): array {
            $vacations = $request->leave_type_id === null;
            $periods = $vacations ? $balances[$request->employee_id] ?? [] : [];
            // A leave uses no balance; vacations are charged as they would be if approved now (criterio C-16).
            $allocation = match (true) {
                ! $vacations => new Allocation([], 0),
                $periods === [] => new Allocation([], $request->working_days),
                default => Allocator::allocate($periods, $request->working_days),
            };
            $allowsAdvance = $vacations && ($terms[$request->employee_id]->allowsAdvance ?? false);

            return [
                'id' => $request->id,
                'name' => ($people[$request->employee_id] ?? null)->name ?? '',
                'type' => $vacations ? null : $types->get($request->leave_type_id, 'Permiso'),
                'startsOn' => $request->starts_on->toDateString(),
                'endsOn' => $request->ends_on->toDateString(),
                'workingDays' => $request->working_days,
                'note' => $request->note,
                'periods' => self::byPeriod($periods, $allocation),
                'shortBy' => $allocation->shortBy,
                'allowsAdvance' => $allowsAdvance,
                'canApprove' => $allocation->covered() || $allowsAdvance,
            ];
        });
    }

    /**
     * The days each period would give, both tranches together, from the oldest.
     *
     * @param  list<PeriodBalance>  $periods  from the oldest
     * @return list<array{year: int, days: int}>
     */
    private static function byPeriod(array $periods, Allocation $allocation): array
    {
        $given = [];

        foreach ($periods as $period) {
            $days = array_sum(array_map(fn (AllocationLine $line): int => $line->entitlementId === $period->entitlementId ? $line->days : 0, $allocation->lines));

            if ($days > 0) {
                $given[] = ['year' => $period->anniversaryOn->year, 'days' => $days];
            }
        }

        return $given;
    }

    /**
     * @return Builder<LeaveRequest>
     */
    private static function waitingFor(string $approverId): Builder
    {
        return LeaveRequest::query()->where('approver_employee_id', $approverId)->where('status', RequestStatus::Requested);
    }
}

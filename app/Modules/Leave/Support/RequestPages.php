<?php

declare(strict_types=1);

namespace App\Modules\Leave\Support;

use App\Modules\Leave\Enums\EscalationReason;
use App\Modules\Leave\Enums\RequestStatus;
use App\Modules\Leave\Models\LeaveEntitlement;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Models\LeaveType;
use App\Modules\Organization\Contracts\Areas;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Organization\Data\RosterQuery;
use App\Modules\Tenancy\Contracts\WorkCenters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * RH's queue of requests: what reached them first, then what waits for the manager and what was answered, each tab with
 * how many it holds, narrowed by name, work center and area (the tabs count what the filters leave). Whoever may only view
 * reads who asked and when, never the note, the reason of a rejection nor which leave it was (criterio C-22).
 *
 * @phpstan-type Row array{id: string, name: string, manager: string|null, type: string|null, startsOn: string, endsOn: string, workingDays: int, status: string, statusLabel: string, hadBalance: bool|null, shortBy: int, periods: list<int>, reason: string|null, recordedByHr: bool, note: string|null, rejectionReason: string|null, canDecide: bool, canCancel: bool, coverage: array{fits: bool, leftAfter: int}|null, team: array{days: list<array{date: string, away: int}>, names: list<string>}|null}
 */
final readonly class RequestPages
{
    public const int PER_PAGE = 10;

    /** The tabs, the one RH opens on first. */
    public const array TABS = ['attend', 'waiting', 'approved', 'rejected', 'cancelled', 'taken'];

    public function __construct(private Roster $roster, private WorkCenters $centers, private Areas $areas, private DecisionContext $context) {}

    /**
     * @return array{tab: string, counts: array{attend: int, waiting: int, approved: int, rejected: int, cancelled: int, taken: int}, filters: array{q: string, center: array{value: string, label: string}|null, area: array{value: string, label: string}|null}, canManage: bool, types: list<array{id: string, name: string}>, requests: LengthAwarePaginator<int, Row>}
     */
    public function index(string $tab, string $search, ?string $centerId, ?string $areaId, bool $canManage): array
    {
        $tab = in_array($tab, self::TABS, true) ? $tab : self::TABS[0];
        $people = $search === '' && $centerId === null && $areaId === null ? null : $this->roster->matchingIds(new RosterQuery(search: $search, activeInWorkCenter: $centerId, areaId: $areaId));

        return [
            'tab' => $tab,
            'counts' => self::counts($people),
            'filters' => [
                'q' => $search,
                'center' => $centerId === null ? null : ['value' => $centerId, 'label' => $this->centers->findActive($centerId)->name ?? ''],
                'area' => $areaId === null ? null : ['value' => $areaId, 'label' => $this->areas->name($areaId) ?? ''],
            ],
            'canManage' => $canManage,
            'types' => ! $canManage ? [] : array_values(LeaveType::query()->whereNull('archived_at')->orderBy('name')->orderBy('id')->get(['id', 'name'])->map(fn (LeaveType $type): array => ['id' => $type->id, 'name' => $type->name])->all()),
            'requests' => $this->page($tab, $people, $canManage),
        ];
    }

    /**
     * @param  list<string>|null  $people  null for everybody
     * @return Builder<LeaveRequest>
     */
    private static function among(?array $people): Builder
    {
        return LeaveRequest::query()->when($people !== null, fn (Builder $requests) => $requests->among('employee_id', $people ?? []));
    }

    /**
     * @param  list<string>|null  $people
     * @return array{attend: int, waiting: int, approved: int, rejected: int, cancelled: int, taken: int}
     */
    private static function counts(?array $people): array
    {
        $byStatus = self::among($people)->toBase()->select('status')->selectRaw('count(*) as total')->groupBy('status')->orderBy('status')->pluck('total', 'status');
        $of = fn (RequestStatus $status): int => is_int($total = $byStatus->get($status->value)) ? $total : 0;
        $attend = self::among($people)->where('status', RequestStatus::Requested)->whereNotNull('escalated_at')->count();

        return [
            'attend' => $attend,
            'waiting' => $of(RequestStatus::Requested) - $attend,
            'approved' => $of(RequestStatus::Approved),
            'rejected' => $of(RequestStatus::Rejected),
            'cancelled' => $of(RequestStatus::Cancelled),
            'taken' => $of(RequestStatus::Taken),
        ];
    }

    /**
     * @param  list<string>|null  $people
     * @return LengthAwarePaginator<int, Row>
     */
    private function page(string $tab, ?array $people, bool $canManage): LengthAwarePaginator
    {
        $requests = self::among($people);
        $requests = match ($tab) {
            'attend' => $requests->where('status', RequestStatus::Requested)->whereNotNull('escalated_at')->orderBy('starts_on')->orderBy('requested_on'),
            'waiting' => $requests->where('status', RequestStatus::Requested)->whereNull('escalated_at')->orderBy('starts_on')->orderBy('requested_on'),
            default => $requests->where('status', RequestStatus::from($tab))->orderByDesc('starts_on'),
        };
        $page = $requests->orderBy('id')->paginate(self::PER_PAGE)->withQueryString();
        $rows = $this->present(array_values($page->items()), $canManage);

        return $page->through(fn (LeaveRequest $request): array => $rows[$request->id]);
    }

    /**
     * The requests RH has to answer first, those the manager did not answer in time or that have nobody above the person,
     * the one that starts soonest first (the overview shows the first few).
     *
     * @return list<Row>
     */
    public function escalated(int $limit): array
    {
        $requests = LeaveRequest::query()->where('status', RequestStatus::Requested)->whereNotNull('escalated_at')->orderBy('starts_on')->orderBy('requested_on')->orderBy('id')->limit($limit)->get()->values()->all();

        return array_values($this->present(array_values($requests), true, withTeam: false));
    }

    /**
     * @param  list<LeaveRequest>  $items
     * @return array<string, Row> by request id
     */
    private function present(array $items, bool $canManage, bool $withTeam = true): array
    {
        $context = $this->context->for($items, $canManage, $withTeam);
        $names = $this->roster->findMany(array_values(array_unique(array_filter(array_merge(...array_map(fn (LeaveRequest $request): array => [$request->employee_id, $request->approver_employee_id], $items))))));
        $types = LeaveType::query()->whereIn('id', array_filter(array_map(fn (LeaveRequest $request): ?string => $request->leave_type_id, $items)))->get(['id', 'name'])->mapWithKeys(fn (LeaveType $type): array => [$type->id => $type->name]);
        $years = LeaveEntitlement::query()->whereIn('id', array_merge(...array_map(fn (LeaveRequest $request): array => array_column($request->allocation, 'entitlement_id'), $items)))->get(['id', 'anniversary_on'])->mapWithKeys(fn (LeaveEntitlement $period): array => [$period->id => $period->anniversary_on->year]);

        return array_column(array_map(fn (LeaveRequest $request): array => [
            'id' => $request->id,
            'name' => ($names[$request->employee_id] ?? null)->name ?? '',
            'manager' => ($names[$request->approver_employee_id ?? ''] ?? null)->name ?? null,
            'type' => $request->leave_type_id === null ? null : ($canManage ? $types->get($request->leave_type_id, 'Permiso') : 'Permiso'),
            'startsOn' => $request->starts_on->toDateString(),
            'endsOn' => $request->ends_on->toDateString(),
            'workingDays' => $request->working_days,
            'status' => $request->status->value,
            'statusLabel' => $request->status->label(),
            'hadBalance' => $request->had_balance,
            'shortBy' => $request->short_by,
            'periods' => self::periods($request, $years),
            'reason' => $request->status === RequestStatus::Requested ? self::reason($request->escalation_reason) : null,
            'recordedByHr' => $request->recorded_by !== null,
            'note' => $canManage ? $request->note : null,
            'rejectionReason' => $canManage ? $request->rejection_reason : null,
            'canDecide' => $canManage && $request->status === RequestStatus::Requested,
            'canCancel' => $canManage && in_array($request->status, RequestStatus::live(), true),
            'coverage' => $context[$request->id]['coverage'] ?? null,
            'team' => $context[$request->id]['team'] ?? null,
        ], $items), null, 'id');
    }

    /**
     * The years of the periods a request charges its days to, from the oldest.
     *
     * @param  Collection<string, int>  $years  by period id
     * @return list<int>
     */
    private static function periods(LeaveRequest $request, Collection $years): array
    {
        $found = array_map(fn (string $id): int => $years->get($id, 0), array_column($request->allocation, 'entitlement_id'));
        sort($found);

        return array_values(array_unique($found));
    }

    private static function reason(?EscalationReason $reason): ?string
    {
        return match ($reason) {
            EscalationReason::NoManager => 'Sin jefe activo',
            EscalationReason::Timeout => 'Su jefe no respondió en '.config()->integer('leave.escalation_business_days').' días hábiles',
            null => null,
        };
    }
}

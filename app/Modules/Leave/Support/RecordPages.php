<?php

declare(strict_types=1);

namespace App\Modules\Leave\Support;

use App\Modules\Leave\Enums\EntryKind;
use App\Modules\Leave\Enums\WorkWeek;
use App\Modules\Leave\Models\LeaveEntitlement;
use App\Modules\Leave\Models\LeaveEntry;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Models\LeaveSetting;
use App\Modules\Leave\Models\LeaveStatement;
use App\Modules\Leave\Models\LeaveTermination;
use App\Modules\Leave\Models\LeaveType;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Organization\Contracts\Seniority;
use App\Modules\Organization\Data\RosterEntry;
use Carbon\CarbonImmutable;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * The record of one person for RH: their periods with their windows, the ledger of every movement of their days, their
 * requests with who answered, their annual statements with the acknowledgment and, if they left, what they are owed in
 * days. Days and dates only, never an amount. Whoever may only view reads no notes and not which leave it was (criterio
 * C-22).
 *
 * @phpstan-type Period array{id: string, year: int, anniversaryOn: string, enjoyBy: string, prescribesOn: string, granted: int, available: int, mayPrescribe: bool}
 * @phpstan-type Entry array{id: string, kind: string, kindLabel: string, tranche: string, days: int, balance: int, period: int|null, note: string|null, on: string}
 * @phpstan-type Request array{id: string, type: string|null, startsOn: string, endsOn: string, workingDays: int, status: string, statusLabel: string, decidedBy: string|null, decidedOn: string|null, note: string|null}
 * @phpstan-type Termination array{id: string, terminatedOn: string, pendingDays: int, proportionalDays: float, settledOn: string|null, canSettle: bool}
 */
final readonly class RecordPages
{
    public const int PER_PAGE = 10;

    public function __construct(private Roster $roster, private Seniority $seniority, private PolicyResolver $policies) {}

    /**
     * @return array{person: array{id: string, name: string, active: bool, jobTitle: string, area: string, policy: string, startedOn: string|null}, canManage: bool, balance: array{available: int, advanced: int, waiting: int}, types: list<array{id: string, name: string}>, periods: list<Period>, entries: LengthAwarePaginator<int, Entry>, requests: LengthAwarePaginator<int, Request>, statements: list<array{id: string, year: int, issuedOn: string, acknowledgedOn: string|null}>, termination: Termination|null}
     */
    public function show(RosterEntry $person, bool $canManage): array
    {
        $balances = Balances::of([$person->id])[$person->id] ?? [];
        $advanced = -(Balances::advanced([$person->id])[$person->id] ?? 0);
        $years = [];

        foreach ($balances as $balance) {
            $years[$balance->entitlementId] = $balance->anniversaryOn->year;
        }

        $terms = $this->policies->for([$person->id])[$person->id] ?? PolicyTerms::statutory(LeaveSetting::query()->first()->statutory_work_week ?? WorkWeek::MonFri);

        return [
            'person' => [
                'id' => $person->id, 'name' => $person->name, 'active' => $person->active, 'jobTitle' => $person->jobTitle, 'area' => $person->areaName,
                'policy' => $terms->name, 'startedOn' => ($this->seniority->startDatesFor([$person->id])[$person->id] ?? null)?->toDateString(),
            ],
            'canManage' => $canManage,
            'balance' => ['available' => array_sum(array_map(fn (PeriodBalance $period): int => $period->left(), $balances)) - $advanced, 'advanced' => $advanced, 'waiting' => Balances::waiting([$person->id])[$person->id] ?? 0],
            'types' => $canManage ? array_values(LeaveType::query()->whereNull('archived_at')->orderBy('name')->orderBy('id')->get(['id', 'name'])->map(fn (LeaveType $type): array => ['id' => $type->id, 'name' => $type->name])->all()) : [],
            'periods' => self::periods($balances),
            'entries' => $this->entries($person->id, $years, $canManage),
            'requests' => $this->requests($person->id, $canManage),
            'statements' => self::statements($person->id, $years),
            'termination' => self::termination($person->id, $canManage),
        ];
    }

    /**
     * Newest first. One that is past its eighteen months and still has days is marked, never taken off (criterio C-11).
     *
     * @param  list<PeriodBalance>  $balances  from the oldest
     * @return list<Period>
     */
    private static function periods(array $balances): array
    {
        $today = PeriodWindow::today()->toDateString();
        $granted = LeaveEntitlement::query()->whereIn('id', array_map(fn (PeriodBalance $period): string => $period->entitlementId, $balances))->get(['id', 'statutory_days', 'extra_days'])->keyBy('id');

        return array_map(fn (PeriodBalance $period): array => [
            'id' => $period->entitlementId,
            'year' => $period->anniversaryOn->year,
            'anniversaryOn' => $period->anniversaryOn->toDateString(),
            'enjoyBy' => $period->enjoyBy->toDateString(),
            'prescribesOn' => $period->prescribesOn->toDateString(),
            'granted' => ($granted[$period->entitlementId]->statutory_days ?? 0) + ($granted[$period->entitlementId]->extra_days ?? 0),
            'available' => $period->left(),
            'mayPrescribe' => $period->left() > 0 && $today > $period->prescribesOn->toDateString(),
        ], array_reverse($balances));
    }

    /**
     * @param  array<string, int>  $years  the year of each period, by id
     * @return LengthAwarePaginator<int, Entry>
     */
    private function entries(string $employeeId, array $years, bool $canManage): LengthAwarePaginator
    {
        // The balance after each movement is a running sum over the whole ledger of the person, so a page keeps the account of the ones before it.
        return LeaveEntry::query()->where('employee_id', $employeeId)->selectRaw('leave_entries.*, (sum(leave_entries.days) over (order by leave_entries.created_at, leave_entries.id))::int as balance')->orderByDesc('created_at')->orderByDesc('id')
            ->paginate(self::PER_PAGE, pageName: 'entries')->withQueryString()
            ->through(fn (LeaveEntry $entry): array => [
                'id' => $entry->id,
                'kind' => $entry->kind->value,
                'kindLabel' => self::kindLabel($entry->kind),
                'tranche' => $entry->tranche->value,
                'days' => $entry->days,
                'balance' => is_int($balance = $entry->getAttribute('balance')) ? $balance : 0,
                'period' => $years[$entry->leave_entitlement_id ?? ''] ?? null,
                'note' => $canManage ? $entry->note : null,
                'on' => self::day($entry->created_at),
            ]);
    }

    /**
     * @return LengthAwarePaginator<int, Request>
     */
    private function requests(string $employeeId, bool $canManage): LengthAwarePaginator
    {
        $page = LeaveRequest::query()->where('employee_id', $employeeId)->orderByDesc('starts_on')->orderByDesc('id')
            ->paginate(self::PER_PAGE, pageName: 'requests')->withQueryString();
        $items = $page->items();
        $bosses = $this->roster->findMany(array_values(array_unique(array_filter(array_map(fn (LeaveRequest $request): ?string => $request->decided_by_employee_id, $items)))));
        $types = LeaveType::query()->whereIn('id', array_filter(array_map(fn (LeaveRequest $request): ?string => $request->leave_type_id, $items)))->get(['id', 'name'])->mapWithKeys(fn (LeaveType $type): array => [$type->id => $type->name]);

        return $page->through(fn (LeaveRequest $request): array => [
            'id' => $request->id,
            'type' => $request->leave_type_id === null ? null : ($canManage ? $types->get($request->leave_type_id, 'Permiso') : 'Permiso'),
            'startsOn' => $request->starts_on->toDateString(),
            'endsOn' => $request->ends_on->toDateString(),
            'workingDays' => $request->working_days,
            'status' => $request->status->value,
            'statusLabel' => $request->status->label(),
            'decidedBy' => $request->decided_by_employee_id !== null ? ($bosses[$request->decided_by_employee_id] ?? null)->name ?? null : ($request->decided_by_user_id !== null ? 'RH' : null),
            'decidedOn' => $request->decided_at === null ? null : self::day($request->decided_at),
            'note' => $canManage ? $request->note : null,
        ]);
    }

    /**
     * @param  array<string, int>  $years
     * @return list<array{id: string, year: int, issuedOn: string, acknowledgedOn: string|null}>
     */
    private static function statements(string $employeeId, array $years): array
    {
        return array_values(LeaveStatement::query()->where('employee_id', $employeeId)->orderByDesc('issued_at')->orderByDesc('id')->get()->map(fn (LeaveStatement $statement): array => [
            'id' => $statement->id,
            'year' => $years[$statement->leave_entitlement_id] ?? 0,
            'issuedOn' => self::day($statement->issued_at),
            'acknowledgedOn' => $statement->acknowledged_at === null ? null : self::day($statement->acknowledged_at),
        ])->all());
    }

    /**
     * The last one that was not voided: what the person is owed in days, and whether RH already settled it.
     *
     * @return Termination|null
     */
    private static function termination(string $employeeId, bool $canManage): ?array
    {
        $termination = LeaveTermination::query()->where('employee_id', $employeeId)->whereNull('voided_at')->orderByDesc('terminated_on')->orderByDesc('id')->first();

        return $termination === null ? null : [
            'id' => $termination->id,
            'terminatedOn' => $termination->terminated_on->toDateString(),
            'pendingDays' => $termination->pending_days,
            'proportionalDays' => $termination->proportional_days,
            'settledOn' => $termination->settled_at === null ? null : self::day($termination->settled_at),
            'canSettle' => $canManage && $termination->settled_at === null,
        ];
    }

    private static function kindLabel(EntryKind $kind): string
    {
        return match ($kind) {
            EntryKind::Granted => 'Periodo otorgado',
            EntryKind::Opening => 'Saldo inicial',
            EntryKind::Taken => 'Días tomados',
            EntryKind::Adjustment => 'Ajuste de RH',
            EntryKind::Expired => 'Días adicionales vencidos',
            EntryKind::Termination => 'Finiquito de días',
        };
    }

    /** The local day of an instant. */
    private static function day(CarbonImmutable $at): string
    {
        return $at->setTimezone(config()->string('app.display_timezone'))->toDateString();
    }
}

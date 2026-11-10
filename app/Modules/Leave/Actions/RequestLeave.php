<?php

declare(strict_types=1);

namespace App\Modules\Leave\Actions;

use App\Actions\RecordAudit;
use App\Modules\Leave\Enums\EntryKind;
use App\Modules\Leave\Enums\EscalationReason;
use App\Modules\Leave\Enums\RequestStatus;
use App\Modules\Leave\Enums\WorkWeek;
use App\Modules\Leave\Exceptions\RequestRefused;
use App\Modules\Leave\Models\LeaveEntry;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Models\LeaveType;
use App\Modules\Leave\Support\Allocation;
use App\Modules\Leave\Support\AllocationLine;
use App\Modules\Leave\Support\Allocator;
use App\Modules\Leave\Support\Approver;
use App\Modules\Leave\Support\Balances;
use App\Modules\Leave\Support\ContinuousRun;
use App\Modules\Leave\Support\EmployeeLock;
use App\Modules\Leave\Support\Holidays;
use App\Modules\Leave\Support\PeriodBalance;
use App\Modules\Leave\Support\PeriodWindow;
use App\Modules\Leave\Support\PolicyResolver;
use App\Modules\Leave\Support\PolicyTerms;
use App\Modules\Leave\Support\RequestNotifier;
use App\Modules\Leave\Support\WorkingDays;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * A person asks for vacations (no type) or for a leave that uses no balance. The working days are counted now, with the
 * week of their policy and the holidays of today (criterio C-04), and never again. For vacations it keeps whether they had
 * the days and where they would come from: it can be sent without them (criterio C-17) and the ledger is not touched until
 * someone approves. It is the first active manager's above them to answer; with none, it is born with RH (criterio C-18).
 * What RH records for a person emails nobody: RH answers it on the same screen. The note and the type never reach the logs.
 */
final readonly class RequestLeave
{
    /** The longest a request may be, in calendar days. */
    private const int LONGEST = 366;

    public function __construct(private CurrentTenant $tenant, private Roster $roster, private PolicyResolver $policies, private Approver $approver, private RequestNotifier $notifier, private RecordAudit $audit) {}

    /**
     * @param  int|null  $recordedBy  the user of RH who records it for the person; RH may record days already gone
     *
     * @throws RequestRefused inactive_employee, type_unavailable, in_the_past, too_long, no_working_days or overlaps
     */
    public function handle(string $employeeId, ?string $leaveTypeId, CarbonImmutable $startsOn, CarbonImmutable $endsOn, ?string $note = null, ?int $recordedBy = null): LeaveRequest
    {
        $today = PeriodWindow::today()->toDateString();
        $note = trim($note ?? '');

        if ($recordedBy === null && $startsOn->toDateString() < $today) {
            throw new RequestRefused('in_the_past');
        }

        $request = DB::transaction(function () use ($employeeId, $leaveTypeId, $startsOn, $endsOn, $note, $recordedBy, $today): LeaveRequest {
            // Held until the end: two requests of the same person never both pass the check of overlaps.
            EmployeeLock::of($employeeId);
            $plan = $this->plan($employeeId, $leaveTypeId, $startsOn, $endsOn);
            $approver = $this->approver->for($employeeId);

            return LeaveRequest::query()->create([
                'tenant_id' => $this->tenant->id(),
                'employee_id' => $employeeId,
                'leave_type_id' => $leaveTypeId,
                'starts_on' => $startsOn->toDateString(),
                'ends_on' => $endsOn->toDateString(),
                'working_days' => $plan['workingDays'],
                'status' => RequestStatus::Requested,
                'note' => $note === '' ? null : $note,
                'had_balance' => $plan['hadBalance'],
                'allocation' => array_map(fn (AllocationLine $line): array => ['entitlement_id' => $line->entitlementId, 'tranche' => $line->tranche->value, 'days' => $line->days], $plan['allocation']),
                'short_by' => $plan['shortBy'],
                'approver_employee_id' => $approver,
                'escalated_at' => $approver === null ? now() : null,
                'escalation_reason' => $approver === null ? EscalationReason::NoManager : null,
                'recorded_by' => $recordedBy,
                'requested_on' => $today,
            ]);
        });

        Log::info('leave.request_created', ['request_id' => $request->id, 'employee_id' => $employeeId, 'working_days' => $request->working_days, 'had_balance' => $request->had_balance]);

        if ($request->escalation_reason !== null) {
            Log::info('leave.request_escalated', ['request_id' => $request->id, 'reason' => $request->escalation_reason->value]);
        }

        if ($recordedBy !== null) {
            // RH wrote it for the person: who did it stays on record. The note and the type stay out of it.
            $this->audit->handle('leave.request_recorded_by_hr', $request, ['employee_id' => $employeeId, 'working_days' => $request->working_days]);
        }

        if ($recordedBy === null) {
            $request->approver_employee_id === null ? $this->notifier->escalated($request) : $this->notifier->toDecide($request);
        }

        return $request;
    }

    /**
     * What the request would be, without writing it, and whether to remind the person of the twelve days in a row of
     * LFT art. 78 (criterio C-06): a reminder, never a refusal.
     *
     * @return array{workingDays: int, hadBalance: bool|null, allocation: list<AllocationLine>, shortBy: int, continuousNotice: bool}
     *
     * @throws RequestRefused
     */
    public function preview(string $employeeId, ?string $leaveTypeId, CarbonImmutable $startsOn, CarbonImmutable $endsOn): array
    {
        $plan = $this->plan($employeeId, $leaveTypeId, $startsOn, $endsOn);

        return [
            'workingDays' => $plan['workingDays'],
            'hadBalance' => $plan['hadBalance'],
            'allocation' => $plan['allocation'],
            'shortBy' => $plan['shortBy'],
            'continuousNotice' => $leaveTypeId === null && self::missesContinuousRun($plan['periods'], $plan['workingDays']),
        ];
    }

    /**
     * @return array{workingDays: int, hadBalance: bool|null, allocation: list<AllocationLine>, shortBy: int, periods: list<PeriodBalance>}
     */
    private function plan(string $employeeId, ?string $leaveTypeId, CarbonImmutable $startsOn, CarbonImmutable $endsOn): array
    {
        [$first, $last] = [$startsOn->toDateString(), $endsOn->toDateString()];

        if (! ($this->roster->find($employeeId)->active ?? false)) {
            throw new RequestRefused('inactive_employee');
        }

        if ($leaveTypeId !== null && ! LeaveType::query()->whereKey($leaveTypeId)->whereNull('archived_at')->exists()) {
            throw new RequestRefused('type_unavailable');
        }

        if ($last < $first) {
            throw new RequestRefused('no_working_days');
        }

        if ($startsOn->startOfDay()->diffInDays($endsOn->startOfDay()) + 1 > self::LONGEST) {
            throw new RequestRefused('too_long');
        }

        $terms = $this->policies->for([$employeeId])[$employeeId] ?? PolicyTerms::statutory(WorkWeek::MonFri);
        $workingDays = WorkingDays::count($startsOn, $endsOn, $terms->workWeek, Holidays::between($startsOn, $endsOn));

        if ($workingDays === 0) {
            throw new RequestRefused('no_working_days');
        }

        if (LeaveRequest::query()->where('employee_id', $employeeId)->whereIn('status', RequestStatus::live())->where('starts_on', '<=', $last)->where('ends_on', '>=', $first)->exists()) {
            throw new RequestRefused('overlaps');
        }

        if ($leaveTypeId !== null) {
            return ['workingDays' => $workingDays, 'hadBalance' => null, 'allocation' => [], 'shortBy' => 0, 'periods' => []];
        }

        $periods = Balances::of([$employeeId])[$employeeId] ?? [];
        $allocation = $periods === [] ? new Allocation([], $workingDays) : Allocator::allocate($periods, $workingDays);
        // What their other vacations still waiting would use is not theirs to ask twice.
        $waiting = (int) LeaveRequest::query()->where('employee_id', $employeeId)->where('status', RequestStatus::Requested)->whereNull('leave_type_id')->sum('working_days');

        return [
            'workingDays' => $workingDays,
            'hadBalance' => Balances::available($employeeId) - $waiting >= $workingDays,
            'allocation' => $allocation->lines,
            'shortBy' => $allocation->shortBy,
            'periods' => $periods,
        ];
    }

    /**
     * Whether, counting this request, no approved or enjoyed request charged to the oldest period that still has days
     * reaches the twelve working days in a row. With no days left anywhere there is nothing to remind.
     *
     * @param  list<PeriodBalance>  $periods  from the oldest
     */
    private static function missesContinuousRun(array $periods, int $workingDays): bool
    {
        foreach ($periods as $period) {
            if ($period->left() > 0) {
                $charged = LeaveEntry::query()->where('leave_entitlement_id', $period->entitlementId)->where('kind', EntryKind::Taken)->whereNotNull('leave_request_id')->select('leave_request_id');

                return ContinuousRun::missing([
                    $workingDays,
                    ...array_values(LeaveRequest::query()->whereIn('id', $charged)->whereIn('status', [RequestStatus::Approved, RequestStatus::Taken])->pluck('working_days')->map(fn (mixed $days): int => is_int($days) ? $days : 0)->all()),
                ]);
            }
        }

        return false;
    }
}

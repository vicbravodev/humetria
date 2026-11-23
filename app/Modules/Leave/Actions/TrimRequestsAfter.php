<?php

declare(strict_types=1);

namespace App\Modules\Leave\Actions;

use App\Actions\RecordAudit;
use App\Modules\Leave\Enums\EntryKind;
use App\Modules\Leave\Enums\RequestStatus;
use App\Modules\Leave\Enums\Tranche;
use App\Modules\Leave\Enums\WorkWeek;
use App\Modules\Leave\Events\AbsenceChanged;
use App\Modules\Leave\Models\LeaveEntry;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Support\Balances;
use App\Modules\Leave\Support\Decider;
use App\Modules\Leave\Support\EmployeeLock;
use App\Modules\Leave\Support\Holidays;
use App\Modules\Leave\Support\PolicyResolver;
use App\Modules\Leave\Support\PolicyTerms;
use App\Modules\Leave\Support\WorkingDays;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The vacations of a person that began on or before the day they left and go past it (criterio C-07): they keep what was
 * worked until that day, and the working days after it go back to the periods they were charged to (newest period first,
 * extra days before statutory), as adjustments of the ledger. The request ends on the day they left. One with no working
 * day up to then is cancelled whole. It runs inside the transaction that deactivates them.
 */
final readonly class TrimRequestsAfter
{
    public function __construct(private CancelRequest $cancel, private PolicyResolver $policies, private RecordAudit $audit) {}

    public function handle(string $employeeId, CarbonImmutable $effectiveOn): void
    {
        $cutoff = $effectiveOn->toDateString();
        $week = ($this->policies->for([$employeeId])[$employeeId] ?? PolicyTerms::statutory(WorkWeek::MonFri))->workWeek;

        $crossing = LeaveRequest::query()->where('employee_id', $employeeId)->whereIn('status', [RequestStatus::Approved, RequestStatus::Taken])
            ->where('starts_on', '<=', $cutoff)->where('ends_on', '>', $cutoff)->orderBy('starts_on')->pluck('id');

        foreach ($crossing as $requestId) {
            if (is_string($requestId)) {
                $this->trim($requestId, $effectiveOn, $week);
            }
        }
    }

    private function trim(string $requestId, CarbonImmutable $effectiveOn, WorkWeek $week): void
    {
        $returned = 0;

        $request = DB::transaction(function () use ($requestId, $effectiveOn, $week, &$returned): LeaveRequest {
            $employeeId = LeaveRequest::query()->findOrFail($requestId, ['id', 'employee_id'])->employee_id;
            EmployeeLock::of($employeeId);
            $request = LeaveRequest::query()->lockForUpdate()->findOrFail($requestId);
            $kept = $this->workingDays($request->starts_on, $effectiveOn, $week);

            if ($kept === 0) {
                $this->cancel->handle($request->id, Decider::system());

                return $request;
            }

            $returned = $request->working_days - $kept;
            $this->giveBack($request, $returned);
            $endedOn = $request->ends_on->toDateString();
            $request->update(['ends_on' => $effectiveOn->toDateString(), 'working_days' => $kept]);
            // The days after the departure are no longer an absence.
            event(new AbsenceChanged($request->id, $request->employee_id, $effectiveOn->addDay()->toDateString(), $endedOn));
            $this->audit->handle('leave.request_trimmed', $request, ['employee_id' => $request->employee_id, 'was' => $request->status->value, 'days_returned' => $returned]);

            return $request;
        });

        // Said once it committed: this runs inside the transaction of the departure, which may still roll back.
        DB::afterCommit(fn () => Log::info('leave.request_trimmed', ['request_id' => $request->id, 'employee_id' => $request->employee_id, 'days_returned' => $returned]));
    }

    private function workingDays(CarbonImmutable $from, CarbonImmutable $to, WorkWeek $week): int
    {
        return WorkingDays::count($from, $to, $week, Holidays::between($from, $to));
    }

    /** Takes the days off the charges of the request, the newest period first and the extra tranche before the statutory one. */
    private function giveBack(LeaveRequest $request, int $days): void
    {
        $charges = LeaveEntry::query()->where('leave_request_id', $request->id)->where('kind', EntryKind::Taken)->get();
        $age = [];

        foreach (Balances::of([$request->employee_id])[$request->employee_id] ?? [] as $period) {
            $age[$period->entitlementId] = $period->anniversaryOn->getTimestamp();
        }

        $ordered = $charges->sortBy([
            fn (LeaveEntry $left, LeaveEntry $right): int => ($age[$right->leave_entitlement_id ?? ''] ?? 0) <=> ($age[$left->leave_entitlement_id ?? ''] ?? 0),
            fn (LeaveEntry $left, LeaveEntry $right): int => ($left->tranche === Tranche::Extra ? 0 : 1) <=> ($right->tranche === Tranche::Extra ? 0 : 1),
        ]);

        foreach ($ordered as $charge) {
            $back = min($days, -$charge->days);

            if ($back > 0) {
                LeaveEntry::query()->create([
                    'tenant_id' => $charge->tenant_id, 'employee_id' => $charge->employee_id, 'leave_entitlement_id' => $charge->leave_entitlement_id, 'kind' => EntryKind::Adjustment,
                    'tranche' => $charge->tranche, 'days' => $back, 'leave_request_id' => $request->id,
                ]);
                $days -= $back;
            }
        }
    }
}

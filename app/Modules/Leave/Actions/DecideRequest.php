<?php

declare(strict_types=1);

namespace App\Modules\Leave\Actions;

use App\Actions\RecordAudit;
use App\Modules\Leave\Enums\EntryKind;
use App\Modules\Leave\Enums\RequestStatus;
use App\Modules\Leave\Enums\Tranche;
use App\Modules\Leave\Exceptions\AlreadyDecided;
use App\Modules\Leave\Exceptions\RequestRefused;
use App\Modules\Leave\Models\LeaveEntry;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Support\Allocation;
use App\Modules\Leave\Support\AllocationLine;
use App\Modules\Leave\Support\Allocator;
use App\Modules\Leave\Support\Balances;
use App\Modules\Leave\Support\Decider;
use App\Modules\Leave\Support\EmployeeLock;
use App\Modules\Leave\Support\PolicyResolver;
use App\Modules\Leave\Support\RequestNotifier;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Approves or rejects a request, once (criterio C-18): the manager whose it is to answer, or RH at any time, which is
 * audited. Approving vacations charges the ledger with the balance of that moment, the oldest period first (criterio
 * C-16). Days the person does not have are only given in advance when their policy allows it, as an entry with no period
 * and with who authorized them (criterio C-17). A leave uses no balance. The reason of a rejection never reaches the logs
 * nor the audit trail.
 */
final readonly class DecideRequest
{
    public function __construct(private PolicyResolver $policies, private RecordAudit $audit, private RequestNotifier $notifier) {}

    /**
     * @throws AlreadyDecided when it was answered or cancelled first; nothing changes
     * @throws ModelNotFoundException<LeaveRequest> when it is not theirs to answer
     * @throws RequestRefused insufficient_balance: the request keeps waiting
     * @throws ValidationException a rejection without its reason
     */
    public function handle(string $requestId, bool $approve, Decider $by, ?string $reason = null): LeaveRequest
    {
        $reason = trim($reason ?? '');

        if (! $approve && (mb_strlen($reason) < 5 || mb_strlen($reason) > 500)) {
            throw ValidationException::withMessages(['reason' => 'Explique el motivo del rechazo, entre 5 y 500 caracteres.']);
        }

        [$request, $advanced] = DB::transaction(function () use ($requestId, $approve, $by, $reason): array {
            $employeeId = LeaveRequest::query()->when(! $by->isHr, fn ($query) => $query->where('approver_employee_id', $by->employeeId))->findOrFail($requestId, ['id', 'employee_id'])->employee_id;
            // The lock of the person, then the row: of two answers at once the second finds it answered.
            EmployeeLock::of($employeeId);
            $request = LeaveRequest::query()->lockForUpdate()->findOrFail($requestId);

            if ($request->status !== RequestStatus::Requested) {
                throw new AlreadyDecided;
            }

            $advanced = $approve && $request->leave_type_id === null ? $this->charge($request, $by) : 0;
            $request->update([
                'status' => $approve ? RequestStatus::Approved : RequestStatus::Rejected,
                'decided_by_employee_id' => $by->employeeId,
                'decided_by_user_id' => $by->userId,
                'decided_at' => now(),
                'rejection_reason' => $approve ? null : $reason,
            ]);

            if ($by->isHr) {
                $this->audit->handle('leave.request_decided_by_hr', $request, ['employee_id' => $request->employee_id, 'approved' => $approve, 'working_days' => $request->working_days]);
            }

            return [$request, $advanced];
        });

        $context = ['request_id' => $request->id, 'employee_id' => $request->employee_id, 'working_days' => $request->working_days, 'by_hr' => $by->isHr];
        $approve ? Log::info('leave.request_approved', [...$context, 'advanced' => $advanced]) : Log::info('leave.request_rejected', $context);
        $this->notifier->decided($request);

        return $request;
    }

    /**
     * Writes the charges of the vacations to the ledger and keeps on the request where the days came from.
     *
     * @return int the days given in advance
     */
    private function charge(LeaveRequest $request, Decider $by): int
    {
        $periods = Balances::of([$request->employee_id])[$request->employee_id] ?? [];
        $allocation = $periods === [] ? new Allocation([], $request->working_days) : Allocator::allocate($periods, $request->working_days);

        if (! $allocation->covered() && ! ($this->policies->for([$request->employee_id])[$request->employee_id]->allowsAdvance ?? false)) {
            throw new RequestRefused('insufficient_balance');
        }

        $entry = ['tenant_id' => $request->tenant_id, 'employee_id' => $request->employee_id, 'kind' => EntryKind::Taken, 'leave_request_id' => $request->id];

        foreach ($allocation->lines as $line) {
            LeaveEntry::query()->create([...$entry, 'leave_entitlement_id' => $line->entitlementId, 'tranche' => $line->tranche, 'days' => -$line->days]);
        }

        if (! $allocation->covered()) {
            // No period: days the person does not have yet. A manager has no account: who authorized is the one who decided the request.
            LeaveEntry::query()->create([...$entry, 'leave_entitlement_id' => null, 'tranche' => Tranche::Statutory, 'days' => -$allocation->shortBy, 'authorized_by' => $by->userId]);
            $this->audit->handle('leave.advance_authorized', $request, ['employee_id' => $request->employee_id, 'days' => $allocation->shortBy, 'authorized_by_employee_id' => $by->employeeId, 'authorized_by_user_id' => $by->userId]);
        }

        $request->fill([
            'allocation' => array_map(fn (AllocationLine $line): array => ['entitlement_id' => $line->entitlementId, 'tranche' => $line->tranche->value, 'days' => $line->days], $allocation->lines),
            'short_by' => $allocation->shortBy,
        ]);

        return $allocation->shortBy;
    }
}

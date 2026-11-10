<?php

declare(strict_types=1);

namespace App\Modules\Leave\Actions;

use App\Actions\RecordAudit;
use App\Modules\Leave\Enums\EntryKind;
use App\Modules\Leave\Enums\RequestStatus;
use App\Modules\Leave\Exceptions\AlreadyDecided;
use App\Modules\Leave\Models\LeaveEntry;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Support\Decider;
use App\Modules\Leave\Support\EmployeeLock;
use App\Modules\Leave\Support\PeriodWindow;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Cancels a request. The person cancels their own while it waits, or once approved until the day before it starts; RH
 * cancels any that holds its dates, enjoyed ones too: that is how RH corrects (criterios C-05 and C-18), and it is audited.
 * The days it had charged go back to the same periods and tranches as new entries of the ledger: nothing is erased.
 */
final readonly class CancelRequest
{
    public function __construct(private RecordAudit $audit) {}

    /**
     * @throws AlreadyDecided when it is no longer theirs to cancel; nothing changes
     * @throws ModelNotFoundException<LeaveRequest> when it is not theirs
     */
    public function handle(string $requestId, Decider $by): LeaveRequest
    {
        [$request, $returned] = DB::transaction(function () use ($requestId, $by): array {
            $employeeId = LeaveRequest::query()->when(! $by->isHr, fn ($query) => $query->where('employee_id', $by->employeeId))->findOrFail($requestId, ['id', 'employee_id'])->employee_id;
            // The lock of the person, then the row: every change of a request takes them in that order.
            EmployeeLock::of($employeeId);
            $request = LeaveRequest::query()->lockForUpdate()->findOrFail($requestId);
            $was = $request->status;

            $theirs = $by->isHr
                ? in_array($was, RequestStatus::live(), true)
                : $was === RequestStatus::Requested || ($was === RequestStatus::Approved && $request->starts_on->toDateString() > PeriodWindow::today()->toDateString());

            if (! $theirs) {
                throw new AlreadyDecided;
            }

            $returned = 0;

            foreach (LeaveEntry::query()->where('leave_request_id', $request->id)->where('kind', EntryKind::Taken)->get() as $charge) {
                LeaveEntry::query()->create([
                    'tenant_id' => $charge->tenant_id, 'employee_id' => $charge->employee_id, 'leave_entitlement_id' => $charge->leave_entitlement_id, 'kind' => EntryKind::Adjustment,
                    'tranche' => $charge->tranche, 'days' => -$charge->days, 'leave_request_id' => $request->id, 'recorded_by' => $by->userId,
                ]);
                $returned -= $charge->days;
            }

            $request->update(['status' => RequestStatus::Cancelled, 'cancelled_at' => now()]);

            if ($by->isHr) {
                $this->audit->handle('leave.request_cancelled', $request, ['employee_id' => $request->employee_id, 'was' => $was->value, 'days_returned' => $returned]);
            }

            return [$request, $returned];
        });

        Log::info('leave.request_cancelled', ['request_id' => $request->id, 'employee_id' => $request->employee_id, 'by_hr' => $by->isHr, 'days_returned' => $returned]);

        return $request;
    }
}

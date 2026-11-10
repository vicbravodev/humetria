<?php

declare(strict_types=1);

namespace App\Modules\Leave\Actions;

use App\Modules\Leave\Enums\EscalationReason;
use App\Modules\Leave\Enums\RequestStatus;
use App\Modules\Leave\Enums\WorkWeek;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Models\LeaveSetting;
use App\Modules\Leave\Support\Approver;
use App\Modules\Leave\Support\Holidays;
use App\Modules\Leave\Support\PeriodWindow;
use App\Modules\Leave\Support\RequestNotifier;
use App\Modules\Leave\Support\WorkingDays;
use App\Modules\Tenancy\Contracts\Companies;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The daily routine of the requests, for every company that uses Vacaciones: the approved ones that ended become taken;
 * the ones waiting follow the person's line of managers as it is today; and the ones a manager did not answer in the
 * business days the company gives reach RH (criterio C-18). The manager keeps the request after it reaches RH: whoever
 * answers first, answers. Running it again changes nothing and emails nobody twice.
 */
final readonly class RouteRequests
{
    public function __construct(private Companies $companies, private Approver $approver, private RequestNotifier $notifier) {}

    /**
     * @return array{taken: int, reassigned: int, escalated: int}
     */
    public function handle(): array
    {
        $done = ['taken' => 0, 'reassigned' => 0, 'escalated' => 0];

        foreach (LeaveSetting::query()->withoutGlobalScopes()->orderBy('tenant_id')->get(['tenant_id']) as $company) {
            try {
                // Each in its own company, as the system.
                Context::scope(function () use ($company, &$done): void {
                    if (! $this->companies->enter($company->tenant_id)->blocked) {
                        $done['taken'] += $this->markTaken();
                        $moved = $this->reroute();
                        $done['reassigned'] += $moved['reassigned'];
                        $done['escalated'] += $moved['escalated'] + $this->escalateOverdue();
                    }
                }, ['user_id' => null]);
            } catch (Throwable $failure) {
                // One company's failure never keeps the others' requests from moving.
                report($failure);
            }
        }

        Log::info('leave.requests_routed', $done);

        return $done;
    }

    /**
     * Gives each request waiting in the current company to whoever answers for the person today: another manager, or RH
     * when nobody above is active.
     *
     * @param  string|null  $waitingFor  only the ones that wait for this manager
     * @return array{reassigned: int, escalated: int}
     */
    public function reroute(?string $waitingFor = null): array
    {
        $done = ['reassigned' => 0, 'escalated' => 0];
        $approvers = [];

        foreach ($this->waiting($waitingFor) as $request) {
            // ponytail: one chain per person with a request waiting; a batch contract if a company has thousands waiting.
            $approver = ($approvers[$request->employee_id] ??= [$this->approver->for($request->employee_id)])[0];

            if ($approver === $request->approver_employee_id) {
                continue;
            }

            if ($approver === null) {
                $done['escalated'] += $this->escalate($request, EscalationReason::NoManager, ['approver_employee_id' => null]) ? 1 : 0;
            } elseif (self::change($request, ['approver_employee_id' => $approver])) {
                Log::info('leave.request_reassigned', ['request_id' => $request->id, 'approver_employee_id' => $approver]);
                $this->notifier->toDecide($request);
                $done['reassigned']++;
            }
        }

        return $done;
    }

    private function markTaken(): int
    {
        $taken = 0;

        foreach (LeaveRequest::query()->where('status', RequestStatus::Approved)->where('ends_on', '<', PeriodWindow::today()->toDateString())->get(['id', 'employee_id']) as $request) {
            // Only while it is still approved: RH may be cancelling it right now.
            if (LeaveRequest::query()->whereKey($request->id)->where('status', RequestStatus::Approved)->update(['status' => RequestStatus::Taken]) === 1) {
                Log::info('leave.request_taken', ['request_id' => $request->id, 'employee_id' => $request->employee_id]);
                $taken++;
            }
        }

        return $taken;
    }

    private function escalateOverdue(): int
    {
        $waiting = $this->waiting();
        $oldest = $waiting->first();

        if ($oldest === null) {
            return 0;
        }

        $today = PeriodWindow::today();
        $holidays = Holidays::between($oldest->requested_on, $today);
        $days = config()->integer('leave.escalation_business_days');
        $escalated = 0;

        foreach ($waiting as $request) {
            if (WorkingDays::add($request->requested_on, $days, WorkWeek::MonFri, $holidays)->toDateString() < $today->toDateString()) {
                $escalated += $this->escalate($request, EscalationReason::Timeout) ? 1 : 0;
            }
        }

        return $escalated;
    }

    /**
     * @param  array<string, string|null>  $changes
     */
    private function escalate(LeaveRequest $request, EscalationReason $reason, array $changes = []): bool
    {
        if (! self::change($request, [...$changes, 'escalated_at' => now(), 'escalation_reason' => $reason])) {
            return false;
        }

        Log::info('leave.request_escalated', ['request_id' => $request->id, 'reason' => $reason->value]);
        $this->notifier->escalated($request);

        return true;
    }

    /**
     * The requests of the current company that wait for a manager and have not reached RH, the oldest first.
     *
     * @return Collection<int, LeaveRequest>
     */
    private function waiting(?string $waitingFor = null): Collection
    {
        return LeaveRequest::query()->where('status', RequestStatus::Requested)->whereNull('escalated_at')
            ->when($waitingFor !== null, fn ($query) => $query->where('approver_employee_id', $waitingFor))
            ->orderBy('requested_on')->orderBy('id')->get();
    }

    /**
     * Only while it still waits: someone may be answering it right now.
     *
     * @param  array<string, mixed>  $changes
     */
    private static function change(LeaveRequest $request, array $changes): bool
    {
        if (LeaveRequest::query()->whereKey($request->id)->where('status', RequestStatus::Requested)->whereNull('escalated_at')->update($changes) !== 1) {
            return false;
        }

        $request->fill($changes)->syncOriginal();

        return true;
    }
}

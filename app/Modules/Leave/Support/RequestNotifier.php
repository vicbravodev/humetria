<?php

declare(strict_types=1);

namespace App\Modules\Leave\Support;

use App\Modules\Leave\Jobs\RequestCancelled;
use App\Modules\Leave\Jobs\RequestDecided;
use App\Modules\Leave\Jobs\RequestEscalated;
use App\Modules\Leave\Jobs\RequestToDecide;
use App\Modules\Leave\Models\LeaveRequest;

/**
 * The emails of a request of the current company, by mail only. Each one is a job that waits for the request to be saved
 * for good, is tried again if the mail server fails, and reads the request when it runs: one that changed in between tells
 * nobody what is no longer true. Whoever has no email gets none. The addresses of the person's pages carry no credential:
 * they enter first.
 */
final readonly class RequestNotifier
{
    /**
     * Tells the manager whose it is to answer, with the address of their team's requests in their pages.
     */
    public function toDecide(LeaveRequest $request): void
    {
        if ($request->approver_employee_id !== null) {
            RequestToDecide::dispatch($request->tenant_id, $request->id, $request->approver_employee_id);
        }
    }

    /**
     * Tells the owner a request reached RH: the same recipient as the digests of the other tools.
     */
    public function escalated(LeaveRequest $request): void
    {
        RequestEscalated::dispatch($request->tenant_id, $request->id);
    }

    /**
     * Tells who asked what was answered, with the address of their vacations in their pages.
     */
    public function decided(LeaveRequest $request): void
    {
        RequestDecided::dispatch($request->tenant_id, $request->id);
    }

    /**
     * Tells the manager that the person cancelled what had been approved. A request that still waited, or that RH cancelled,
     * tells nobody: nothing was answered, or whoever did it knows.
     */
    public function cancelled(LeaveRequest $request, bool $byHr): void
    {
        if (! $byHr && $request->decided_at !== null) {
            RequestCancelled::dispatch($request->tenant_id, $request->id);
        }
    }
}

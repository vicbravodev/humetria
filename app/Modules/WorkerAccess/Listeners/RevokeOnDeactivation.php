<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Listeners;

use App\Actions\RecordAudit;
use App\Modules\Organization\Events\EmployeeDeactivated;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\WorkerAccess\Events\WorkerAccessRevoked;
use App\Modules\WorkerAccess\Models\WorkerAccessLink;
use App\Modules\WorkerAccess\Models\WorkerAccessState;
use App\Modules\WorkerAccess\Models\WorkerCredential;
use App\Modules\WorkerAccess\Models\WorkerDeviceKey;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Someone who left the company has no access: every key of theirs stops (reason `left_company`; one revoked before keeps its
 * reason and date), every link that still opened is revoked and every session ends (valid_after moves, even for someone with no key or link), as ReissueAccess does,
 * with no new link. It runs inside the transaction that deactivates them. Coming back, they get a new link and a new key.
 */
final readonly class RevokeOnDeactivation
{
    public function __construct(private Companies $companies, private RecordAudit $audit) {}

    public function handle(EmployeeDeactivated $event): void
    {
        $this->companies->enter($event->tenantId);
        $revoked = WorkerCredential::query()->where('employee_id', $event->employeeId)->whereNull('revoked_at')
            ->update(['revoked_at' => CarbonImmutable::now(), 'revoked_reason' => 'left_company']);
        // The device keys of their phone (plan B of the offline marks) stop signing new marks too.
        $deviceKeys = WorkerDeviceKey::revokeAllOf($event->employeeId, 'left_company');
        $links = WorkerAccessLink::query()->where('employee_id', $event->employeeId)->whereNull('used_at')->whereNull('revoked_at')->exists();
        WorkerAccessLink::revokePending($event->employeeId);

        // Their sessions end whatever else they had: one opened with a link already used would come back with them.
        WorkerAccessState::moveValidAfter($event->tenantId, $event->employeeId);
        // Said once the departure committed, with or without keys: the sessions ended either way (Notifications forgets their push subscriptions).
        DB::afterCommit(fn () => event(new WorkerAccessRevoked($event->employeeId, 'left_company')));

        // Nothing of theirs to revoke: nothing to say or log.
        if ($revoked === 0 && $deviceKeys === 0 && ! $links) {
            return;
        }

        // How many device keys stopped is said only when there were some.
        $counts = ['employee_id' => $event->employeeId, 'keys_revoked' => $revoked, ...($deviceKeys > 0 ? ['device_keys_revoked' => $deviceKeys] : [])];
        $this->audit->handle('worker_access.access_revoked_on_leaving', null, $counts);
        // Said once it committed: this runs inside the transaction of the departure, which may still roll back.
        DB::afterCommit(fn () => Log::info('worker_access.access_revoked_on_leaving', $counts));
    }
}

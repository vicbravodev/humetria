<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Actions;

use App\Actions\RecordAudit;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\WorkerAccess\Data\InvitationOutcome;
use App\Modules\WorkerAccess\Events\WorkerAccessRevoked;
use App\Modules\WorkerAccess\Models\WorkerAccessState;
use App\Modules\WorkerAccess\Models\WorkerCredential;
use App\Modules\WorkerAccess\Models\WorkerDeviceKey;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * CUJ 7, «Teléfono perdido»: RH reissues a person's access. In one transaction every key of theirs in use stops (reason
 * `reissued`; one revoked before keeps its reason and date), every session ends (valid_after moves), and a new link RH
 * issued replaces every link of theirs that still opened (IssueAccessLink: emailed, or returned once for a person without
 * email). The device keys of the phone (plan B of the offline marks) stop too, with the same reason. Anything that fails undoes all of it.
 */
final readonly class ReissueAccess
{
    public function __construct(private Roster $roster, private Companies $companies, private IssueAccessLink $issue, private RecordAudit $audit) {}

    public function handle(string $employeeId, int $issuedBy): InvitationOutcome
    {
        $person = $this->roster->find($employeeId);

        if ($person === null || ! $person->active) {
            return new InvitationOutcome(InvitationOutcome::UNAVAILABLE);
        }

        [$counts, $outcome] = DB::transaction(function () use ($person, $issuedBy): array {
            $revoked = WorkerCredential::query()->where('employee_id', $person->id)->whereNull('revoked_at')
                ->update(['revoked_at' => CarbonImmutable::now(), 'revoked_reason' => 'reissued']);
            $deviceKeys = WorkerDeviceKey::revokeAllOf($person->id, 'reissued');
            WorkerAccessState::moveValidAfter($this->companies->current()->id, $person->id);
            // How many device keys stopped is said only when there were some.
            $counts = ['employee_id' => $person->id, 'keys_revoked' => $revoked, ...($deviceKeys > 0 ? ['device_keys_revoked' => $deviceKeys] : [])];
            $this->audit->handle('worker_access.access_reissued', null, $counts);

            return [$counts, $this->issue->handle($person->id, $issuedBy)];
        });

        Log::info('worker_access.access_reissued', $counts);
        // Even with no key to revoke, their sessions ended.
        event(new WorkerAccessRevoked($person->id, 'reissued'));

        return $outcome;
    }
}

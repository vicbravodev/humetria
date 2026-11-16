<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Actions;

use App\Actions\RecordAudit;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\WorkerAccess\Data\InvitationOutcome;
use App\Modules\WorkerAccess\Models\WorkerAccessState;
use App\Modules\WorkerAccess\Models\WorkerCredential;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * CUJ 7, «Teléfono perdido»: RH reissues a person's access. In one transaction every key of theirs in use stops (reason
 * `reissued`; one revoked before keeps its reason and date), every session ends (valid_after moves), and a new link RH
 * issued replaces every link of theirs that still opened (IssueAccessLink: emailed, or returned once for a person without
 * email). Anything that fails undoes all of it.
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

        [$revoked, $outcome] = DB::transaction(function () use ($person, $issuedBy): array {
            $revoked = WorkerCredential::query()->where('employee_id', $person->id)->whereNull('revoked_at')
                ->update(['revoked_at' => CarbonImmutable::now(), 'revoked_reason' => 'reissued']);
            WorkerAccessState::moveValidAfter($this->companies->current()->id, $person->id);
            $this->audit->handle('worker_access.access_reissued', null, ['employee_id' => $person->id, 'keys_revoked' => $revoked]);

            return [$revoked, $this->issue->handle($person->id, $issuedBy)];
        });

        Log::info('worker_access.access_reissued', ['employee_id' => $person->id, 'keys_revoked' => $revoked]);

        return $outcome;
    }
}

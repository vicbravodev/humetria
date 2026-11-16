<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Actions;

use App\Actions\RecordAudit;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Organization\Data\RosterEntry;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use App\Modules\Tenancy\Contracts\WorkCenters;
use App\Modules\WorkerAccess\Contracts\WorkerCredentials;
use App\Modules\WorkerAccess\Jobs\SendWorkerInvitation;
use App\Modules\WorkerAccess\Models\WorkerAccessLink;
use Illuminate\Support\Facades\Log;

/**
 * RH invites every active person of a center in use with no key in use and no link RH issued that still opens: one queued
 * job each, which makes the link in the worker (see SendWorkerInvitation). Who already has a link that opens keeps it
 * (resending is the one-by-one action), and is counted. A person with no email is left for RH to hand theirs over one by
 * one, and counted. The batch goes to the audit trail once, with counts only.
 */
final readonly class InviteCenter
{
    public function __construct(private WorkCenters $workCenters, private Roster $roster, private WorkerCredentials $credentials, private CurrentTenant $tenant, private RecordAudit $audit) {}

    /**
     * @return array{sent: int, without_email: int, with_key: int, with_link: int}|null null when the center is not one in use of the company
     */
    public function handle(string $workCenterId, int $userId): ?array
    {
        $center = $this->workCenters->findActive($workCenterId);

        if ($center === null) {
            return null;
        }

        $people = $this->roster->activeIn($center->id);
        $ids = array_map(fn (RosterEntry $person): string => $person->id, $people);
        $keys = $this->credentials->countsByEmployee($ids);
        $links = WorkerAccessLink::issuedWaiting($ids);
        $counts = ['sent' => 0, 'without_email' => 0, 'with_key' => 0, 'with_link' => 0];
        $recipients = [];

        foreach ($people as $person) {
            $outcome = match (true) {
                ($keys[$person->id]->active ?? 0) > 0 => 'with_key',
                isset($links[$person->id]) && $links[$person->id]->open() => 'with_link',
                $person->email === null => 'without_email',
                default => 'sent',
            };
            $counts[$outcome]++;

            if ($outcome === 'sent') {
                $recipients[] = $person->id;
            }
        }

        // The trail first: a failed audit insert leaves no job queued.
        $this->audit->handle('worker_access.center_invited', null, ['work_center_id' => $center->id, ...$counts]);
        Log::info('worker_access.center_invited', ['work_center_id' => $center->id, ...$counts]);

        // ponytail: one job per person, all pushed in this request; fine for centers of a few thousand people.
        foreach ($recipients as $employeeId) {
            SendWorkerInvitation::dispatch($this->tenant->id(), $employeeId, $userId);
        }

        return $counts;
    }
}

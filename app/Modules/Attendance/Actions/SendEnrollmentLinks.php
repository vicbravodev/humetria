<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Actions;

use App\Actions\RecordAudit;
use App\Modules\Attendance\Exceptions\WorkCenterNotAvailable;
use App\Modules\Attendance\Jobs\SendEnrollmentLink;
use App\Modules\Attendance\Support\DevicePages;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Organization\Data\RosterEntry;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use App\Modules\Tenancy\Contracts\WorkCenters;
use Illuminate\Support\Facades\Log;

/**
 * Sends their link to link a phone to every active person of a center in use with no active device: one queued job each,
 * which makes the link in the worker (see SendEnrollmentLink). A person with no email is left for RH to hand theirs over
 * one by one, and counted. The batch goes to the audit trail once, with counts only.
 */
final readonly class SendEnrollmentLinks
{
    public function __construct(private WorkCenters $workCenters, private Roster $roster, private CurrentTenant $tenant, private RecordAudit $audit) {}

    /**
     * @return array{sent: int, without_email: int, linked: int}
     *
     * @throws WorkCenterNotAvailable when the center is not one in use of the company
     */
    public function handle(string $workCenterId, string $userId): array
    {
        $center = $this->workCenters->findActive($workCenterId) ?? throw new WorkCenterNotAvailable;
        $people = $this->roster->activeIn($center->id);
        $states = DevicePages::states(array_map(fn (RosterEntry $person): string => $person->id, $people));
        $counts = ['sent' => 0, 'without_email' => 0, 'linked' => 0];

        // ponytail: one job per person, all pushed in this request; fine for centers of a few thousand people.
        foreach ($people as $person) {
            $outcome = match (true) {
                $states[$person->id] === 'linked' => 'linked',
                $person->email === null => 'without_email',
                default => 'sent',
            };
            $counts[$outcome]++;

            if ($outcome === 'sent') {
                SendEnrollmentLink::dispatch($this->tenant->id(), $person->id, $userId);
            }
        }

        $this->audit->handle('attendance.enrollments_sent', null, ['work_center_id' => $center->id, ...$counts]);
        Log::info('attendance.enrollments_sent', ['work_center_id' => $center->id, ...$counts]);

        return $counts;
    }
}

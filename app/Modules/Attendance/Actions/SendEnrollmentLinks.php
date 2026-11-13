<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Actions;

use App\Actions\RecordAudit;
use App\Modules\Attendance\Exceptions\WorkCenterNotAvailable;
use App\Modules\Attendance\Jobs\SendEnrollmentLink;
use App\Modules\Attendance\Models\EnrollmentLink;
use App\Modules\Attendance\Support\CenterReadiness;
use App\Modules\Attendance\Support\DevicePages;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Organization\Data\RosterEntry;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use App\Modules\Tenancy\Contracts\WorkCenters;
use Illuminate\Support\Facades\Log;

/**
 * Sends their link to link a phone to every active person of a center in use with no active device and no link that still
 * opens: one queued job each, which makes the link in the worker (see SendEnrollmentLink). Who already has a link that opens
 * keeps it (resending is the one-by-one action), and is counted. A person with no email is left for RH to hand theirs over
 * one by one, and counted. The batch goes to the audit trail once, with counts only and whether the center could mark (a zone and, when the policy asks for it, a screen).
 */
final readonly class SendEnrollmentLinks
{
    public function __construct(private WorkCenters $workCenters, private Roster $roster, private CurrentTenant $tenant, private RecordAudit $audit) {}

    /**
     * @return array{sent: int, without_email: int, linked: int, with_link: int, center_ready: bool}
     *
     * @throws WorkCenterNotAvailable when the center is not one in use of the company
     */
    public function handle(string $workCenterId, int $userId): array
    {
        $center = $this->workCenters->findActive($workCenterId) ?? throw new WorkCenterNotAvailable;
        $people = $this->roster->activeIn($center->id);
        $states = DevicePages::states(array_map(fn (RosterEntry $person): string => $person->id, $people));
        $withLink = array_flip(EnrollmentLink::openAmong(array_keys($states)));
        $counts = ['sent' => 0, 'without_email' => 0, 'linked' => 0, 'with_link' => 0];
        $recipients = [];

        foreach ($people as $person) {
            $outcome = match (true) {
                $states[$person->id] === 'linked' => 'linked',
                isset($withLink[$person->id]) => 'with_link',
                $person->email === null => 'without_email',
                default => 'sent',
            };
            $counts[$outcome]++;

            if ($outcome === 'sent') {
                $recipients[] = $person->id;
            }
        }

        // Sending to a center that cannot mark yet is allowed (the links are the same); the trail says it was not ready.
        $counts = [...$counts, 'center_ready' => CenterReadiness::of($center->id)];

        // The trail first: a failed audit insert leaves no job queued.
        $this->audit->handle('attendance.enrollments_sent', null, ['work_center_id' => $center->id, ...$counts]);
        Log::info('attendance.enrollments_sent', ['work_center_id' => $center->id, ...$counts]);

        // ponytail: one job per person, all pushed in this request; fine for centers of a few thousand people.
        foreach ($recipients as $employeeId) {
            SendEnrollmentLink::dispatch($this->tenant->id(), $employeeId, $userId);
        }

        return $counts;
    }
}

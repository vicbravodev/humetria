<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Actions;

use App\Actions\RecordAudit;
use App\Modules\Complaints\Models\Complaint;
use App\Modules\Complaints\Models\ComplaintAssignment;
use App\Modules\Complaints\Support\Dates;
use App\Modules\Complaints\Support\HandlerCoverage;
use App\Modules\Tenancy\Contracts\CurrentTenant;

/**
 * Gives a complaint to every active handler its conflict leaves: whoever handles the work center of the complaint (or the
 * whole company), except the role the complaint is against and whoever stepped aside from it. At receipt the system assigns and writes neither audit nor log
 * line with the case (the moment of the line would tie it to the case); a person who assigns by hand is audited.
 */
final readonly class AssignComplaint
{
    public function __construct(private CurrentTenant $tenant, private HandlerCoverage $coverage, private RecordAudit $audit) {}

    /**
     * @param  int|null  $assignedBy  The person who assigns; null is the system, at receipt.
     * @return int how many handlers got the complaint.
     */
    public function handle(Complaint $complaint, ?int $assignedBy = null): int
    {
        $already = ComplaintAssignment::barredFrom($complaint->id);
        $assigned = 0;

        foreach ($this->coverage->handlerIds($complaint->respondent_kind, $complaint->work_center_id) as $userId) {
            if (in_array($userId, $already, true)) {
                continue;
            }

            ComplaintAssignment::query()->create([
                'tenant_id' => $this->tenant->id(),
                'complaint_id' => $complaint->id,
                'user_id' => $userId,
                'assigned_by' => $assignedBy,
                'assigned_on' => Dates::today(),
            ]);

            if ($assignedBy !== null) {
                $this->audit->handle('complaint.assigned', $complaint, ['user_id' => $userId]);
            }

            $assigned++;
        }

        return $assigned;
    }
}

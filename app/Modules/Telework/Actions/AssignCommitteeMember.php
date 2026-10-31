<?php

declare(strict_types=1);

namespace App\Modules\Telework\Actions;

use App\Actions\RecordAudit;
use App\Modules\Telework\Models\CommitteeAssignment;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Illuminate\Support\Facades\Log;

/**
 * Seats a user of the company on the Comisión of a work center. Doing it twice changes nothing.
 */
final readonly class AssignCommitteeMember
{
    public function __construct(private CurrentTenant $tenant, private RecordAudit $audit) {}

    public function handle(string $workCenterId, int $memberId): void
    {
        $assignment = CommitteeAssignment::query()->firstOrCreate(
            ['work_center_id' => $workCenterId, 'user_id' => $memberId],
            ['tenant_id' => $this->tenant->id()],
        );

        if (! $assignment->wasRecentlyCreated) {
            return;
        }

        $record = ['work_center_id' => $workCenterId, 'user_id' => $memberId];
        $this->audit->handle('telework.committee_member_assigned', $assignment, $record);
        Log::info('telework.committee_member_assigned', $record);
    }
}

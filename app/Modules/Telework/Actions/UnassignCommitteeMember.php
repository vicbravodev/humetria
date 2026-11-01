<?php

declare(strict_types=1);

namespace App\Modules\Telework\Actions;

use App\Actions\RecordAudit;
use App\Modules\Telework\Models\CommitteeAssignment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Takes a user off the Comisión of a work center. The last one may go: the center is then left without an active committee.
 */
final readonly class UnassignCommitteeMember
{
    public function __construct(private RecordAudit $audit) {}

    /** @return bool False when the user was not on that committee. */
    public function handle(string $workCenterId, int $memberId): bool
    {
        return DB::transaction(function () use ($workCenterId, $memberId): bool {
            $assignment = CommitteeAssignment::query()->where('work_center_id', $workCenterId)->where('user_id', $memberId)->first();

            if ($assignment === null) {
                return false;
            }

            $assignment->delete();
            $record = ['work_center_id' => $workCenterId, 'user_id' => $memberId];
            $this->audit->handle('telework.committee_member_unassigned', $assignment, $record);
            Log::info('telework.committee_member_unassigned', $record);

            return true;
        });
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Actions;

use App\Actions\RecordAudit;
use App\Modules\Complaints\Enums\EntryKind;
use App\Modules\Complaints\Models\Complaint;
use App\Modules\Complaints\Models\ComplaintAssignment;
use App\Modules\Complaints\Support\CaseThread;
use App\Modules\Complaints\Support\Dates;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Gives the case to another handler. Only one a person who has it chooses, and only one the conflict leaves (the request checks
 * it): being designated is not enough to read a case.
 */
final readonly class ShareCase
{
    public function __construct(private CurrentTenant $tenant, private CaseThread $thread, private RecordAudit $audit) {}

    public function handle(Complaint $complaint, int $userId, int $by): void
    {
        DB::transaction(function () use ($complaint, $userId, $by): void {
            ComplaintAssignment::query()->create([
                'tenant_id' => $this->tenant->id(),
                'complaint_id' => $complaint->id,
                'user_id' => $userId,
                'assigned_by' => $by,
                'assigned_on' => Dates::today(),
            ]);
            $this->thread->append($complaint, EntryKind::AssignmentChange, $by, null, ['user_id' => $userId]);
            $this->audit->handle('complaint.assigned', $complaint, ['user_id' => $userId]);
        });

        Log::info('complaints.assignment_changed', ['complaint_id' => $complaint->id, 'reason' => 'shared']);
    }
}

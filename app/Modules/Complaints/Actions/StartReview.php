<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Actions;

use App\Actions\RecordAudit;
use App\Modules\Complaints\Enums\ComplaintStatus;
use App\Modules\Complaints\Enums\EntryKind;
use App\Modules\Complaints\Models\Complaint;
use App\Modules\Complaints\Support\CaseThread;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/** Moves a new case to review, acknowledging it first if nobody did. */
final readonly class StartReview
{
    public function __construct(private AcknowledgeComplaint $acknowledge, private CaseThread $thread, private RecordAudit $audit) {}

    public function handle(Complaint $complaint, int $by): void
    {
        if ($complaint->status !== ComplaintStatus::New) {
            return;
        }

        $this->acknowledge->handle($complaint, $by);

        DB::transaction(function () use ($complaint, $by): void {
            $complaint->update(['status' => ComplaintStatus::InReview]);
            $change = ['from' => ComplaintStatus::New->value, 'to' => ComplaintStatus::InReview->value];
            $this->thread->append($complaint, EntryKind::StatusChange, $by, null, $change);
            $this->audit->handle('complaint.status_changed', $complaint, $change);
        });

        Log::info('complaints.status_changed', ['complaint_id' => $complaint->id, 'from' => 'new', 'to' => 'in_review']);
    }
}

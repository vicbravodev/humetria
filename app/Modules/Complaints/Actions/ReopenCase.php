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

/** Opens a closed case again, in review: its resolution and day of closing are cleared and the person may write again. */
final readonly class ReopenCase
{
    public function __construct(private CaseThread $thread, private RecordAudit $audit) {}

    public function handle(Complaint $complaint, int $by): void
    {
        if ($complaint->status !== ComplaintStatus::Closed) {
            return;
        }

        DB::transaction(function () use ($complaint, $by): void {
            $complaint->update(['status' => ComplaintStatus::InReview, 'resolution' => null, 'closed_at' => null, 'closed_on' => null]);
            $change = ['from' => 'closed', 'to' => 'in_review'];
            $this->thread->append($complaint, EntryKind::StatusChange, $by, null, $change);
            $this->audit->handle('complaint.status_changed', $complaint, $change);
        });

        Log::info('complaints.status_changed', ['complaint_id' => $complaint->id, 'from' => 'closed', 'to' => 'in_review']);
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Actions;

use App\Actions\RecordAudit;
use App\Modules\Complaints\Models\Complaint;
use Illuminate\Support\Facades\Log;

/** That a handler opened a case: every reading is in the audit trail, with who and when, and never what the case says. */
final readonly class ViewCase
{
    public function __construct(private RecordAudit $audit) {}

    public function handle(Complaint $complaint): void
    {
        $this->audit->handle('complaint.viewed', $complaint);
        Log::info('complaints.case_viewed', ['complaint_id' => $complaint->id]);
    }
}

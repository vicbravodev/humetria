<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Actions;

use App\Actions\RecordAudit;
use App\Modules\Complaints\Models\Complaint;
use Illuminate\Support\Facades\Log;

/** That a handler printed a case: the printout leaves the platform, so it is recorded. */
final readonly class RecordCaseExport
{
    public function __construct(private RecordAudit $audit) {}

    public function handle(Complaint $complaint): void
    {
        $this->audit->handle('complaint.exported', $complaint);
        Log::info('complaints.case_exported', ['complaint_id' => $complaint->id]);
    }
}

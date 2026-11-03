<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Actions;

use App\Actions\RecordAudit;
use App\Modules\Complaints\Enums\EntryKind;
use App\Modules\Complaints\Models\Complaint;
use App\Modules\Complaints\Support\CaseThread;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/** Writes to the person who presented the complaint: they read it when they come back with their reference and key. */
final readonly class WriteToReporter
{
    public function __construct(private CaseThread $thread, private RecordAudit $audit) {}

    public function handle(Complaint $complaint, string $body, int $by): void
    {
        DB::transaction(function () use ($complaint, $body, $by): void {
            $this->thread->append($complaint, EntryKind::HandlerMessage, $by, $body);
            $this->audit->handle('complaint.message_sent', $complaint);
        });

        Log::info('complaints.message_sent', ['complaint_id' => $complaint->id]);
    }
}

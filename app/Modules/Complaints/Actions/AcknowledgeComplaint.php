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

/** Acknowledges receipt: the moment is the handler's, and the person is told in the thread. Doing it again changes nothing. */
final readonly class AcknowledgeComplaint
{
    public const string TEXT = 'Acusamos recibo de su queja. La persona que atiende el caso la está revisando.';

    public function __construct(private CaseThread $thread, private RecordAudit $audit) {}

    public function handle(Complaint $complaint, int $by): void
    {
        if ($complaint->acknowledged_at !== null || $complaint->status === ComplaintStatus::Closed) {
            return;
        }

        DB::transaction(function () use ($complaint, $by): void {
            $complaint->update(['acknowledged_at' => now()]);
            $this->thread->append($complaint, EntryKind::StatusChange, $by, null, ['acknowledged' => true]);
            $this->thread->append($complaint, EntryKind::HandlerMessage, $by, self::TEXT);
            $this->audit->handle('complaint.acknowledged', $complaint);
        });

        Log::info('complaints.acknowledged', ['complaint_id' => $complaint->id]);
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Actions;

use App\Actions\RecordAudit;
use App\Modules\Complaints\Enums\ComplaintResolution;
use App\Modules\Complaints\Enums\ComplaintStatus;
use App\Modules\Complaints\Enums\EntryKind;
use App\Modules\Complaints\Models\Complaint;
use App\Modules\Complaints\Support\CaseThread;
use App\Modules\Complaints\Support\Dates;
use App\Modules\Complaints\Support\ReporterNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Closes a case with the resolution the handler classified it under. It is the handler's classification, not a finding of
 * the platform: nothing here concludes whether there was violence. The answer the handler writes to the person, if any, goes
 * in the thread right before the case is closed.
 */
final readonly class CloseCase
{
    public function __construct(private CaseThread $thread, private RecordAudit $audit, private ReporterNotifier $news) {}

    public function handle(Complaint $complaint, ComplaintResolution $resolution, int $by, ?string $answer = null): void
    {
        $from = $complaint->status->value;

        DB::transaction(function () use ($complaint, $resolution, $by, $from, $answer): void {
            if ($answer !== null) {
                $this->thread->append($complaint, EntryKind::HandlerMessage, $by, $answer);
                $this->audit->handle('complaint.message_sent', $complaint);
            }

            $complaint->update([
                'status' => ComplaintStatus::Closed,
                'resolution' => $resolution,
                'acknowledged_at' => $complaint->acknowledged_at ?? now(),
                'closed_at' => now(),
                'closed_on' => Dates::today(),
            ]);
            $this->thread->append($complaint, EntryKind::StatusChange, $by, null, ['from' => $from, 'to' => 'closed', 'resolution' => $resolution->value]);
            $this->audit->handle('complaint.closed', $complaint, ['resolution' => $resolution->value]);
        });

        if ($answer !== null) {
            Log::info('complaints.message_sent', ['complaint_id' => $complaint->id]);
        }

        Log::info('complaints.closed', ['complaint_id' => $complaint->id, 'resolution' => $resolution->value]);
        $this->news->news($complaint);
    }
}

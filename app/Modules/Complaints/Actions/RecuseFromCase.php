<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Actions;

use App\Actions\RecordAudit;
use App\Modules\Complaints\Enums\EntryKind;
use App\Modules\Complaints\Models\Complaint;
use App\Modules\Complaints\Models\ComplaintAssignment;
use App\Modules\Complaints\Support\CaseThread;
use App\Modules\Complaints\Support\Dates;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/** A handler declares a conflict and steps aside: their assignment ends, it is noted, and the case stays with the others. */
final readonly class RecuseFromCase
{
    public function __construct(private CaseThread $thread, private RecordAudit $audit) {}

    public function handle(Complaint $complaint, int $by): void
    {
        DB::transaction(function () use ($complaint, $by): void {
            ComplaintAssignment::query()->where('complaint_id', $complaint->id)->where('user_id', $by)->whereNull('ended_on')
                ->update(['ended_on' => Dates::today()->toDateString(), 'end_reason' => 'recused']);
            $this->thread->append($complaint, EntryKind::Recusal, $by, null, ['user_id' => $by]);
            $this->audit->handle('complaint.recused', $complaint);
        });

        Log::info('complaints.recused', ['complaint_id' => $complaint->id]);
    }
}

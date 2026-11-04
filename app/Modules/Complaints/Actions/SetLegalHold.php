<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Actions;

use App\Actions\RecordAudit;
use App\Modules\Complaints\Models\Complaint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/** A legal hold keeps a case from being purged while it lasts; setting it and lifting it are audited. */
final readonly class SetLegalHold
{
    public function __construct(private RecordAudit $audit) {}

    public function handle(Complaint $complaint, bool $hold): void
    {
        DB::transaction(function () use ($complaint, $hold): void {
            $complaint->update(['on_hold' => $hold]);
            $this->audit->handle($hold ? 'complaint.hold_set' : 'complaint.hold_lifted', $complaint);
        });

        Log::info('complaints.hold_set', ['complaint_id' => $complaint->id, 'on_hold' => $hold]);
    }
}

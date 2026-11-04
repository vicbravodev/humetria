<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Actions;

use App\Actions\RecordAudit;
use App\Modules\Complaints\Models\ComplaintChannel;
use Illuminate\Support\Facades\Log;

/** That the evidence of the mechanism was exported by a person: it is shown to inspectors, so each time is on record. */
final readonly class RecordEvidenceExport
{
    public function __construct(private RecordAudit $audit) {}

    public function handle(): void
    {
        $channel = ComplaintChannel::query()->first();
        $this->audit->handle('complaint_channel.evidence_exported', $channel);
        Log::info('complaints.evidence_exported', ['channel_id' => $channel?->id]);
    }
}

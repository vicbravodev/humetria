<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Actions;

use App\Actions\RecordAudit;
use App\Modules\Compensation\Models\ReportShare;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/** Closes a link to a report before it expires: from now on it opens nothing. */
final readonly class RevokeReportShare
{
    public function __construct(private RecordAudit $audit) {}

    public function handle(ReportShare $share): void
    {
        DB::transaction(function () use ($share): void {
            $share->update(['revoked_at' => now()]);
            $this->audit->handle('compensation.share_revoked', metadata: ['scenario_id' => $share->scenario_id, 'share_id' => $share->id]);
        });

        Log::info('compensation.share_revoked', ['scenario_id' => $share->scenario_id, 'share_id' => $share->id]);
    }
}

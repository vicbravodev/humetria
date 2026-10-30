<?php

declare(strict_types=1);

namespace App\Modules\Results\Actions;

use App\Actions\RecordAudit;
use App\Modules\Results\Models\ReportShare;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Closes a link to a report before it expires: from now on it opens nothing.
 */
final readonly class RevokeReportShare
{
    public function __construct(private RecordAudit $audit) {}

    public function handle(ReportShare $share): void
    {
        DB::transaction(function () use ($share): void {
            $share->update(['revoked_at' => now()]);
            $this->audit->handle('results.share_revoked', metadata: ['campaign_id' => $share->campaign_id, 'share_id' => $share->id]);
        });

        Log::info('result_share.revoked', ['campaign_id' => $share->campaign_id, 'share_id' => $share->id]);
    }
}

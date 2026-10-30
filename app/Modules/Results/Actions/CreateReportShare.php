<?php

declare(strict_types=1);

namespace App\Modules\Results\Actions;

use App\Actions\RecordAudit;
use App\Modules\Results\Enums\ShareDuration;
use App\Modules\Results\Models\ReportShare;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Opens a temporary link to an evaluation's report. Returns the link's token, the only time it exists in the clear:
 * only its hash is stored, and neither reaches a log or the audit trail.
 */
final readonly class CreateReportShare
{
    public function __construct(private CurrentTenant $currentTenant, private RecordAudit $audit) {}

    /**
     * @return array{ReportShare, string} the link and its token
     */
    public function handle(string $campaignId, ShareDuration $duration, int $userId): array
    {
        $token = Str::random(64);

        $share = DB::transaction(function () use ($campaignId, $duration, $userId, $token): ReportShare {
            $share = ReportShare::query()->create([
                'tenant_id' => $this->currentTenant->id(),
                'campaign_id' => $campaignId,
                'token_hash' => ReportShare::hashToken($token),
                'created_at' => now(),
                'expires_at' => now()->addMinutes($duration->minutes()),
                'created_by' => $userId,
            ]);
            $this->audit->handle('results.share_created', metadata: ['campaign_id' => $campaignId, 'share_id' => $share->id, 'minutes' => $duration->minutes()]);

            return $share;
        });

        Log::info('result_share.created', ['campaign_id' => $campaignId, 'share_id' => $share->id, 'minutes' => $duration->minutes()]);

        return [$share, $token];
    }
}

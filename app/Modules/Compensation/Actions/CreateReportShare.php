<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Actions;

use App\Actions\RecordAudit;
use App\Modules\Compensation\Models\ReportShare;
use App\Modules\Compensation\Models\Scenario;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Opens a temporary link to the report of a scenario, for 1 to 7 days. Returns the link's token, the only time it exists in
 * the clear: only its hash is stored, and neither reaches a log or the audit trail.
 */
final readonly class CreateReportShare
{
    public function __construct(private CurrentTenant $currentTenant, private RecordAudit $audit) {}

    /**
     * @return array{ReportShare, string} the link and its token
     *
     * @throws ValidationException
     */
    public function handle(Scenario $scenario, int $days, int $userId): array
    {
        if ($days < 1 || $days > ReportShare::MAX_DAYS) {
            throw ValidationException::withMessages(['days' => 'Un enlace dura de 1 a '.ReportShare::MAX_DAYS.' días.']);
        }

        $token = Str::random(64);
        $share = DB::transaction(function () use ($scenario, $days, $userId, $token): ReportShare {
            $share = ReportShare::query()->create([
                'tenant_id' => $this->currentTenant->id(),
                'scenario_id' => $scenario->id,
                'token_hash' => ReportShare::hashToken($token),
                'created_at' => now(),
                'expires_at' => now()->addDays($days),
                'created_by' => $userId,
            ]);
            $this->audit->handle('compensation.share_created', metadata: ['scenario_id' => $scenario->id, 'share_id' => $share->id, 'days' => $days]);

            return $share;
        });

        Log::info('compensation.share_created', ['scenario_id' => $scenario->id, 'share_id' => $share->id, 'days' => $days]);

        return [$share, $token];
    }
}

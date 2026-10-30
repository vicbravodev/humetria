<?php

declare(strict_types=1);

namespace App\Modules\Results\Actions;

use App\Modules\Results\Data\ReportDetailsData;
use App\Modules\Results\Models\ReportDetails;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Illuminate\Support\Facades\Log;

/**
 * Saves what HR fills in for an evaluation's report, replacing what was there. Its text never reaches the log.
 */
final readonly class SaveReportDetails
{
    public function __construct(private CurrentTenant $currentTenant) {}

    public function handle(string $campaignId, ReportDetailsData $details, int $userId): void
    {
        ReportDetails::query()->updateOrCreate(['campaign_id' => $campaignId], [
            'tenant_id' => $this->currentTenant->id(),
            'objective' => $details->objective,
            'activities' => $details->activities,
            'conclusions' => $details->conclusions,
            'recommendations' => $details->recommendations,
            'responsible_name' => $details->responsibleName,
            'responsible_license' => $details->responsibleLicense,
            'updated_by' => $userId,
        ]);

        Log::info('result_report.saved', ['campaign_id' => $campaignId]);
    }
}

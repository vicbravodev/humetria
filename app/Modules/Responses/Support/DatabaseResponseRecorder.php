<?php

declare(strict_types=1);

namespace App\Modules\Responses\Support;

use App\Modules\Responses\Contracts\ResponseRecorder;
use App\Modules\Responses\Data\AnonymousAnswers;
use App\Modules\Responses\Data\TraumaAnswers;
use App\Modules\Responses\Models\AnonymousResponse;
use App\Modules\Responses\Models\TraumaScreening;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Illuminate\Support\Facades\Log;

final readonly class DatabaseResponseRecorder implements ResponseRecorder
{
    public function __construct(private CurrentTenant $currentTenant) {}

    public function recordAnonymous(AnonymousAnswers $answers): void
    {
        AnonymousResponse::query()->create([
            'tenant_id' => $this->currentTenant->id(),
            'campaign_id' => $answers->campaignId,
            'work_center_id' => $answers->workCenterId,
            'area_id' => $answers->areaId,
            'instrument' => $answers->instrument,
            'instrument_version' => $answers->version,
            'answers' => $answers->answers,
        ]);

        // Neither the response's id nor who gave it: with the participant's own log line, it would tie them together.
        Log::info('response.recorded', ['campaign_id' => $answers->campaignId, 'instrument' => $answers->instrument]);
    }

    public function recordTraumaScreening(TraumaAnswers $answers): void
    {
        TraumaScreening::query()->create([
            'tenant_id' => $this->currentTenant->id(),
            'campaign_id' => $answers->campaignId,
            'employee_id' => $answers->employeeId,
            'instrument_version' => $answers->version,
            'answers' => $answers->answers,
            'requires_clinical_assessment' => $answers->requiresClinicalAssessment,
            'answered_at' => now(),
        ]);

        // Whether the person needs a clinical assessment is health data: it stays out of the log.
        Log::info('trauma_screening.recorded', ['campaign_id' => $answers->campaignId]);
    }
}
